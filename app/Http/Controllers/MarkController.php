<?php

namespace App\Http\Controllers;

use App\Models\Mark;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\InstituteSubject;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\Teacher;
use App\Services\MarkGradingService;
use App\Services\TeacherPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MarkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instituteId = Auth::user()->institute_id;
        $isTeacher = strtolower((string) Auth::user()->usertype) === 'teacher';
        $teacherAssignments = $isTeacher
            ? Auth::user()->teacher->subjectAssignments()->active()->get()
            : collect();
        $viewAssignments = $teacherAssignments->filter(fn ($assignment) => in_array('marks.view', $assignment->permissions ?? [], true));
        $marksQuery = $this->instituteMarks($instituteId);
        if ($isTeacher) {
            app(TeacherPermissionService::class)->restrictMarksToPermission($marksQuery, Auth::user(), 'marks.view');
        }

        $marks = $marksQuery
            ->with(['exam', 'student', 'instituteSubject', 'instituteClass', 'section', 'teacher'])
            ->latest()
            ->get()
            ->map(function($mark) use ($isTeacher, $teacherAssignments) {
                $assignment = $isTeacher
                    ? $teacherAssignments->first(fn ($assignment) => (int) $assignment->institute_class_id === (int) $mark->institute_class_id
                        && (int) $assignment->institute_subject_id === (int) $mark->institute_subject_id
                        && ($assignment->section_id === null || (int) $assignment->section_id === (int) $mark->section_id)
                        && (int) $assignment->academic_year_id === (int) $mark->exam->academic_year_id)
                    : null;

                return [
                    'id' => $mark->id,
                    'exam_name' => $mark->exam->name ?? 'N/A',
                    'student_name' => $mark->student->name ?? 'N/A',
                    'student_id' => $mark->student->student_id ?? 'N/A',
                    'roll_no' => $mark->student->roll_no ?? 'N/A',
                    'subject_name' => $mark->instituteSubject->name ?? 'N/A',
                    'class_name' => $mark->instituteClass->name ?? 'N/A',
                    'section_name' => $mark->section->name ?? 'N/A',
                    'teacher_name' => $mark->teacher->name ?? 'N/A',
                    'theory_marks' => $mark->theory_marks,
                    'practical_marks' => $mark->practical_marks,
                    'mcq_marks' => $mark->mcq_marks,
                    'total_marks' => $mark->total_marks,
                    'grade' => $mark->grade,
                    'grade_label' => $mark->grade_label,
                    'remarks' => $mark->remarks,
                    'is_absent' => $mark->is_absent,
                    'is_reexamine' => $mark->is_reexamine,
                    'status_label' => $mark->status_label,
                    'status_badge_class' => $mark->status_badge_class,
                    'can_update' => ! $isTeacher || in_array('marks.update', $assignment?->permissions ?? [], true),
                    'can_delete' => ! $isTeacher || in_array('marks.delete', $assignment?->permissions ?? [], true),
                    'created_at' => $mark->created_at,
                    'updated_at' => $mark->updated_at
                ];
            });

        // Get dropdown data
        $exams = Exam::whereHas('instituteClass', function($query) use ($instituteId) {
            $query->where('institute_id', $instituteId);
        })
        ->when($isTeacher, fn ($query) => $query
            ->whereIn('academic_year_id', $viewAssignments->pluck('academic_year_id')->unique())
            ->whereHas('examSubjects', fn ($subjects) => $subjects->whereIn('institute_subject_id', $viewAssignments->pluck('institute_subject_id')->unique()))
            ->where(function ($query) use ($viewAssignments) {
                $query->whereIn('institute_class_id', $viewAssignments->pluck('institute_class_id')->unique())
                    ->orWhereHas('classes', fn ($classes) => $classes->whereIn('institute_classes.id', $viewAssignments->pluck('institute_class_id')->unique()));
            }))
        ->with(['instituteClass', 'academicYear', 'examSubjects'])
        ->orderBy('start_date', 'desc')
        ->get()
        ->map(function($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'class_name' => $exam->instituteClass->name ?? 'N/A',
                'academic_year' => $exam->academicYear->session ?? 'N/A',
                'academic_year_id' => $exam->academic_year_id,
                'subject_ids' => $exam->examSubjects->pluck('institute_subject_id'),
                'class_ids' => $exam->classIds(),
            ];
        })
        ->when($isTeacher, fn ($exams) => $exams->filter(fn ($exam) => $viewAssignments->contains(
            fn ($assignment) => (int) $assignment->academic_year_id === (int) $exam['academic_year_id']
                && in_array((int) $assignment->institute_class_id, array_map('intval', $exam['class_ids']), true)
                && $exam['subject_ids']->contains((int) $assignment->institute_subject_id)
        )))
        ->map(fn ($exam) => collect($exam)->except(['academic_year_id', 'subject_ids', 'class_ids'])->all());

        $classes = InstituteClass::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $viewAssignments->pluck('institute_class_id')->unique()))
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $subjects = InstituteSubject::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $viewAssignments->pluck('institute_subject_id')->unique()))
            ->ordered()
            ->get(['id', 'name', 'code']);

        $students = Student::forInstitute($instituteId)
            ->active()
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $this->studentIdsForAssignments($viewAssignments)))
            ->get(['id', 'name', 'student_id', 'roll_no']);

        $sections = Section::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($viewAssignments) {
                $query->whereIn('institute_class_id', $viewAssignments->whereNull('section_id')->pluck('institute_class_id')->unique())
                    ->orWhereIn('id', $viewAssignments->pluck('section_id')->filter()->unique());
            }))
            ->ordered()
            ->get(['id', 'name']);

        $teachers = Teacher::forInstitute($instituteId)
            ->active()
            ->when($isTeacher, fn ($query) => $query->where('user_id', Auth::id()))
            ->get(['id', 'name']);

        $stats = [
            'total' => $marks->count(),
            'passed' => $marks->where('grade', '!=', 'F')->where('grade', '!=', null)->count(),
            'failed' => $marks->where('grade', 'F')->count(),
            'absent' => $marks->where('is_absent', true)->count(),
            'reexamine' => $marks->where('is_reexamine', true)->count()
        ];

        return Inertia::render('Institute/Marks/Index', [
            'marks' => $marks,
            'exams' => $exams,
            'classes' => $classes,
            'subjects' => $subjects,
            'students' => $students,
            'sections' => $sections,
            'teachers' => $teachers,
            'stats' => $stats
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $instituteId = auth()->user()->institute_id;
        $isTeacher = strtolower((string) auth()->user()->usertype) === 'teacher';
        $teacherAssignments = $isTeacher
            ? auth()->user()->teacher->subjectAssignments()->active()->whereJsonContains('permissions', 'marks.enter')->get()
            : collect();
        $assignedClassIds = $teacherAssignments->pluck('institute_class_id')->unique();
        $assignedSubjectIds = $teacherAssignments->pluck('institute_subject_id')->unique();
        $assignedYearIds = $teacherAssignments->pluck('academic_year_id')->unique();

        $exams = Exam::whereHas('instituteClass', function($query) use ($instituteId) {
            $query->where('institute_id', $instituteId);
        })
        ->when($isTeacher, fn ($query) => $query
            ->whereIn('academic_year_id', $assignedYearIds)
            ->whereHas('examSubjects', fn ($subjects) => $subjects->whereIn('institute_subject_id', $assignedSubjectIds))
            ->where(function ($query) use ($assignedClassIds) {
                $query->whereIn('institute_class_id', $assignedClassIds)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereIn('institute_classes.id', $assignedClassIds));
            }))
        ->with([
            'instituteClass:id,name',
            'academicYear:id,session',
            'examSubjects:id,exam_id,institute_subject_id,institute_class_id,full_marks,pass_marks',
            'classes:id,name',
        ])
        ->orderBy('start_date', 'desc')
        ->get()
        ->map(function($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'class_id' => $exam->institute_class_id,
                'academic_year_id' => $exam->academic_year_id,
                'class_name' => $exam->instituteClass->name ?? 'N/A',
                'academic_year' => $exam->academicYear->session ?? 'N/A',
                'subject_ids' => $exam->examSubjects->pluck('institute_subject_id'),
                'subject_ids_by_class' => $exam->examSubjects
                    ->groupBy('institute_class_id')
                    ->map(fn ($subjects) => $subjects->pluck('institute_subject_id')->values()),
                'subject_limits' => $exam->examSubjects->mapWithKeys(fn (ExamInstituteSubjec $examSubject) => [
                    $examSubject->institute_subject_id => [
                        'full_marks' => $examSubject->full_marks,
                        'pass_marks' => $examSubject->pass_marks,
                    ],
                ]),
                'subject_limits_by_class' => $exam->examSubjects
                    ->groupBy('institute_class_id')
                    ->map(fn ($subjects) => $subjects->mapWithKeys(fn (ExamInstituteSubjec $examSubject) => [
                        $examSubject->institute_subject_id => [
                            'full_marks' => $examSubject->full_marks,
                            'pass_marks' => $examSubject->pass_marks,
                        ],
                    ])),
                'class_ids' => $exam->classIds(),
                'class_names' => $exam->classes->isNotEmpty()
                    ? $exam->classes->pluck('name')
                    : collect([$exam->instituteClass->name])->filter()->values(),
            ];
        })
        ->when($isTeacher, fn ($exams) => $exams->filter(fn ($exam) => $teacherAssignments->contains(
            fn ($assignment) => (int) $assignment->academic_year_id === (int) $exam['academic_year_id']
                && in_array((int) $assignment->institute_class_id, array_map('intval', $exam['class_ids']), true)
                && $exam['subject_ids']->contains((int) $assignment->institute_subject_id)
        )));
        $classes = InstituteClass::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $assignedClassIds))
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $sections = Section::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($teacherAssignments) {
                $query->whereIn('institute_class_id', $teacherAssignments->whereNull('section_id')->pluck('institute_class_id')->unique())
                    ->orWhereIn('id', $teacherAssignments->pluck('section_id')->filter()->unique());
            }))
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']);

        $classIdsBySubject = DB::table('institute_class_institute_subjects')
            ->join('institute_classes', 'institute_classes.id', '=', 'institute_class_institute_subjects.institute_class_id')
            ->where('institute_classes.institute_id', $instituteId)
            ->get(['institute_class_institute_subjects.institute_subject_id', 'institute_class_institute_subjects.institute_class_id'])
            ->groupBy('institute_subject_id')
            ->map(fn ($assignments) => $assignments->pluck('institute_class_id')->values());
        if ($isTeacher) {
            $classIdsBySubject = $teacherAssignments
                ->groupBy('institute_subject_id')
                ->map(fn ($assignments) => $assignments->pluck('institute_class_id')->unique()->values());
        }

        $subjects = InstituteSubject::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $assignedSubjectIds))
            ->ordered()
            ->get([
                'id', 'name', 'code', 'full_marks', 'pass_marks',
                'theory_marks', 'theory_pass_marks',
                'mcq_marks', 'mcq_pass_marks',
                'practical_marks', 'practical_pass_marks',
            ])
            ->map(fn (InstituteSubject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'full_marks' => $subject->full_marks,
                'pass_marks' => $subject->pass_marks,
                'theory_marks' => $subject->theory_marks,
                'theory_pass_marks' => $subject->theory_pass_marks,
                'mcq_marks' => $subject->mcq_marks,
                'mcq_pass_marks' => $subject->mcq_pass_marks,
                'practical_marks' => $subject->practical_marks,
                'practical_pass_marks' => $subject->practical_pass_marks,
                'class_ids' => $classIdsBySubject->get($subject->id, collect()),
            ]);

        $assignedStudentIds = $isTeacher ? $this->studentIdsForAssignments($teacherAssignments) : collect();
        $students = Student::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $assignedStudentIds))
            ->active()
            ->get(['id', 'name', 'student_id', 'roll_no', 'gender']);

        $teachers = Teacher::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->where('user_id', auth()->id()))
            ->active()
            ->get(['id', 'name']);

        return Inertia::render('Institute/Marks/Create', [
            'exams' => $exams,
            'grades' => app(MarkGradingService::class)->gradeRangesForInstitute((int) $instituteId),
            'classes' => $classes,
            'sections' => $sections,
            'subjects' => $subjects,
            'students' => $students,
            'teachers' => $teachers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $instituteId = (int) Auth::user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned before entering marks.');

        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'student_id' => 'required|exists:students,id',
            'institute_subject_id' => 'required|exists:institute_subjects,id',
            'institute_class_id' => 'required|exists:institute_classes,id',
            'section_id' => 'required|exists:sections,id',
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('teachers', 'id')->where('institute_id', $instituteId),
            ],
            'theory_marks' => 'nullable|numeric|min:0',
            'practical_marks' => 'nullable|numeric|min:0',
            'mcq_marks' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'is_absent' => 'boolean',
            'is_reexamine' => 'boolean'
        ]);

        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('classes')
            ->findOrFail($validated['exam_id']);
        abort_unless(in_array((int) $validated['institute_class_id'], $exam->classIds(), true), 404);

        $section = Section::query()
            ->where('institute_id', $instituteId)
            ->where('institute_class_id', $validated['institute_class_id'])
            ->findOrFail($validated['section_id']);
        $studentInfo = StudentAcademicInfo::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('institute_class_id', $validated['institute_class_id'])
            ->where('section_id', $section->id)
            ->where('student_id', $validated['student_id'])
            ->whereHas('student', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('status', 'active'))
            ->firstOrFail();
        $subject = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->whereHas('classes', fn ($query) => $query->whereKey($validated['institute_class_id']))
            ->findOrFail($validated['institute_subject_id']);
        $classSubjectType = DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $validated['institute_class_id'])
            ->where('institute_subject_id', $subject->id)
            ->value('type');
        $choiceField = [
            'elective' => 'elective_subject_id',
            'optional' => 'optional_subject_id',
            'additional' => 'additional_subject_id',
        ][$classSubjectType] ?? null;
        if ($choiceField && (int) $studentInfo->{$choiceField} !== (int) $subject->id) {
            throw ValidationException::withMessages([
                'student_id' => 'This student did not select the chosen subject.',
            ]);
        }
        $examSubject = ExamInstituteSubjec::query()
            ->where('exam_id', $exam->id)
            ->where('institute_subject_id', $subject->id)
            ->where('institute_class_id', $validated['institute_class_id'])
            ->firstOrFail();
        $isTeacher = strtolower((string) Auth::user()->usertype) === 'teacher';
        if ($isTeacher) {
            abort_unless(app(TeacherPermissionService::class)->hasSubjectPermission(
                Auth::user(),
                'marks.enter',
                (int) $validated['institute_class_id'],
                (int) $subject->id,
                (int) $section->id,
                (int) $exam->academic_year_id
            ), 403);
            $validated['teacher_id'] = (int) Auth::user()->teacher->id;
        }

        $isAbsent = (bool) ($validated['is_absent'] ?? false);
        $hasOtherComponents = (float) $subject->practical_marks > 0 || (float) $subject->mcq_marks > 0;
        $componentMaximums = [
            'theory_marks' => $hasOtherComponents
                ? min((float) ($subject->theory_marks ?? $subject->full_marks), (float) $examSubject->full_marks)
                : (float) $examSubject->full_marks,
            'practical_marks' => min((float) ($subject->practical_marks ?? 0), (float) $examSubject->full_marks),
            'mcq_marks' => min((float) ($subject->mcq_marks ?? 0), (float) $examSubject->full_marks),
        ];
        $componentErrors = [];
        foreach ($componentMaximums as $field => $maximum) {
            $score = $validated[$field] ?? null;
            if ($score !== null && $maximum !== null && $score > $maximum) {
                $componentErrors[$field] = "Marks cannot exceed {$maximum}.";
            }
            if ($score !== null && $field !== 'theory_marks' && (! $maximum || $maximum <= 0) && $score > 0) {
                $componentErrors[$field] = 'This mark component is not configured for the selected subject.';
            }
        }
        if ($componentErrors !== []) {
            throw ValidationException::withMessages($componentErrors);
        }

        $totalMarks = $isAbsent ? 0 : $this->calculateTotal(
            $validated['theory_marks'] ?? 0,
            $validated['practical_marks'] ?? 0,
            $validated['mcq_marks'] ?? 0
        );
        if ($totalMarks > $examSubject->full_marks) {
            throw ValidationException::withMessages([
                'theory_marks' => "Total marks cannot exceed the exam full marks of {$examSubject->full_marks}.",
            ]);
        }

        $gradeData = $this->calculateGrade(
            $totalMarks,
            $subject,
            $validated['theory_marks'] ?? 0,
            $validated['mcq_marks'] ?? 0,
            $validated['practical_marks'] ?? 0,
            $examSubject,
            $instituteId,
            $isAbsent
        );
        if ($isTeacher) {
            $existingMark = Mark::query()
                ->where('exam_id', $exam->id)
                ->where('student_id', $studentInfo->student_id)
                ->where('institute_subject_id', $subject->id)
                ->first();
            if ($existingMark) {
                abort_unless(app(TeacherPermissionService::class)->hasSubjectPermission(
                    Auth::user(),
                    'marks.update',
                    (int) $validated['institute_class_id'],
                    (int) $subject->id,
                    (int) $section->id,
                    (int) $exam->academic_year_id
                ), 403);
            }
        }

        $mark = Mark::updateOrCreate(
            [
                'exam_id' => $exam->id,
                'student_id' => $studentInfo->student_id,
                'institute_subject_id' => $subject->id,
            ],
            [
                'institute_class_id' => $studentInfo->institute_class_id,
                'section_id' => $studentInfo->section_id,
                'teacher_id' => $validated['teacher_id'],
                'theory_marks' => $validated['theory_marks'] ?? null,
                'practical_marks' => $validated['practical_marks'] ?? null,
                'mcq_marks' => $validated['mcq_marks'] ?? null,
                'total_marks' => $totalMarks,
                'grade_point' => $gradeData['point'],
                'grade' => $isAbsent ? 'F' : $gradeData['grade'],
                'remarks' => $validated['remarks'] ?? null,
                'is_absent' => $isAbsent,
                'is_reexamine' => $validated['is_reexamine'] ?? false,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Marks saved successfully.',
                'mark' => $mark->only(['id', 'total_marks', 'grade', 'grade_point']),
            ]);
        }

        return redirect()->route('marks.entry')->with('success', 'Marks saved successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Mark $mark)
    {
        $mark = $this->instituteMarks(Auth::user()->institute_id)->findOrFail($mark->id);
        $this->authorizeTeacherMark($mark, 'marks.view');
        $mark->load(['exam', 'student', 'instituteSubject', 'instituteClass', 'section', 'teacher']);
        $examSubject = ExamInstituteSubjec::query()
            ->where('exam_id', $mark->exam_id)
            ->where('institute_subject_id', $mark->institute_subject_id)
            ->where('institute_class_id', $mark->institute_class_id)
            ->firstOrFail();

        return Inertia::render('Institute/Marks/Show', [
            'mark' => [
                'id' => $mark->id,
                'exam_name' => $mark->exam->name ?? 'N/A',
                'student_name' => $mark->student->name ?? 'N/A',
                'student_id' => $mark->student->student_id ?? 'N/A',
                'roll_no' => $mark->student->roll_no ?? 'N/A',
                'subject_name' => $mark->instituteSubject->name ?? 'N/A',
                'class_name' => $mark->instituteClass->name ?? 'N/A',
                'section_name' => $mark->section->name ?? 'N/A',
                'teacher_name' => $mark->teacher->name ?? 'N/A',
                'theory_marks' => $mark->theory_marks,
                'practical_marks' => $mark->practical_marks,
                'mcq_marks' => $mark->mcq_marks,
                'total_marks' => $mark->total_marks,
                'full_marks' => $examSubject->full_marks,
                'pass_marks' => $examSubject->pass_marks,
                'grade' => $mark->grade,
                'grade_label' => $mark->grade_label,
                'remarks' => $mark->remarks,
                'is_absent' => $mark->is_absent,
                'is_reexamine' => $mark->is_reexamine,
                'status_label' => $mark->status_label,
                'status_badge_class' => $mark->status_badge_class,
                'created_at' => $mark->created_at,
                'updated_at' => $mark->updated_at
            ]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Mark $mark)
    {
        $instituteId = Auth::user()->institute_id;
        $isTeacher = strtolower((string) Auth::user()->usertype) === 'teacher';
        $teacherAssignments = $isTeacher
            ? Auth::user()->teacher->subjectAssignments()->active()->whereJsonContains('permissions', 'marks.update')->get()
            : collect();
        $assignedClassIds = $teacherAssignments->pluck('institute_class_id')->unique();
        $assignedSubjectIds = $teacherAssignments->pluck('institute_subject_id')->unique();
        $assignedYearIds = $teacherAssignments->pluck('academic_year_id')->unique();
        $mark = $this->instituteMarks($instituteId)->findOrFail($mark->id);
        $this->authorizeTeacherMark($mark, 'marks.update');

        $exams = Exam::whereHas('instituteClass', function($query) use ($instituteId) {
            $query->where('institute_id', $instituteId);
        })
        ->when($isTeacher, fn ($query) => $query
            ->whereIn('academic_year_id', $assignedYearIds)
            ->whereHas('examSubjects', fn ($subjects) => $subjects->whereIn('institute_subject_id', $assignedSubjectIds))
            ->where(function ($query) use ($assignedClassIds) {
                $query->whereIn('institute_class_id', $assignedClassIds)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereIn('institute_classes.id', $assignedClassIds));
            }))
        ->with([
            'instituteClass',
            'academicYear',
            'examSubjects:id,exam_id,institute_subject_id,institute_class_id,full_marks,pass_marks',
        ])
        ->orderBy('start_date', 'desc')
        ->get()
        ->map(function($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'academic_year_id' => $exam->academic_year_id,
                'subject_ids' => $exam->examSubjects->pluck('institute_subject_id'),
                'subject_ids_by_class' => $exam->examSubjects
                    ->groupBy('institute_class_id')
                    ->map(fn ($subjects) => $subjects->pluck('institute_subject_id')->values()),
                'class_ids' => $exam->classIds(),
                'class_name' => $exam->instituteClass->name ?? 'N/A',
                'academic_year' => $exam->academicYear->session ?? 'N/A',
                'subject_limits' => $exam->examSubjects->mapWithKeys(fn (ExamInstituteSubjec $examSubject) => [
                    $examSubject->institute_subject_id => [
                        'full_marks' => $examSubject->full_marks,
                        'pass_marks' => $examSubject->pass_marks,
                    ],
                ]),
                'subject_limits_by_class' => $exam->examSubjects
                    ->groupBy('institute_class_id')
                    ->map(fn ($subjects) => $subjects->mapWithKeys(fn (ExamInstituteSubjec $examSubject) => [
                        $examSubject->institute_subject_id => [
                            'full_marks' => $examSubject->full_marks,
                            'pass_marks' => $examSubject->pass_marks,
                        ],
                    ])),
            ];
        })
        ->when($isTeacher, fn ($exams) => $exams->filter(fn ($exam) => $teacherAssignments->contains(
            fn ($assignment) => (int) $assignment->academic_year_id === (int) $exam['academic_year_id']
                && in_array((int) $assignment->institute_class_id, array_map('intval', $exam['class_ids']), true)
                && $exam['subject_ids']->contains((int) $assignment->institute_subject_id)
        )))
        ->map(fn ($exam) => collect($exam)->except(['academic_year_id', 'subject_ids', 'class_ids'])->all());
        $examSubject = ExamInstituteSubjec::query()
            ->where('exam_id', $mark->exam_id)
            ->where('institute_subject_id', $mark->institute_subject_id)
            ->where('institute_class_id', $mark->institute_class_id)
            ->firstOrFail();

        $classes = InstituteClass::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $assignedClassIds))
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $sections = Section::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->where(function ($query) use ($teacherAssignments) {
                $query->whereIn('institute_class_id', $teacherAssignments->whereNull('section_id')->pluck('institute_class_id')->unique())
                    ->orWhereIn('id', $teacherAssignments->pluck('section_id')->filter()->unique());
            }))
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']);

        $subjects = InstituteSubject::forInstitute($instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $assignedSubjectIds))
            ->ordered()
            ->get([
                'id', 'name', 'code', 'full_marks', 'pass_marks',
                'theory_marks', 'theory_pass_marks',
                'mcq_marks', 'mcq_pass_marks',
                'practical_marks', 'practical_pass_marks',
            ]);

        $students = Student::forInstitute($instituteId)
            ->active()
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $this->studentIdsForAssignments($teacherAssignments)))
            ->get(['id', 'name', 'student_id', 'roll_no', 'gender']);

        $teachers = Teacher::forInstitute($instituteId)
            ->active()
            ->when($isTeacher, fn ($query) => $query->where('user_id', auth()->id()))
            ->get(['id', 'name']);

        return Inertia::render('Institute/Marks/Edit', [
            'mark' => [
                'id' => $mark->id,
                'exam_id' => $mark->exam_id,
                'student_id' => $mark->student_id,
                'institute_subject_id' => $mark->institute_subject_id,
                'institute_class_id' => $mark->institute_class_id,
                'section_id' => $mark->section_id,
                'teacher_id' => $mark->teacher_id,
                'theory_marks' => $mark->theory_marks,
                'practical_marks' => $mark->practical_marks,
                'mcq_marks' => $mark->mcq_marks,
                'total_marks' => $mark->total_marks,
                'full_marks' => $examSubject->full_marks,
                'pass_marks' => $examSubject->pass_marks,
                'grade' => $mark->grade,
                'grade_point' => $mark->grade_point,
                'remarks' => $mark->remarks,
                'is_absent' => $mark->is_absent,
                'is_reexamine' => $mark->is_reexamine
            ],
            'exams' => $exams,
            'classes' => $classes,
            'sections' => $sections,
            'subjects' => $subjects,
            'students' => $students,
            'teachers' => $teachers,
            'grades' => app(MarkGradingService::class)->gradeRangesForInstitute((int) $instituteId),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Mark $mark)
    {
        $mark = $this->instituteMarks(Auth::user()->institute_id)->findOrFail($mark->id);

        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|exists:exams,id',
            'student_id' => 'required|exists:students,id',
            'institute_subject_id' => 'required|exists:institute_subjects,id',
            'institute_class_id' => 'required|exists:institute_classes,id',
            'section_id' => 'required|exists:sections,id',
            'teacher_id' => 'required|exists:teachers,id',
            'theory_marks' => 'nullable|numeric|min:0',
            'practical_marks' => 'nullable|numeric|min:0',
            'mcq_marks' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'is_absent' => 'boolean',
            'is_reexamine' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $instituteId = (int) Auth::user()->institute_id;
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('classes')
            ->findOrFail($request->exam_id);
        abort_unless(in_array((int) $request->institute_class_id, $exam->classIds(), true), 404);
        $studentInfo = StudentAcademicInfo::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('institute_class_id', $request->institute_class_id)
            ->where('section_id', $request->section_id)
            ->where('student_id', $request->student_id)
            ->whereHas('student', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('status', 'active'))
            ->firstOrFail();
        $subject = InstituteSubject::query()
            ->where('institute_id', $instituteId)
            ->whereHas('classes', fn ($query) => $query->whereKey($request->institute_class_id))
            ->findOrFail($request->institute_subject_id);
        if (strtolower((string) Auth::user()->usertype) === 'teacher') {
            abort_unless(app(TeacherPermissionService::class)->hasSubjectPermission(
                Auth::user(),
                'marks.update',
                (int) $request->institute_class_id,
                (int) $subject->id,
                (int) $request->section_id,
                (int) $exam->academic_year_id
            ), 403);
            $request->merge(['teacher_id' => Auth::user()->teacher->id]);
        }
        $examSubject = ExamInstituteSubjec::query()
            ->where('exam_id', $exam->id)
            ->where('institute_subject_id', $subject->id)
            ->where('institute_class_id', $request->institute_class_id)
            ->firstOrFail();
        $classSubjectType = DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $request->institute_class_id)
            ->where('institute_subject_id', $subject->id)
            ->value('type');
        $choiceField = [
            'elective' => 'elective_subject_id',
            'optional' => 'optional_subject_id',
            'additional' => 'additional_subject_id',
        ][$classSubjectType] ?? null;
        if ($choiceField && (int) $studentInfo->{$choiceField} !== (int) $subject->id) {
            throw ValidationException::withMessages([
                'student_id' => 'This student did not select the chosen subject.',
            ]);
        }

        // Check if mark already exists (excluding current)
        $exists = Mark::where('exam_id', $request->exam_id)
            ->where('student_id', $request->student_id)
            ->where('institute_subject_id', $request->institute_subject_id)
            ->where('id', '!=', $mark->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'Marks already entered for this student and subject.');
        }

        $hasOtherComponents = (float) $subject->practical_marks > 0 || (float) $subject->mcq_marks > 0;
        $componentMaximums = [
            'theory_marks' => $hasOtherComponents
                ? min((float) ($subject->theory_marks ?? $subject->full_marks), (float) $examSubject->full_marks)
                : (float) $examSubject->full_marks,
            'practical_marks' => min((float) ($subject->practical_marks ?? 0), (float) $examSubject->full_marks),
            'mcq_marks' => min((float) ($subject->mcq_marks ?? 0), (float) $examSubject->full_marks),
        ];
        $isAbsent = (bool) $request->is_absent;
        if (! $isAbsent) {
            foreach ($componentMaximums as $field => $maximum) {
                $score = $request->input($field);
                if ($score !== null && $maximum !== null && $score > $maximum) {
                    throw ValidationException::withMessages([
                        $field => "Marks cannot exceed {$maximum}.",
                    ]);
                }
            }
        }

        $totalMarks = $isAbsent ? 0 : $this->calculateTotal(
            $request->theory_marks,
            $request->practical_marks,
            $request->mcq_marks
        );
        if ($totalMarks > $examSubject->full_marks) {
            throw ValidationException::withMessages([
                'theory_marks' => "Total marks cannot exceed the exam full marks of {$examSubject->full_marks}.",
            ]);
        }

        $gradeData = $this->calculateGrade(
            $totalMarks,
            $subject,
            $request->theory_marks,
            $request->mcq_marks,
            $request->practical_marks,
            $examSubject,
            $instituteId,
            $isAbsent
        );

        $mark->update([
            'exam_id' => $request->exam_id,
            'student_id' => $request->student_id,
            'institute_subject_id' => $request->institute_subject_id,
            'institute_class_id' => $request->institute_class_id,
            'section_id' => $request->section_id,
            'teacher_id' => $request->teacher_id,
            'theory_marks' => $request->theory_marks,
            'practical_marks' => $request->practical_marks,
            'mcq_marks' => $request->mcq_marks,
            'total_marks' => $totalMarks,
            'grade_point' => $gradeData['point'],
            'grade' => $gradeData['grade'],
            'remarks' => $request->remarks,
            'is_absent' => $request->is_absent ?? false,
            'is_reexamine' => $request->is_reexamine ?? false
        ]);

        return redirect()->route('marks.index')
            ->with('success', 'Marks updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mark $mark)
    {
        $mark = $this->instituteMarks(Auth::user()->institute_id)->findOrFail($mark->id);
        $this->authorizeTeacherMark($mark, 'marks.delete');
        $mark->delete();

        return redirect()->route('marks.index')
            ->with('success', 'Marks deleted successfully!');
    }

    /**
     * Get students by class and section (API for Inertia).
     */
    /**
     * Get students by class and section with existing marks.
     */
     public function getStudents(Request $request)
    {
        $instituteId = auth()->user()->institute_id;

         $validated = $request->validate([
             'class_id' => ['required', 'integer'],
             'section_id' => ['required', 'integer'],
             'exam_id' => ['required', 'integer'],
             'subject_id' => ['required', 'integer'],
         ]);
         $exam = Exam::query()
             ->where('id', $validated['exam_id'])
             ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
             ->with('classes')
             ->firstOrFail();
         abort_unless(in_array((int) $validated['class_id'], $exam->classIds(), true), 404);
         $section = Section::query()
             ->where('id', $validated['section_id'])
             ->where('institute_id', $instituteId)
             ->where('institute_class_id', $validated['class_id'])
             ->firstOrFail();
         $subject = InstituteSubject::query()
             ->where('id', $validated['subject_id'])
             ->where('institute_id', $instituteId)
             ->whereHas('classes', fn ($query) => $query->where('institute_classes.id', $validated['class_id']))
             ->firstOrFail();
         if (strtolower((string) Auth::user()->usertype) === 'teacher') {
             abort_unless(app(TeacherPermissionService::class)->hasSubjectPermission(
                 Auth::user(),
                 'marks.enter',
                 (int) $validated['class_id'],
                 (int) $subject->id,
                 (int) $validated['section_id'],
                 (int) $exam->academic_year_id
             ), 403);
         }
         $classSubjectType = DB::table('institute_class_institute_subjects')
             ->where('institute_class_id', $validated['class_id'])
             ->where('institute_subject_id', $subject->id)
             ->value('type');
         $choiceField = [
             'elective' => 'elective_subject_id',
             'optional' => 'optional_subject_id',
             'additional' => 'additional_subject_id',
         ][$classSubjectType] ?? null;
         $examSubject = ExamInstituteSubjec::query()
             ->where('exam_id', $exam->id)
             ->where('institute_subject_id', $subject->id)
             ->where('institute_class_id', $validated['class_id'])
             ->firstOrFail();

         // Get students
         $students = StudentAcademicInfo::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('institute_class_id', $validated['class_id'])
            ->where('section_id', $section->id)
            ->when($choiceField, fn ($query) => $query->where($choiceField, $subject->id))
            ->whereHas('student', fn ($query) => $query
                ->where('institute_id', $instituteId)
                ->where('status', 'active'))
            ->with('student:id,name,roll_no,gender,student_id')
            ->get(['student_id', 'roll_number', 'elective_subject_id'])
            ->sort(function (StudentAcademicInfo $first, StudentAcademicInfo $second) {
                $firstRoll = (string) ($first->roll_number ?? $first->student?->roll_no ?? '');
                $secondRoll = (string) ($second->roll_number ?? $second->student?->roll_no ?? '');

                return strnatcasecmp($firstRoll, $secondRoll);
            })
            ->values()
            ->map(function($studentInfo) {
                $student = $studentInfo->student;

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                    'roll_no' => $studentInfo->roll_number ?? $student->roll_no,
                    'gender' => $student->gender ?? 'male',
                ];
            });

        // Get existing marks
        $existingMarks = Mark::where('exam_id', $request->exam_id)
            ->where('institute_subject_id', $request->subject_id)
            ->where('institute_class_id', $request->class_id)
            ->where('section_id', $request->section_id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get(['id', 'student_id', 'theory_marks', 'practical_marks', 'mcq_marks', 'total_marks', 'grade', 'is_absent', 'is_reexamine']);

        return response()->json([
            'success' => true,
            'students' => $students,
            'existing_marks' => $existingMarks,
            'full_marks' => $examSubject->full_marks,
            'pass_marks' => $examSubject->pass_marks,
            'component_pass_marks' => [
                'theory_pass_marks' => $subject->theory_pass_marks,
                'mcq_pass_marks' => $subject->mcq_pass_marks,
                'practical_pass_marks' => $subject->practical_pass_marks,
            ],
        ]);
    }

    private function instituteMarks(int $instituteId)
    {
        return Mark::query()
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->whereHas('instituteSubject', fn ($query) => $query->where('institute_id', $instituteId))
            ->whereHas('teacher', fn ($query) => $query->where('institute_id', $instituteId));
    }

    private function authorizeTeacherMark(Mark $mark, string $permission): void
    {
        if (strtolower((string) Auth::user()->usertype) !== 'teacher') {
            return;
        }

        abort_unless(app(TeacherPermissionService::class)->hasSubjectPermission(
            Auth::user(),
            $permission,
            (int) $mark->institute_class_id,
            (int) $mark->institute_subject_id,
            (int) $mark->section_id,
            (int) $mark->exam()->value('academic_year_id')
        ), 403);
    }

    private function studentIdsForAssignments(Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return collect();
        }

        return StudentAcademicInfo::query()
            ->where(function ($query) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $query->orWhere(function ($match) use ($assignment) {
                        $match->where('institute_class_id', $assignment->institute_class_id)
                            ->where('academic_year_id', $assignment->academic_year_id);
                        if ($assignment->section_id !== null) {
                            $match->where('section_id', $assignment->section_id);
                        }
                    });
                }
            })
            ->pluck('student_id');
    }

    private function calculateTotal($theory, $practical, $mcq)
    {
        $total = 0;
        if ($theory) $total += $theory;
        if ($practical) $total += $practical;
        if ($mcq) $total += $mcq;
        return $total;
    }

    private function calculateGrade(
        $marks,
        InstituteSubject $subject,
        $theoryMarks,
        $mcqMarks,
        $practicalMarks,
        ExamInstituteSubjec $examSubject,
        int $instituteId,
        bool $isAbsent = false
    )
    {
        $grading = app(MarkGradingService::class);
        $failsComponents = (float) $subject->practical_marks > 0 || (float) $subject->mcq_marks > 0
            ? $grading->failsMarkComponents($subject, [
                'theory_marks' => $theoryMarks,
                'mcq_marks' => $mcqMarks,
                'practical_marks' => $practicalMarks,
            ], (float) $examSubject->full_marks)
            : false;

        return $grading->gradeForExam(
            $isAbsent ? 0 : $marks,
            $examSubject->full_marks,
            $examSubject->pass_marks,
            $grading->gradeRangesForInstitute($instituteId),
            $isAbsent || $failsComponents
        );
    }
}