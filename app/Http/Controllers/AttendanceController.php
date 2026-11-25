<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\InstituteClass;
use App\Models\Institute;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Services\TeacherPermissionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $instituteId = $this->instituteId($request);
        $academicYear = $this->currentAcademicYear($instituteId);
        $isTeacher = strtolower((string) $request->user()->usertype) === 'teacher';
        $teacher = Teacher::forInstitute($instituteId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        $classAssignments = $isTeacher
            ? $teacher?->classTeachers()
                ->where(fn ($query) => $query
                    ->whereJsonContains('permissions', 'attendance.view')
                    ->orWhereJsonContains('permissions', 'attendance.manage'))
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->get(['institute_class_id', 'section_id'])
            : collect();
        $classIds = $classAssignments?->pluck('institute_class_id')->unique() ?? collect();
        $allClassSections = $classAssignments?->whereNull('section_id')->pluck('institute_class_id')->unique() ?? collect();
        $sectionIds = $classAssignments?->pluck('section_id')->filter()->unique() ?? collect();

        $classes = InstituteClass::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $classIds))
            ->ordered()
            ->get(['id', 'name', 'group']);

        $sections = Section::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($allClassSections, $sectionIds) {
                $query->whereIn('institute_class_id', $allClassSections)
                    ->orWhereIn('id', $sectionIds);
            }))
            ->when(! $isTeacher && $classes->isNotEmpty(), fn ($query) => $query->whereIn('institute_class_id', $classes->pluck('id')))
            ->when($classes->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('display_order')
            ->get(['id', 'institute_class_id', 'name']);

        $teachers = Teacher::forInstitute($instituteId)
            ->where('status', 'active')
            ->when($isTeacher, fn ($query) => $query->whereKey($teacher?->id ?? 0))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id']);

        $attendance = Attendance::query()
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->with([
                'student:id,name,student_id,roll_no',
                'instituteClass:id,name',
                'section:id,name',
                'teacher:id,name',
            ])
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($allClassSections, $sectionIds) {
                $query->whereIn('institute_class_id', $allClassSections)
                    ->orWhereIn('section_id', $sectionIds);
            }))
            ->latest('date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Institute/Attendance/Index', [
            'attendance' => $attendance,
            'academicYear' => $academicYear?->only(['id', 'session']),
            'classes' => $classes,
            'sections' => $sections,
            'teachers' => $teachers,
            'currentTeacherId' => $teacher?->id,
            'isTeacher' => $isTeacher,
            'manageableClassIds' => $isTeacher
                ? $teacher?->classTeachers()
                    ->where('academic_year_id', $academicYear?->id)
                    ->whereJsonContains('permissions', 'attendance.manage')
                    ->pluck('institute_class_id')->unique()->values() ?? collect()
                : $classes->pluck('id'),
        ]);
    }

    public function roster(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'class_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $instituteId = $this->instituteId($request);
        $academicYear = $this->currentAcademicYear($instituteId);
        abort_if(! $academicYear, 422, 'Set a current academic year before taking attendance.');

        $class = InstituteClass::forInstitute($instituteId)->findOrFail($filters['class_id']);
        $section = Section::forInstitute($instituteId)
            ->where('institute_class_id', $class->id)
            ->findOrFail($filters['section_id']);
        if (strtolower((string) $request->user()->usertype) === 'teacher') {
            abort_unless(app(TeacherPermissionService::class)->hasClassPermission(
                $request->user(),
                'attendance.manage',
                (int) $class->id,
                (int) $section->id,
                (int) $academicYear->id
            ), 403);
        }

        $students = StudentAcademicInfo::query()
            ->with('student')
            ->where('academic_year_id', $academicYear->id)
            ->where('institute_class_id', $class->id)
            ->where('section_id', $section->id)
            ->whereHas('student', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('status', 'active'))
            ->orderBy('roll_number')
            ->get()
            ->filter(fn (StudentAcademicInfo $info) => $info->student !== null)
            ->values();

        $existingRecords = Attendance::query()
            ->where('institute_class_id', $class->id)
            ->where('section_id', $section->id)
            ->whereDate('date', $filters['date'])
            ->whereIn('student_id', $students->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        return response()->json([
            'data' => $students->map(function (StudentAcademicInfo $info) use ($existingRecords) {
                $student = $info->student;

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                    'roll_no' => $info->roll_number,
                    'photo' => $student->photo_url,
                    'status' => $existingRecords->get($student->id)?->status ?? 'absent',
                ];
            }),
        ]);
    }

    public function report(Request $request): Response
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);
        $instituteId = $this->instituteId($request);
        $academicYear = $this->currentAcademicYear($instituteId);
        $isTeacher = strtolower((string) $request->user()->usertype) === 'teacher';
        $startDate = Carbon::parse($filters['from_date'] ?? now()->startOfMonth()->toDateString());
        $endDate = Carbon::parse($filters['to_date'] ?? now()->toDateString());

        $teacherAssignments = $isTeacher && $academicYear
            ? $request->user()->teacher?->classTeachers()
                ->where(fn ($query) => $query
                    ->whereJsonContains('permissions', 'attendance.view')
                    ->orWhereJsonContains('permissions', 'attendance.manage'))
                ->where('academic_year_id', $academicYear->id)
                ->get(['institute_class_id', 'section_id']) ?? collect()
            : collect();
        $classWideIds = $teacherAssignments->whereNull('section_id')->pluck('institute_class_id')->unique();
        $assignedSectionIds = $teacherAssignments->pluck('section_id')->filter()->unique();
        $classes = InstituteClass::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn(
                'id',
                $teacherAssignments->pluck('institute_class_id')->unique()
            ))
            ->ordered()
            ->get(['id', 'name', 'group']);
        $sections = Section::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($classWideIds, $assignedSectionIds) {
                $query->whereIn('institute_class_id', $classWideIds)
                    ->orWhereIn('id', $assignedSectionIds);
            }))
            ->when(! $isTeacher && $classes->isNotEmpty(), fn ($query) => $query->whereIn(
                'institute_class_id',
                $classes->pluck('id')
            ))
            ->when($classes->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('display_order')
            ->get(['id', 'institute_class_id', 'name']);

        $classId = $filters['class_id'] ?? null;
        $sectionId = $filters['section_id'] ?? null;
        if ($classId) {
            $class = InstituteClass::forInstitute($instituteId)->findOrFail($classId);
            abort_unless($classes->contains('id', $class->id), 403);
        }
        if ($sectionId) {
            $section = Section::forInstitute($instituteId)
                ->when($classId, fn ($query) => $query->where('institute_class_id', $classId))
                ->findOrFail($sectionId);
            $classId ??= $section->institute_class_id;
            abort_unless($sections->contains('id', $section->id), 403);
        }
        $records = DB::table('attendances')
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->join('institute_classes', 'institute_classes.id', '=', 'attendances.institute_class_id')
            ->join('sections', 'sections.id', '=', 'attendances.section_id')
            ->where('students.institute_id', $instituteId)
            ->whereBetween('attendances.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($classId, fn ($query) => $query->where('attendances.institute_class_id', $classId))
            ->when($sectionId, fn ($query) => $query->where('attendances.section_id', $sectionId))
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($classWideIds, $assignedSectionIds) {
                $query->whereIn('attendances.institute_class_id', $classWideIds)
                    ->orWhereIn('attendances.section_id', $assignedSectionIds);
            }))
            ->groupBy(
                'students.id',
                'students.student_id',
                'students.name',
                'students.roll_no',
                'students.phone',
                'attendances.institute_class_id',
                'attendances.section_id',
                'institute_classes.name',
                'sections.name'
            )
            ->orderBy('institute_classes.name')
            ->orderBy('sections.name')
            ->orderBy('students.roll_no')
            ->select([
                'students.id',
                'students.student_id',
                'students.name',
                'students.roll_no',
                'students.phone',
                'attendances.institute_class_id',
                'attendances.section_id',
                'institute_classes.name as class_name',
                'sections.name as section_name',
            ])
            ->selectRaw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_days")
            ->selectRaw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent_days")
            ->selectRaw("SUM(CASE WHEN attendances.status = 'leave' THEN 1 ELSE 0 END) as leave_days")
            ->get();

        $attendanceDetails = DB::table('attendances')
            ->join('students', 'students.id', '=', 'attendances.student_id')
            ->where('students.institute_id', $instituteId)
            ->whereIn('attendances.student_id', $records->pluck('id'))
            ->whereBetween('attendances.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($classId, fn ($query) => $query->where('attendances.institute_class_id', $classId))
            ->when($sectionId, fn ($query) => $query->where('attendances.section_id', $sectionId))
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($classWideIds, $assignedSectionIds) {
                $query->whereIn('attendances.institute_class_id', $classWideIds)
                    ->orWhereIn('attendances.section_id', $assignedSectionIds);
            }))
            ->orderBy('attendances.date')
            ->get([
                'attendances.student_id',
                'attendances.institute_class_id',
                'attendances.section_id',
                'attendances.date',
                'attendances.status',
            ]);
        $absentDates = $attendanceDetails
            ->where('status', 'absent')
            ->groupBy(fn ($attendance) => implode(':', [
                $attendance->student_id,
                $attendance->institute_class_id,
                $attendance->section_id,
            ]))
            ->map(fn ($attendance) => $attendance->pluck('date')->values());
        $records->each(function ($record) use ($absentDates) {
            $record->absent_dates = $absentDates->get(implode(':', [
                $record->id,
                $record->institute_class_id,
                $record->section_id,
            ]), collect());
        });

        $institute = Institute::findOrFail($instituteId);

        return Inertia::render('Institute/Attendance/Report', [
            'institute' => [
                ...$institute->only(['name', 'address', 'phone', 'eiin']),
                'logo_url' => $institute->logo
                    ? Storage::disk('public')->url($institute->logo)
                    : null,
            ],
            'records' => $records,
            'classes' => $classes,
            'sections' => $sections,
            'filters' => [
                'from_date' => $startDate->toDateString(),
                'to_date' => $endDate->toDateString(),
                'class_id' => $classId,
                'section_id' => $sectionId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instituteId = $this->instituteId($request);
        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('teachers', 'id')->where(fn ($query) => $query
                    ->where('institute_id', $instituteId)
                    ->where('status', 'active')),
            ],
            'reviewed_unmatched' => ['accepted'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'leave'])],
        ]);

        $academicYear = $this->currentAcademicYear($instituteId);
        if (! $academicYear) {
            throw ValidationException::withMessages([
                'class_id' => 'Set a current academic year before taking attendance.',
            ]);
        }

        $class = InstituteClass::forInstitute($instituteId)->findOrFail($validated['class_id']);
        $section = Section::forInstitute($instituteId)
            ->where('institute_class_id', $class->id)
            ->findOrFail($validated['section_id']);
        $isTeacher = strtolower((string) $request->user()->usertype) === 'teacher';
        if ($isTeacher) {
            abort_unless(app(TeacherPermissionService::class)->hasClassPermission(
                $request->user(),
                'attendance.manage',
                (int) $class->id,
                (int) $section->id,
                (int) $academicYear->id
            ), 403);
        }

        $students = StudentAcademicInfo::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('institute_class_id', $class->id)
            ->where('section_id', $section->id)
            ->whereHas('student', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('status', 'active'))
            ->get(['student_id']);

        $enrolledStudentIds = $students->pluck('student_id')->sort()->values();
        $submittedStudentIds = collect($validated['records'])
            ->pluck('student_id')
            ->sort()
            ->values();

        if ($enrolledStudentIds->isEmpty() || $enrolledStudentIds->all() !== $submittedStudentIds->all()) {
            throw ValidationException::withMessages([
                'records' => 'Attendance must include every active student in the selected class and section.',
            ]);
        }

        $markedBy = $isTeacher ? (int) $request->user()->teacher->id : (int) $validated['teacher_id'];

        DB::transaction(function () use ($validated, $class, $section, $markedBy) {
            foreach ($validated['records'] as $entry) {
                $record = Attendance::query()
                    ->where('student_id', $entry['student_id'])
                    ->whereDate('date', $validated['date'])
                    ->first() ?? new Attendance();
                $record->student_id = $entry['student_id'];
                $record->date = $validated['date'];
                $record->institute_class_id = $class->id;
                $record->section_id = $section->id;
                $record->status = $entry['status'];
                $record->marked_by = $markedBy;
                $record->save();
            }
        });

        return redirect()->route('attendance.index')
            ->with('success', 'Attendance saved successfully.');
    }

    private function instituteId(Request $request): int
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned to this account.');

        return $instituteId;
    }

    private function currentAcademicYear(int $instituteId): ?AcademicYear
    {
        return AcademicYear::forInstitute($instituteId)
            ->where('is_current', true)
            ->first();
    }
}
