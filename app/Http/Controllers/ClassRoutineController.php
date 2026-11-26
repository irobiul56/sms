<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ClassRoutine;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassRoutineController extends Controller
{
    public function print(Request $request): Response
    {
        $instituteId = Auth::user()->institute_id;
        $academicYear = $this->currentAcademicYear($instituteId);
        abort_unless($academicYear, 404);

        $classId = $request->input('class_id');
        $query = ClassRoutine::query()
            ->where('institute_id', $instituteId)
            ->where('academic_year_id', $academicYear->id)
            ->with(['instituteClass:id,name,bn_name', 'section:id,name', 'instituteSubject:id,name,bn_name', 'teacher:id,name,bn_name'])
            ->orderBy('institute_class_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time');

        if ($classId && $classId !== 'all') {
            $query->where('institute_class_id', $classId);
        }

        $routines = $query->get()->map(fn (ClassRoutine $routine) => [
            'id' => $routine->id,
            'class_name' => $routine->instituteClass->bn_name ?: $routine->instituteClass->name,
            'class_name_en' => $routine->instituteClass->name,
            'section_name' => $routine->section?->name,
            'subject_name' => $routine->instituteSubject->bn_name ?: $routine->instituteSubject->name,
            'subject_name_en' => $routine->instituteSubject->name,
            'teacher_name' => $routine->teacher?->bn_name ?: $routine->teacher?->name,
            'teacher_name_en' => $routine->teacher?->name,
            'day_of_week' => $routine->day_of_week,
            'start_time' => substr($routine->start_time, 0, 5),
            'end_time' => substr($routine->end_time, 0, 5),
            'classroom' => $routine->classroom,
        ])->values();

        abort_if($routines->isEmpty(), 404);

        return Inertia::render('Institute/ClassRoutines/RoutinePrint', [
            'institute' => [
                'name' => Auth::user()->institute->name,
                'address' => Auth::user()->institute->address,
            ],
            'academicYear' => $academicYear->session ?: $academicYear->name,
            'routines' => $routines,
        ]);
    }

    public function index(): Response
    {
        $instituteId = Auth::user()->institute_id;
        $academicYear = $this->currentAcademicYear($instituteId);

        return Inertia::render('Institute/ClassRoutines/Index', [
            'academicYear' => $academicYear ? [
                'id' => $academicYear->id,
                'name' => $academicYear->session ?: $academicYear->name,
            ] : null,
            'classes' => InstituteClass::query()
                ->where('institute_id', $instituteId)
                ->with('sections:id,institute_class_id,name')
                ->ordered()
                ->get(['id', 'institute_id', 'name'])
                ->map(fn (InstituteClass $class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'sections' => $class->sections->map(fn ($section) => [
                        'id' => $section->id,
                        'name' => $section->name,
                    ])->values(),
                ])->values(),
            'subjects' => InstituteSubject::query()
                ->where('institute_id', $instituteId)
                ->with('classes:id')
                ->orderBy('name')
                ->get()
                ->map(fn (InstituteSubject $subject) => [
                    'id' => $subject->id,
                    'name' => $subject->bn_name ?: $subject->name,
                    'class_ids' => $subject->classes->pluck('id'),
                ])->values(),
            'teachers' => Teacher::query()
                ->where('institute_id', $instituteId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'bn_name'])
                ->map(fn (Teacher $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->bn_name ?: $teacher->name,
                ])->values(),
            'routines' => $academicYear
                ? ClassRoutine::query()
                    ->where('institute_id', $instituteId)
                    ->where('academic_year_id', $academicYear->id)
                    ->with(['instituteClass:id,name', 'section:id,name', 'instituteSubject:id,name,bn_name', 'teacher:id,name,bn_name'])
                    ->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get()
                    ->map(fn (ClassRoutine $routine) => [
                        'id' => $routine->id,
                        'institute_class_id' => $routine->institute_class_id,
                        'section_id' => $routine->section_id,
                        'institute_subject_id' => $routine->institute_subject_id,
                        'teacher_id' => $routine->teacher_id,
                        'day_of_week' => $routine->day_of_week,
                        'start_time' => substr($routine->start_time, 0, 5),
                        'end_time' => substr($routine->end_time, 0, 5),
                        'classroom' => $routine->classroom,
                        'class_name' => $routine->instituteClass->name,
                        'section_name' => $routine->section?->name,
                        'subject_name' => $routine->instituteSubject->bn_name ?: $routine->instituteSubject->name,
                        'teacher_name' => $routine->teacher?->bn_name ?: $routine->teacher?->name,
                    ])->values()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instituteId = Auth::user()->institute_id;
        $academicYear = $this->currentAcademicYear($instituteId);
        if (! $academicYear) {
            throw ValidationException::withMessages([
                'academic_year' => 'রুটিন যোগ করতে আগে একটি চলতি শিক্ষাবর্ষ নির্ধারণ করুন।',
            ]);
        }

        if ($request->has('routines')) {
            $common = $request->validate([
                'institute_class_id' => [
                    'required',
                    Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
                ],
                'section_id' => [
                    'nullable',
                    Rule::exists('sections', 'id')
                        ->where('institute_id', $instituteId)
                        ->where('institute_class_id', $request->input('institute_class_id')),
                ],
                'institute_subject_id' => [
                    'required',
                    Rule::exists('institute_class_institute_subjects', 'institute_subject_id')
                        ->where('institute_class_id', $request->input('institute_class_id')),
                ],
                'routines' => ['required', 'array', 'min:1'],
            ]);

            $rows = $request->validate([
                'routines.*.teacher_id' => [
                    'nullable',
                    Rule::exists('teachers', 'id')->where('institute_id', $instituteId),
                ],
                'routines.*.day_of_week' => ['required', 'integer', 'between:0,6'],
                'routines.*.start_time' => ['required', 'date_format:H:i'],
                'routines.*.end_time' => ['required', 'date_format:H:i', 'after:routines.*.start_time'],
                'routines.*.classroom' => ['nullable', 'string', 'max:100'],
            ])['routines'];

            DB::transaction(function () use ($common, $rows, $academicYear, $instituteId) {
                foreach ($rows as $index => $row) {
                    $data = [
                        ...$common,
                        ...$row,
                        'section_id' => $common['section_id'] ?? null,
                    ];
                    unset($data['routines']);
                    $this->ensureNoTimeConflict($data, $academicYear->id, $instituteId);

                    foreach (array_slice($rows, 0, $index) as $existingRow) {
                        if (
                            (int) $existingRow['day_of_week'] === (int) $row['day_of_week']
                            && $existingRow['start_time'] < $row['end_time']
                            && $existingRow['end_time'] > $row['start_time']
                        ) {
                            throw ValidationException::withMessages([
                                "routines.$index.start_time" => 'একই সময়ে একাধিক ক্লাস যোগ করা হয়েছে।',
                            ]);
                        }
                    }

                    ClassRoutine::create([
                        ...$data,
                        'institute_id' => $instituteId,
                        'academic_year_id' => $academicYear->id,
                    ]);
                }
            });

            return redirect()->route('class-routines.index')->with('success', 'ক্লাস রুটিন যোগ হয়েছে।');
        }

        $data = $this->validatedData($request, $instituteId);
        $this->ensureNoTimeConflict($data, $academicYear->id, $instituteId);

        ClassRoutine::create([
            ...$data,
            'institute_id' => $instituteId,
            'academic_year_id' => $academicYear->id,
        ]);

        return redirect()->route('class-routines.index')->with('success', 'ক্লাস রুটিন যোগ হয়েছে।');
    }

    public function update(Request $request, ClassRoutine $classRoutine): RedirectResponse
    {
        $instituteId = Auth::user()->institute_id;
        $academicYear = $this->currentAcademicYear($instituteId);
        abort_unless($academicYear, 404);

        $classRoutine = ClassRoutine::query()
            ->where('institute_id', $instituteId)
            ->where('academic_year_id', $academicYear->id)
            ->findOrFail($classRoutine->id);

        $data = $this->validatedData($request, $instituteId);
        $this->ensureNoTimeConflict($data, $academicYear->id, $instituteId, $classRoutine->id);
        $classRoutine->update($data);

        return redirect()->route('class-routines.index')->with('success', 'ক্লাস রুটিন হালনাগাদ হয়েছে।');
    }

    public function destroy(ClassRoutine $classRoutine): RedirectResponse
    {
        $academicYear = $this->currentAcademicYear(Auth::user()->institute_id);
        abort_unless($academicYear, 404);

        ClassRoutine::query()
            ->where('institute_id', Auth::user()->institute_id)
            ->where('academic_year_id', $academicYear->id)
            ->findOrFail($classRoutine->id)
            ->delete();

        return redirect()->route('class-routines.index')->with('success', 'ক্লাস রুটিন মুছে ফেলা হয়েছে।');
    }

    private function validatedData(Request $request, int $instituteId): array
    {
        return $request->validate([
            'institute_class_id' => [
                'required',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'section_id' => [
                'nullable',
                Rule::exists('sections', 'id')
                    ->where('institute_id', $instituteId)
                    ->where('institute_class_id', $request->input('institute_class_id')),
            ],
            'institute_subject_id' => [
                'required',
                Rule::exists('institute_class_institute_subjects', 'institute_subject_id')
                    ->where('institute_class_id', $request->input('institute_class_id')),
            ],
            'teacher_id' => [
                'nullable',
                Rule::exists('teachers', 'id')->where('institute_id', $instituteId),
            ],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'classroom' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function ensureNoTimeConflict(array $data, int $academicYearId, int $instituteId, ?int $exceptId = null): void
    {
        $conflicts = ClassRoutine::query()
            ->where('institute_id', $instituteId)
            ->where('academic_year_id', $academicYearId)
            ->where('institute_class_id', $data['institute_class_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where(function ($query) use ($data) {
                if ($data['section_id'] ?? null) {
                    $query->whereNull('section_id')->orWhere('section_id', $data['section_id']);
                }
            })
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages([
                'start_time' => 'এই সময়ে একই শ্রেণির আরেকটি ক্লাস আছে।',
            ]);
        }
    }

    private function currentAcademicYear(int $instituteId): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->where('is_current', true)
            ->first();
    }
}
