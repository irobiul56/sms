<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Homework;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Section;
use App\Models\TeacherSubject;
use App\Services\TeacherPermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $instituteId = (int) $user->institute_id;
        $isTeacher = strtolower((string) $user->usertype) === 'teacher';
        $academicYear = AcademicYear::forInstitute($instituteId)->where('is_current', true)->first();

        if ($isTeacher) {
            $teacher = $user->teacher()->where('institute_id', $instituteId)->firstOrFail();
            $assignments = TeacherSubject::query()
                ->active()
                ->where('teacher_id', $teacher->id)
                ->whereJsonContains('permissions', 'homework.manage')
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->with(['instituteClass:id,name', 'section:id,name,institute_class_id', 'instituteSubject:id,name,bn_name'])
                ->get();

            $classIds = $assignments->pluck('institute_class_id')->unique();
            $classes = $assignments->pluck('instituteClass')->filter()->unique('id')->values();
            $subjects = $assignments->pluck('instituteSubject')->filter()->unique('id')->values();
            $sections = $assignments->pluck('section')->filter()->unique('id')->values();

            if ($assignments->contains(fn (TeacherSubject $assignment) => $assignment->section_id === null)) {
                $sections = $sections->merge(
                    Section::forInstitute($instituteId)
                        ->whereIn('institute_class_id', $classIds)
                        ->get(['id', 'name', 'institute_class_id'])
                )->unique('id')->values();
            }

            $homework = Homework::query()
                ->where('institute_id', $instituteId)
                ->where('teacher_id', $teacher->id)
                ->with(['instituteClass:id,name', 'section:id,name', 'instituteSubject:id,name,bn_name', 'teacher:id,name'])
                ->latest()
                ->get();
        } else {
            $classes = InstituteClass::forInstitute($instituteId)->orderBy('name')            ->get(['id', 'name', 'group']);
            $subjects = InstituteSubject::forInstitute($instituteId)->orderBy('name')->get(['id', 'name', 'bn_name']);
            $sections = Section::forInstitute($instituteId)->get(['id', 'name', 'institute_class_id']);
            $homework = Homework::query()
                ->where('institute_id', $instituteId)
                ->with(['instituteClass:id,name', 'section:id,name', 'instituteSubject:id,name,bn_name', 'teacher:id,name'])
                ->latest()
                ->get();
        }

        return Inertia::render('Institute/Homework/Index', [
            'homework' => $homework,
            'academicYear' => $academicYear,
            'classes' => $classes,
            'subjects' => $subjects,
            'sections' => $sections,
            'isTeacher' => $isTeacher,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'institute_class_id' => ['required', 'integer', 'exists:institute_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'institute_subject_id' => ['required', 'integer', 'exists:institute_subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
        ]);

        $this->authorizeHomeworkScope($request, $validated);
        $user = $request->user();

        Homework::create([
            ...$validated,
            'institute_id' => $user->institute_id,
            'teacher_id' => strtolower((string) $user->usertype) === 'teacher'
                ? $user->teacher?->id
                : null,
            'created_by' => $user->id,
            'is_published' => true,
        ]);

        return redirect()->route('homework.index')->with('success', 'Homework published successfully.');
    }

    public function update(Request $request, Homework $homework)
    {
        $this->authorizeHomeworkRecord($request, $homework);
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'institute_class_id' => ['required', 'integer', 'exists:institute_classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'institute_subject_id' => ['required', 'integer', 'exists:institute_subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
        ]);
        $this->authorizeHomeworkScope($request, $validated);
        $homework->update($validated);

        return redirect()->route('homework.index')->with('success', 'Homework updated successfully.');
    }

    public function destroy(Request $request, Homework $homework)
    {
        $this->authorizeHomeworkRecord($request, $homework);
        $homework->delete();

        return redirect()->route('homework.index')->with('success', 'Homework deleted successfully.');
    }

    private function authorizeHomeworkRecord(Request $request, Homework $homework): void
    {
        $user = $request->user();
        abort_unless((int) $homework->institute_id === (int) $user->institute_id, 403);

        if (strtolower((string) $user->usertype) === 'teacher') {
            abort_unless((int) $homework->teacher_id === (int) $user->teacher?->id, 403);
        }
    }

    private function authorizeHomeworkScope(Request $request, array $validated): void
    {
        $user = $request->user();
        if (strtolower((string) $user->usertype) !== 'teacher') {
            abort_unless(
                InstituteClass::forInstitute($user->institute_id)->whereKey($validated['institute_class_id'])->exists()
                && InstituteSubject::forInstitute($user->institute_id)->whereKey($validated['institute_subject_id'])->exists(),
                403
            );
            return;
        }

        abort_unless(
            app(TeacherPermissionService::class)->hasSubjectPermission(
                $user,
                'homework.manage',
                (int) $validated['institute_class_id'],
                (int) $validated['institute_subject_id'],
                isset($validated['section_id']) ? (int) $validated['section_id'] : null,
                (int) $validated['academic_year_id']
            ),
            403
        );
    }
}
