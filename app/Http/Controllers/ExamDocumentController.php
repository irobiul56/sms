<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Mark;
use App\Models\Result;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Services\MarkGradingService;
use App\Services\TeacherPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExamDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $instituteId = $this->instituteId($request);
        $isTeacher = strtolower((string) $request->user()->usertype) === 'teacher';
        $classPermissionKeys = [
            'admit_cards.print',
            'seat_plan.print',
            'marksheets.print',
            'tabulation_sheet.print',
        ];
        $permissionService = app(TeacherPermissionService::class);
        $classAssignments = collect($classPermissionKeys)
            ->flatMap(fn ($permission) => $permissionService->classAssignmentsForPermission(
                $request->user(),
                $permission
            ));
        $authorizedClassIds = $classAssignments->pluck('institute_class_id')->unique()->values();
        $classes = InstituteClass::query()
            ->where('institute_id', $instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $authorizedClassIds))
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name', 'group']);
        $classId = $classes->contains('id', (int) $request->query('class_id'))
            ? (string) $request->query('class_id')
            : '';
        $documentPermission = match ($request->query('document_type')) {
            'admit-card' => 'admit_cards.print',
            'seat-plan' => 'seat_plan.print',
            'marksheet' => 'marksheets.print',
            default => null,
        };
        $exams = $this->examsForInstitute($instituteId)
            ->when($classId !== '', fn ($query) => $query->where(function ($query) use ($classId) {
                $query->where('institute_class_id', $classId)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereKey($classId));
            }))
            ->with(['instituteClass:id,name', 'classes:id,name'])
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'institute_class_id', 'academic_year_id']);
        if ($isTeacher) {
            $exams = $exams->filter(
                fn (Exam $item) => collect($item->classIds())->intersect($authorizedClassIds)->isNotEmpty()
            )->values();
        }
        $exam = $exams->firstWhere('id', (int) $request->query('exam_id'));
        if ($exam && $classId === '') {
            $candidateClassIds = $isTeacher
                ? collect($exam->classIds())->intersect($authorizedClassIds)
                : collect($exam->classIds());
            if ($isTeacher && $documentPermission) {
                $candidateClassIds = $candidateClassIds->filter(fn ($candidateId) => $permissionService
                    ->hasAnyClassPermissionForClass(
                        $request->user(),
                        $documentPermission,
                        (int) $candidateId,
                        (int) $exam->academic_year_id
                    ));
            }
            $classId = (string) ($candidateClassIds->first() ?? '');
        }
        if ($exam && (
            ! in_array((int) $classId, $exam->classIds(), true)
            || ($isTeacher && ! $authorizedClassIds->contains((int) $classId))
        )) {
            $exam = null;
        }
        $sections = collect();
        $students = collect();
        $seatPlanClasses = collect();
        $classRoster = collect();
        $resultsByStudent = collect();
        $sectionId = '';
        $rollNumber = trim((string) $request->query('roll_number', ''));

        if ($exam) {
            $sections = Section::query()
                ->where('institute_id', $instituteId)
                ->where('institute_class_id', $classId)
                ->when($isTeacher, function ($query) use ($classAssignments, $classId, $exam) {
                    $scopes = $classAssignments
                        ->where('institute_class_id', (int) $classId)
                        ->where('academic_year_id', (int) $exam->academic_year_id);
                    $sectionIds = $scopes->pluck('section_id')->filter()->unique();
                    if ($scopes->contains(fn ($assignment) => $assignment->section_id === null)) {
                        return;
                    }

                    $query->whereIn('id', $sectionIds);
                })
                ->orderBy('name')
                ->get(['sections.id', 'sections.name']);
            $sectionId = $sections->contains('id', (int) $request->query('section_id'))
                ? (string) $request->query('section_id')
                : '';
            $classRoster = $this->examRoster($exam, $instituteId, $classId);
            if ($isTeacher) {
                $scopes = $classAssignments
                    ->where('institute_class_id', (int) $classId)
                    ->where('academic_year_id', (int) $exam->academic_year_id);
                if (! $scopes->contains(fn ($assignment) => $assignment->section_id === null)) {
                    $classRoster = $classRoster
                        ->whereIn('section_id', $scopes->pluck('section_id')->filter())
                        ->values();
                }
            }
            $roster = $sectionId === ''
                ? $classRoster
                : $classRoster->where('section_id', (int) $sectionId)->values();
            if ($rollNumber !== '') {
                $roster = $roster->filter(
                    fn (StudentAcademicInfo $info) => str_contains(
                        mb_strtolower((string) $info->roll_number),
                        mb_strtolower($rollNumber)
                    )
                )->values();
            }
            $seatPlanAssignments = $isTeacher
                ? $permissionService->classAssignmentsForPermission(
                    $request->user(),
                    'seat_plan.print',
                    (int) $exam->academic_year_id
                )
                : collect();
            $classCountsQuery = StudentAcademicInfo::query()
                ->where('academic_year_id', $exam->academic_year_id)
                ->whereIn('institute_class_id', $exam->classIds())
                ->when($sectionId !== '', fn ($query) => $query->where(function ($query) use ($classId, $sectionId) {
                    $query->where('institute_class_id', '!=', $classId)
                        ->orWhere('section_id', $sectionId);
                }))
                ->when($rollNumber !== '', fn ($query) => $query->where('roll_number', 'like', "%{$rollNumber}%"))
                ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
                ->selectRaw('institute_class_id, COUNT(DISTINCT student_id) as student_count')
                ->groupBy('institute_class_id');
            if ($isTeacher) {
                $classCountsQuery->where(function ($query) use ($seatPlanAssignments) {
                    foreach ($seatPlanAssignments as $assignment) {
                        $query->orWhere(function ($scope) use ($assignment) {
                            $scope->where('institute_class_id', $assignment->institute_class_id);
                            if ($assignment->section_id !== null) {
                                $scope->where('section_id', $assignment->section_id);
                            }
                        });
                    }
                });
            }
            $classCounts = $classCountsQuery->get()->keyBy('institute_class_id');
            $seatPlanClassIds = $isTeacher
                ? $seatPlanAssignments->pluck('institute_class_id')->unique()
                : collect($exam->classIds());
            $seatPlanClasses = $classes
                ->whereIn('id', $exam->classIds())
                ->whereIn('id', $seatPlanClassIds)
                ->map(fn (InstituteClass $class) => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'bn_name' => $class->bn_name,
                    'student_count' => (int) ($classCounts->get($class->id)?->student_count ?? 0),
                ])
                ->values();
            $resultsByStudent = Result::query()
                ->where('exam_id', $exam->id)
                ->whereIn('student_id', $classRoster->pluck('student_id'))
                ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
                ->get(['student_id', 'is_published'])
                ->keyBy('student_id');
            $students = $roster
                ->map(fn (StudentAcademicInfo $info) => [
                    'id' => $info->student_id,
                    'name' => $info->student->name,
                    'student_id' => $info->student->student_id,
                    'section_id' => $info->section_id,
                    'roll_number' => $info->roll_number,
                    'section_name' => $info->section?->name,
                    'has_result' => $resultsByStudent->has($info->student_id),
                    'has_published_result' => (bool) $resultsByStudent->get($info->student_id)?->is_published,
                ]);
        }

        return Inertia::render('Institute/ExamDocuments/Index', [
            'classes' => $classes,
            'exams' => $exams->map(fn (Exam $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'class_ids' => $isTeacher
                    ? collect($item->classIds())->intersect($authorizedClassIds)->values()
                    : $item->classIds(),
                'class_names' => $classes
                    ->whereIn('id', $isTeacher
                        ? collect($item->classIds())->intersect($authorizedClassIds)
                        : $item->classIds())
                    ->pluck('name')
                    ->values(),
            ]),
            'selectedExam' => $exam ? [
                'id' => $exam->id,
                'name' => $exam->name,
                'class_id' => (int) $classId,
                'class_name' => $classes->firstWhere('id', (int) $classId)?->name,
            ] : null,
            'seatPlanClasses' => $seatPlanClasses,
            'sections' => $sections,
            'students' => $students,
            'classStudentCount' => $classRoster->count(),
            'classHasAllResults' => $classRoster->isNotEmpty()
                && $classRoster->every(fn (StudentAcademicInfo $info) => $resultsByStudent->has($info->student_id)),
            'classHasAllPublishedResults' => $classRoster->isNotEmpty()
                && $classRoster->every(fn (StudentAcademicInfo $info) => (bool) $resultsByStudent->get($info->student_id)?->is_published),
            'documentPermissions' => collect($classPermissionKeys)->mapWithKeys(fn ($permission) => [
                $permission => ! $isTeacher || (
                    $exam && $classId !== '' && $permissionService->hasClassPermission(
                        $request->user(),
                        $permission,
                        (int) $classId,
                        $sectionId !== '' ? (int) $sectionId : null,
                        (int) $exam->academic_year_id
                    )
                ),
            ]),
            'filters' => [
                'class_id' => $classId,
                'exam_id' => $exam ? (string) $exam->id : '',
                'section_id' => $sectionId,
                'roll_number' => $rollNumber,
                'document_type' => in_array($request->query('document_type'), ['marksheet', 'admit-card', 'seat-plan'], true)
                    ? $request->query('document_type')
                    : '',
            ],
        ]);
    }

    public function marksheet(Request $request, int $exam, int $student): Response
    {
        $instituteId = $this->instituteId($request);
        $exam = $this->findExam($exam, $instituteId);
        $studentInfo = $this->findStudentInfo(
            $exam,
            $student,
            $instituteId,
            $request->query('class_id') ? (int) $request->query('class_id') : null
        );
        $result = Result::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student)
            ->where('institute_class_id', $studentInfo->institute_class_id)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->firstOrFail();
        abort_unless($result->is_published, 404);
        $subjects = $this->subjectsForStudent(
            $this->examSubjects($exam, $instituteId, $studentInfo->institute_class_id),
            $studentInfo
        );
        $marks = Mark::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student)
            ->whereIn('institute_subject_id', $subjects->pluck('institute_subject_id'))
            ->get()
            ->keyBy('institute_subject_id');
        $resultData = $this->resultData($result);
        $summary = $this->resultSummaryForStudent($subjects, $marks, $studentInfo, $instituteId);

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'marksheet',
            'institute' => $this->instituteData($request),
            'exam' => $this->examData($exam, $studentInfo->institute_class_id),
            'student' => $this->studentData($studentInfo),
            'subjects' => $this->marksheetSubjects($subjects, $marks),
            'result' => $summary ? array_replace($resultData, $summary) : $resultData,
            'publicMarksheetUrl' => $this->publicMarksheetUrl($request, $exam->id, $student),
        ]);
    }

    public function publicMarksheet(Request $request, int $exam, int $student): Response
    {
        $instituteId = (int) Student::query()->whereKey($student)->value('institute_id');
        abort_if(! $instituteId, 404);
        $exam = $this->findExam($exam, $instituteId);
        $studentInfo = $this->findStudentInfo($exam, $student, $instituteId);
        $result = Result::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student)
            ->where('institute_class_id', $studentInfo->institute_class_id)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->firstOrFail();
        abort_unless($result->is_published, 404);

        $subjects = $this->subjectsForStudent(
            $this->examSubjects($exam, $instituteId, $studentInfo->institute_class_id),
            $studentInfo
        );
        $marks = Mark::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student)
            ->whereIn('institute_subject_id', $subjects->pluck('institute_subject_id'))
            ->get()
            ->keyBy('institute_subject_id');
        $resultData = $this->resultData($result);
        $summary = $this->resultSummaryForStudent($subjects, $marks, $studentInfo, $instituteId);

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'marksheet',
            'isPublicMarksheet' => true,
            'institute' => $this->instituteDataForId($instituteId),
            'exam' => $this->examData($exam, $studentInfo->institute_class_id),
            'student' => $this->studentData($studentInfo),
            'subjects' => $this->marksheetSubjects($subjects, $marks),
            'result' => $summary ? array_replace($resultData, $summary) : $resultData,
            'publicMarksheetUrl' => $this->publicMarksheetUrl($request, $exam->id, $student),
        ]);
    }

    public function classMarksheets(Request $request, int $exam): Response
    {
        $instituteId = $this->instituteId($request);
        $exam = $this->findExam($exam, $instituteId);
        $roster = $this->filteredExamRoster($request, $exam, $instituteId);
        $classId = $this->selectedClassId($request, $exam);
        $results = Result::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $roster->pluck('student_id'))
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->get()
            ->keyBy('student_id');

        abort_if(
            $roster->isEmpty()
                || $results->count() !== $roster->count()
                || $results->contains(fn (Result $result) => ! $result->is_published),
            422,
            'Publish results for every student in the selected list before printing marksheets.'
        );

        $subjects = $this->examSubjects($exam, $instituteId, $classId);
        $choiceSubjectIds = $this->choiceSubjectIdsForClass($classId);
        $meritPositions = Result::query()
            ->where('exam_id', $exam->id)
            ->where('institute_class_id', $classId)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->get(['student_id', 'total_marks', 'total_full_marks', 'final_grade', 'position'])
            ->sort(function (Result $left, Result $right) {
                $leftFailed = strtoupper((string) $left->final_grade) === 'F';
                $rightFailed = strtoupper((string) $right->final_grade) === 'F';
                $leftPercentage = (float) $left->total_full_marks > 0
                    ? (float) $left->total_marks / (float) $left->total_full_marks
                    : 0;
                $rightPercentage = (float) $right->total_full_marks > 0
                    ? (float) $right->total_marks / (float) $right->total_full_marks
                    : 0;

                return ($leftFailed <=> $rightFailed)
                    ?: ($rightPercentage <=> $leftPercentage)
                    ?: ((float) $right->total_marks <=> (float) $left->total_marks)
                    ?: ((int) $left->position <=> (int) $right->position)
                    ?: ((int) $left->student_id <=> (int) $right->student_id);
            })
            ->values()
            ->mapWithKeys(fn (Result $result, int $index) => [$result->student_id => $index + 1]);
        $marksByStudent = Mark::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $roster->pluck('student_id'))
            ->whereIn('institute_subject_id', $subjects->pluck('institute_subject_id'))
            ->get()
            ->groupBy('student_id');
        $documents = $roster->map(function (StudentAcademicInfo $info) use (
            $request,
            $results,
            $meritPositions,
            $subjects,
            $choiceSubjectIds,
            $marksByStudent,
            $instituteId
        ) {
            $studentSubjects = $this->subjectsForStudent($subjects, $info, $choiceSubjectIds);
            $studentMarks = $marksByStudent->get($info->student_id, collect());
            $result = $results->get($info->student_id);
            $resultData = $result ? $this->resultData($result) : [];
            $summary = $this->resultSummaryForStudent($studentSubjects, $studentMarks, $info, $instituteId);
            $resultData = $summary ? array_replace($resultData, $summary) : $resultData;
            $resultData['position'] = $meritPositions->get($info->student_id);

            return [
                'student' => $this->studentData($info),
                'result' => $resultData,
                'publicMarksheetUrl' => $this->publicMarksheetUrl($request, $result->exam_id, $result->student_id),
                'subjects' => $this->marksheetSubjects(
                    $studentSubjects,
                    $studentMarks
                ),
            ];
        })->sortBy(fn (array $document) => $document['result']['position'] ?? PHP_INT_MAX)->values();

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'marksheet',
            'isBulkMarksheets' => true,
            'institute' => $this->instituteData($request),
            'exam' => $this->examData($exam, $classId),
            'documents' => $documents,
        ]);
    }

    public function classTabulationSheet(Request $request, int $exam): Response
    {
        $instituteId = $this->instituteId($request);
        $exam = $this->findExam($exam, $instituteId);
        $roster = $this->filteredExamRoster($request, $exam, $instituteId);
        $classId = $this->selectedClassId($request, $exam);
        $subjects = $this->examSubjects($exam, $instituteId, $classId);
        $marksByStudent = Mark::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $roster->pluck('student_id'))
            ->whereIn('institute_subject_id', $subjects->pluck('institute_subject_id'))
            ->get()
            ->groupBy('student_id');
        $results = Result::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $roster->pluck('student_id'))
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->get()
            ->keyBy('student_id');
        $choiceSubjectIds = $this->choiceSubjectIdsForClass($classId);
        $rows = $roster->map(function (StudentAcademicInfo $info) use (
            $subjects,
            $choiceSubjectIds,
            $marksByStudent,
            $results,
            $instituteId
        ) {
            $studentSubjects = $this->subjectsForStudent($subjects, $info, $choiceSubjectIds);
            $studentMarks = $marksByStudent->get($info->student_id, collect());
            $result = $results->get($info->student_id);
            $resultData = $result ? $this->resultData($result) : [];
            $summary = $this->resultSummaryForStudent($studentSubjects, $studentMarks, $info, $instituteId);

            return [
                'student' => $this->studentData($info),
                'result' => $summary && $resultData
                    ? array_replace($resultData, $summary)
                    : ($summary ?: ($result ? $resultData : null)),
                'subjects' => $this->marksheetSubjects(
                    $subjects,
                    $studentMarks->filter(fn (Mark $mark) => $studentSubjects->contains(
                        'institute_subject_id',
                        $mark->institute_subject_id
                    ))
                ),
            ];
        });

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'tabulation',
            'institute' => $this->instituteData($request),
            'exam' => $this->examData($exam, $classId),
            'tabulation' => [
                'subjects' => $subjects->map(fn (ExamInstituteSubjec $examSubject) => [
                    'id' => $examSubject->institute_subject_id,
                    'name' => $examSubject->instituteSubject?->name ?? 'Subject',
                    'bn_name' => $examSubject->instituteSubject?->bn_name,
                    'full_marks' => $examSubject->full_marks,
                    'pass_marks' => $examSubject->pass_marks,
                ]),
                'rows' => $rows,
                'section_name' => $request->query('section_id')
                    ? $roster->first()?->section?->name
                    : 'All Sections',
            ],
        ]);
    }

    public function admitCard(Request $request, int $exam, int $student): Response
    {
        $instituteId = $this->instituteId($request);
        $exam = $this->findExam($exam, $instituteId);
        $studentInfo = $this->findStudentInfo(
            $exam,
            $student,
            $instituteId,
            $request->query('class_id') ? (int) $request->query('class_id') : null
        );
        $subjects = $this->subjectsForStudent(
            $this->examSubjects($exam, $instituteId, $studentInfo->institute_class_id),
            $studentInfo
        );

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'admit-card',
            'institute' => $this->instituteData($request),
            'exam' => $this->examData($exam, $studentInfo->institute_class_id),
            'student' => $this->studentData($studentInfo),
            'subjects' => $subjects->map(fn (ExamInstituteSubjec $examSubject) => [
                'name' => $examSubject->instituteSubject?->name ?? 'Subject',
                'bn_name' => $examSubject->instituteSubject?->bn_name,
                'exam_date' => $examSubject->exam_date?->format('d M Y'),
                'start_time' => $examSubject->start_time?->format('h:i A'),
                'end_time' => $examSubject->end_time?->format('h:i A'),
                'full_marks' => $examSubject->full_marks,
            ]),
        ]);
    }

    public function classAdmitCards(Request $request, int $exam): Response
    {
        $instituteId = $this->instituteId($request);
        $exam = $this->findExam($exam, $instituteId);
        $roster = $this->filteredExamRoster($request, $exam, $instituteId);
        $classId = $this->selectedClassId($request, $exam);
        $subjects = $this->examSubjects($exam, $instituteId, $classId);
        $choiceSubjectIds = $this->choiceSubjectIdsForClass($classId);
        $documents = $roster->map(fn (StudentAcademicInfo $info) => [
            'student' => $this->studentData($info),
            'subjects' => $this->subjectsForStudent($subjects, $info, $choiceSubjectIds)
                ->map(fn (ExamInstituteSubjec $examSubject) => [
                    'name' => $examSubject->instituteSubject?->name ?? 'Subject',
                    'bn_name' => $examSubject->instituteSubject?->bn_name,
                    'exam_date' => $examSubject->exam_date?->format('d M Y'),
                    'start_time' => $examSubject->start_time?->format('h:i A'),
                    'end_time' => $examSubject->end_time?->format('h:i A'),
                    'full_marks' => $examSubject->full_marks,
                ]),
        ]);

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'admit-card',
            'institute' => $this->instituteData($request),
            'exam' => $this->examData($exam, $classId),
            'documents' => $documents,
        ]);
    }

    public function seatPlan(Request $request, int $exam): Response
    {
        $instituteId = $this->instituteId($request);
        $exam = $this->findExam($exam, $instituteId);
        $classId = $this->selectedClassId($request, $exam);
        $sections = Section::query()
            ->where('institute_id', $instituteId)
            ->where('institute_class_id', $classId)
            ->get(['id', 'name', 'institute_class_id']);
        $sectionId = $request->query('section_id');

        if ($sectionId !== null && $sectionId !== '') {
            abort_unless($sections->contains('id', (int) $sectionId), 404);
        } else {
            $sectionId = '';
        }

        $validated = $request->validate([
            'seats_per_row' => ['nullable', 'integer', 'min:1', 'max:10'],
            'room_name' => ['nullable', 'string', 'max:80'],
            'class_ids' => ['sometimes', 'array', 'min:1', 'max:20'],
            'class_ids.*' => ['required', 'integer', 'distinct'],
            'rooms' => ['sometimes', 'array', 'min:1', 'max:20'],
            'rooms.*.room_name' => ['required_with:rooms', 'string', 'max:80'],
            'rooms.*.bench_rows' => ['required_with:rooms', 'integer', 'min:1', 'max:12'],
            'rooms.*.seats_per_bench' => ['required_with:rooms', 'integer', 'min:1', 'max:30'],
        ]);
        $seatsPerRow = (int) ($validated['seats_per_row'] ?? 5);

        $studentId = $request->query('student_id');
        $singleStudent = $studentId !== null && $studentId !== '';
        $roomPlans = collect();
        if (isset($validated['rooms'])) {
            $classIds = array_values(array_unique(array_map(
                'intval',
                $validated['class_ids'] ?? [$classId]
            )));
            $authorizedClassIds = InstituteClass::query()
                ->where('institute_id', $instituteId)
                ->whereIn('id', $exam->classIds())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            abort_unless(count(array_diff($classIds, $authorizedClassIds)) === 0, 404);

            $classRosterLists = collect($classIds)->map(function (int $rosterClassId) use (
                $request,
                $exam,
                $instituteId,
                $classId,
                $sectionId
            ) {
                $roster = $this->examRoster(
                    $exam,
                    $instituteId,
                    $rosterClassId,
                    $rosterClassId === $classId ? $sectionId : ''
                );
                $rollNumber = trim((string) $request->query('roll_number', ''));
                if ($rollNumber !== '') {
                    $roster = $roster->filter(fn (StudentAcademicInfo $info) => str_contains(
                        mb_strtolower((string) $info->roll_number),
                        mb_strtolower($rollNumber)
                    ))->values();
                }

                $class = InstituteClass::query()
                    ->where('institute_id', $instituteId)
                    ->findOrFail($rosterClassId);

                return [
                    'class_name' => $class->name,
                    'class_bn_name' => $class->bn_name,
                    'students' => $roster,
                ];
            });
            $maxClassRosterSize = $classRosterLists->max(fn (array $list) => $list['students']->count()) ?? 0;
            $roster = collect();
            for ($index = 0; $index < $maxClassRosterSize; $index++) {
                foreach ($classRosterLists as $classRosterList) {
                    $info = $classRosterList['students']->get($index);
                    if ($info) {
                        $roster->push([
                            'info' => $info,
                            'class_name' => $classRosterList['class_name'],
                            'class_bn_name' => $classRosterList['class_bn_name'],
                        ]);
                    }
                }
            }

            if ($studentId !== null && $studentId !== '') {
                abort_unless($roster->contains(
                    fn (array $entry) => (int) $entry['info']->student_id === (int) $studentId
                ), 404);
                $roster = $roster->filter(
                    fn (array $entry) => (int) $entry['info']->student_id === (int) $studentId
                )->values();
            }

            $capacity = collect($validated['rooms'])->sum(
                fn (array $room) => (int) $room['bench_rows'] * (int) $room['seats_per_bench']
            );
            if ($capacity < $roster->count()) {
                throw ValidationException::withMessages([
                    'rooms' => "নির্বাচিত শিক্ষার্থীর সংখ্যা {$roster->count()} জন, কিন্তু রুমের মোট আসন {$capacity}টি।",
                ]);
            }

            $studentIndex = 0;
            foreach ($validated['rooms'] as $room) {
                $roomStudents = collect();
                $roomCapacity = (int) $room['bench_rows'] * (int) $room['seats_per_bench'];
                for ($seatIndex = 0; $seatIndex < $roomCapacity && $studentIndex < $roster->count(); $seatIndex++) {
                    $entry = $roster->get($studentIndex++);
                    $info = $entry['info'];
                    $roomStudents->push([
                        'student' => $this->studentData($info),
                        'class_name' => $entry['class_name'],
                        'class_bn_name' => $entry['class_bn_name'],
                        'row' => intdiv($seatIndex, (int) $room['seats_per_bench']) + 1,
                        'seat' => ($seatIndex % (int) $room['seats_per_bench']) + 1,
                        'room_name' => $room['room_name'],
                    ]);
                }

                $roomPlans->push([
                    'room_name' => $room['room_name'],
                    'bench_rows' => (int) $room['bench_rows'],
                    'seats_per_bench' => (int) $room['seats_per_bench'],
                    'students' => $roomStudents,
                ]);
            }
            $roster = $roomPlans->flatMap(fn (array $room) => $room['students'])->values();
        } else {
            $fullRoster = $this->examRoster($exam, $instituteId, $classId);
            $roster = $this->filteredExamRoster($request, $exam, $instituteId);
            if ($studentId !== null && $studentId !== '') {
                abort_unless($roster->contains(fn (StudentAcademicInfo $info) => (int) $info->student_id === (int) $studentId), 404);
                $roster = $roster->where('student_id', (int) $studentId)->values();
            }
            $class = InstituteClass::query()
                ->where('institute_id', $instituteId)
                ->findOrFail($classId);
            $roster = $roster
                ->map(function (StudentAcademicInfo $info) use ($fullRoster, $seatsPerRow, $validated, $class) {
                    $index = $fullRoster->search(fn (StudentAcademicInfo $candidate) => (int) $candidate->student_id === (int) $info->student_id);

                    return [
                        'student' => $this->studentData($info),
                        'class_name' => $class->name,
                        'class_bn_name' => $class->bn_name,
                        'row' => intdiv($index, $seatsPerRow) + 1,
                        'seat' => ($index % $seatsPerRow) + 1,
                        'room_name' => ($validated['room_name'] ?? '') ?: 'Main Exam Hall',
                    ];
                })
                ->values();
        }

        return Inertia::render('Institute/ExamDocuments/Print', [
            'documentType' => 'seat-plan',
            'institute' => $this->instituteData($request),
            'exam' => $this->examData($exam, $classId),
            'seatPlan' => [
                'room_name' => ($validated['room_name'] ?? '') ?: 'Main Exam Hall',
                'seats_per_row' => $seatsPerRow,
                'single_student' => $singleStudent,
                'section_name' => $sectionId
                    ? $sections->firstWhere('id', (int) $sectionId)?->name
                    : 'All Sections',
                'students' => $roster,
                'rooms' => $roomPlans,
            ],
        ]);
    }

    private function instituteId(Request $request): int
    {
        abort_if(! $request->user()->institute_id, 403, 'An institute must be assigned to this account.');

        return (int) $request->user()->institute_id;
    }

    private function examsForInstitute(int $instituteId)
    {
        return Exam::query()->where(function ($query) use ($instituteId) {
            $query->whereHas('instituteClass', fn ($classes) => $classes->where('institute_id', $instituteId))
                ->orWhereHas('classes', fn ($classes) => $classes->where('institute_classes.institute_id', $instituteId));
        });
    }

    private function findExam(int $examId, int $instituteId): Exam
    {
        return $this->examsForInstitute($instituteId)
            ->with(['instituteClass:id,name,bn_name', 'classes:id,name,bn_name', 'academicYear:id,session'])
            ->findOrFail($examId);
    }

    private function findStudentInfo(Exam $exam, int $studentId, int $instituteId, ?int $classId = null): StudentAcademicInfo
    {
        $classId ??= (int) $exam->institute_class_id;
        abort_unless(in_array($classId, $exam->classIds(), true), 404);

        return StudentAcademicInfo::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('institute_class_id', $classId)
            ->where('student_id', $studentId)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->with(['student', 'section'])
            ->firstOrFail();
    }

    private function examRoster(Exam $exam, int $instituteId, string|int $classId, string|int $sectionId = ''): Collection
    {
        return StudentAcademicInfo::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->where('institute_class_id', $classId)
            ->when($sectionId !== '', fn ($query) => $query->where('section_id', $sectionId))
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->with(['student', 'section'])
            ->orderBy('roll_number')
            ->get()
            ->unique('student_id')
            ->sort(function (StudentAcademicInfo $first, StudentAcademicInfo $second) {
                $sectionOrder = strnatcasecmp($first->section?->name ?? '', $second->section?->name ?? '');

                return $sectionOrder !== 0
                    ? $sectionOrder
                    : strnatcasecmp((string) $first->roll_number, (string) $second->roll_number);
            })
            ->values();
    }

    private function filteredExamRoster(Request $request, Exam $exam, int $instituteId): Collection
    {
        $classId = $this->selectedClassId($request, $exam);
        $sectionId = $request->query('section_id', '');
        if ($sectionId !== '') {
            abort_unless(
                Section::query()
                    ->where('id', $sectionId)
                    ->where('institute_id', $instituteId)
                    ->where('institute_class_id', $classId)
                    ->exists(),
                404
            );
        }

        $roster = $this->examRoster($exam, $instituteId, $classId, $sectionId);
        $rollNumber = trim((string) $request->query('roll_number', ''));

        return $rollNumber === ''
            ? $roster
            : $roster->filter(fn (StudentAcademicInfo $info) => str_contains(
                mb_strtolower((string) $info->roll_number),
                mb_strtolower($rollNumber)
            ))->values();
    }

    private function selectedClassId(Request $request, Exam $exam): int
    {
        $classId = (int) $request->query('class_id', $exam->institute_class_id);
        abort_unless(in_array($classId, $exam->classIds(), true), 404);

        return $classId;
    }

    private function examSubjects(Exam $exam, int $instituteId, ?int $classId = null): Collection
    {
        $subjects = ExamInstituteSubjec::query()
            ->where('exam_id', $exam->id)
            ->when($classId !== null, fn ($query) => $query->where('institute_class_id', $classId))
            ->whereHas('instituteSubject', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('instituteSubject:id,institute_id,name,bn_name,theory_marks,theory_pass_marks,practical_marks,practical_pass_marks,mcq_marks,mcq_pass_marks')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get();

        return $subjects;
    }

    private function subjectsForStudent(
        Collection $subjects,
        StudentAcademicInfo $studentInfo,
        ?Collection $choiceSubjectIds = null
    ): Collection {
        $choiceSubjectIds ??= $this->choiceSubjectIdsForClass($studentInfo->institute_class_id);
        $selectedIds = collect([
            $studentInfo->elective_subject_id,
            $studentInfo->optional_subject_id,
            $studentInfo->additional_subject_id,
        ])->filter()->map(fn ($id) => (int) $id);

        return $subjects->reject(fn (ExamInstituteSubjec $subject) => (
            collect(['elective', 'optional', 'additional'])->contains(
                fn ($type) => $choiceSubjectIds->get($type, collect())->contains($subject->institute_subject_id)
            ) && ! $selectedIds->contains((int) $subject->institute_subject_id)
        ))->values();
    }

    private function choiceSubjectIdsForClass(int $classId): Collection
    {
        return DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $classId)
            ->whereIn('type', ['elective', 'optional', 'additional'])
            ->get(['type', 'institute_subject_id'])
            ->groupBy('type')
            ->map(fn ($choices) => $choices->pluck('institute_subject_id'));
    }

    private function instituteData(Request $request): array
    {
        return $this->instituteDataForId($this->instituteId($request));
    }

    private function instituteDataForId(int $instituteId): array
    {
        $institute = Institute::findOrFail($instituteId);

        return [
            ...$institute->only(['name', 'phone', 'address']),
            'logo_url' => $institute->logo
                ? Storage::disk('public')->url($institute->logo)
                : null,
        ];
    }

    private function publicMarksheetUrl(Request $request, int $examId, int $studentId): string
    {
        $host = $request->server->get('HTTP_HOST')
            ?: $request->headers->get('host')
            ?: parse_url(config('app.url'), PHP_URL_HOST);

        $root = $request->getScheme() . '://' . $host;

        URL::forceRootUrl($root);
        URL::forceScheme($request->getScheme());

        try {
            return URL::signedRoute('public.marksheet', [
                'exam' => $examId,
                'student' => $studentId,
            ]);
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }

    private function examData(Exam $exam, ?int $classId = null): array
    {
        $class = $classId === null || $classId === (int) $exam->institute_class_id
            ? $exam->instituteClass
            : $exam->classes->firstWhere('id', $classId);

        return [
            'id' => $exam->id,
            'class_id' => $class?->id,
            'name' => $exam->name,
            'bn_name' => $exam->bn_name,
            'class_name' => $class?->name,
            'class_bn_name' => $class?->bn_name,
            'academic_year' => $exam->academicYear?->session,
            'start_date' => $exam->start_date?->format('d M Y'),
            'end_date' => $exam->end_date?->format('d M Y'),
        ];
    }

    private function studentData(StudentAcademicInfo $info): array
    {
        return [
            'id' => $info->student_id,
            'name' => $info->student->name,
            'bn_name' => $info->student->bn_name,
            'student_id' => $info->student->student_id,
            'roll_number' => $info->roll_number,
            'section_name' => $info->section?->name,
            'photo' => $info->student->photo_url,
            'father_name' => $info->student->father_name,
            'mother_name' => $info->student->mother_name,
        ];
    }

    private function resultData(Result $result): array
    {
        return $result->only([
            'total_subjects', 'total_marks', 'total_point', 'gpa',
            'total_full_marks', 'final_grade', 'total_absent', 'position', 'class_position', 'remarks',
        ]);
    }

    private function resultSummaryForStudent(
        Collection $subjects,
        Collection $marks,
        StudentAcademicInfo $studentInfo,
        int $instituteId
    ): ?array {
        if ($subjects->isEmpty()) {
            return null;
        }

        $marksBySubject = $marks->keyBy('institute_subject_id');
        if ($subjects->contains(fn (ExamInstituteSubjec $subject) => ! $marksBySubject->has($subject->institute_subject_id))) {
            return null;
        }

        $grading = app(MarkGradingService::class);
        $gradeRanges = $grading->gradeRangesForInstitute($instituteId);
        $regularPoints = 0.0;
        $regularSubjects = 0;
        $optionalPoint = 0.0;
        $failedRegularSubject = false;
        $totalMarks = 0.0;
        $totalFullMarks = 0.0;
        $totalAbsent = 0;

        foreach ($subjects as $examSubject) {
            $mark = $marksBySubject->get($examSubject->institute_subject_id);
            $subject = $examSubject->instituteSubject;
            if (! $mark || ! $subject) {
                return null;
            }

            $failsComponents = ((float) $subject->practical_marks > 0 || (float) $subject->mcq_marks > 0)
                && $grading->failsMarkComponents($subject, [
                    'theory_marks' => $mark->theory_marks,
                    'mcq_marks' => $mark->mcq_marks,
                    'practical_marks' => $mark->practical_marks,
                ], (float) $examSubject->full_marks);
            $gradeData = $grading->gradeForExam(
                $mark->is_absent ? 0 : (float) $mark->total_marks,
                (float) $examSubject->full_marks,
                (float) $examSubject->pass_marks,
                $gradeRanges,
                $mark->is_absent || $failsComponents
            );

            $totalMarks += (float) $mark->total_marks;
            $totalFullMarks += (float) $examSubject->full_marks;
            $totalAbsent += $mark->is_absent ? 1 : 0;

            if ((int) $studentInfo->optional_subject_id === (int) $examSubject->institute_subject_id) {
                $optionalPoint = $gradeData['point'];

                continue;
            }

            $regularSubjects++;
            $regularPoints += $gradeData['point'];
            $failedRegularSubject = $failedRegularSubject || $gradeData['grade'] === 'F';
        }

        $totalPoint = $regularPoints + max(0, $optionalPoint - 2);
        $gpa = $regularSubjects > 0 && ! $failedRegularSubject
            ? min(5, $totalPoint / $regularSubjects)
            : 0;
        $finalGrade = $failedRegularSubject
            ? ($gradeRanges->first(fn ($range) => data_get($range, 'name') === 'F') ?? ['name' => 'F'])
            : $gradeRanges->first(fn ($range) => (float) data_get($range, 'point') <= $gpa);

        return [
            'total_subjects' => $subjects->count(),
            'total_marks' => number_format($totalMarks, 2, '.', ''),
            'total_point' => number_format($totalPoint, 2, '.', ''),
            'gpa' => number_format(round($gpa, 2), 2, '.', ''),
            'total_full_marks' => number_format($totalFullMarks, 2, '.', ''),
            'final_grade' => is_array($finalGrade) ? $finalGrade['name'] : ($finalGrade?->name ?? 'F'),
            'total_absent' => $totalAbsent,
        ];
    }

    private function marksheetSubjects(Collection $subjects, Collection $marks): Collection
    {
        $marks = $marks->keyBy('institute_subject_id');
        $grading = app(MarkGradingService::class);
        $gradeRangesByInstitute = [];

        return $subjects->map(function (ExamInstituteSubjec $examSubject) use ($marks, $grading, &$gradeRangesByInstitute) {
            $subject = $examSubject->instituteSubject;
            $mark = $marks->get($examSubject->institute_subject_id);
            $markData = $mark?->only([
                'total_marks', 'theory_marks', 'practical_marks', 'mcq_marks',
                'grade_point', 'grade', 'is_absent', 'remarks',
            ]);

            if ($mark && $subject) {
                $instituteId = (int) $subject->institute_id;
                $gradeRangesByInstitute[$instituteId] ??= $grading->gradeRangesForInstitute($instituteId);
                $failsComponents = ((float) $subject->practical_marks > 0 || (float) $subject->mcq_marks > 0)
                    && $grading->failsMarkComponents($subject, [
                        'theory_marks' => $mark->theory_marks,
                        'mcq_marks' => $mark->mcq_marks,
                        'practical_marks' => $mark->practical_marks,
                    ], (float) $examSubject->full_marks);
                $gradeData = $grading->gradeForExam(
                    $mark->is_absent ? 0 : (float) $mark->total_marks,
                    (float) $examSubject->full_marks,
                    (float) $examSubject->pass_marks,
                    $gradeRangesByInstitute[$instituteId],
                    $mark->is_absent || $failsComponents
                );
                $markData['grade'] = $gradeData['grade'];
                $markData['grade_point'] = number_format($gradeData['point'], 2, '.', '');
            }

            return [
                'id' => $examSubject->institute_subject_id,
                'name' => $subject?->name ?? 'Subject',
                'bn_name' => $subject?->bn_name,
                'full_marks' => $examSubject->full_marks,
                'pass_marks' => $examSubject->pass_marks,
                'mcq_full_marks' => $subject?->mcq_marks,
                'practical_full_marks' => $subject?->practical_marks,
                'theory_full_marks' => $subject?->theory_marks,
                'mark' => $markData,
            ];
        });
    }
}
