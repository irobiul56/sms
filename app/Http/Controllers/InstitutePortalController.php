<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Grade;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Mark;
use App\Models\Result;
use App\Models\Section;
use App\Models\StudentAcademicInfo;
use App\Services\MarkGradingService;
use App\Services\SubjectGroupService;
use App\Services\TeacherPermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstitutePortalController extends Controller
{
    public function results(Request $request): Response
    {
        return $this->renderResults($request, false);
    }

    public function publishResults(Request $request): Response
    {
        return $this->renderResults($request, true);
    }

    private function renderResults(Request $request, bool $publishPage): Response
    {
        $instituteId = $request->user()->institute_id;
        $isTeacher = $publishPage && strtolower((string) $request->user()->usertype) === 'teacher';
        $permissionAssignments = $isTeacher
            ? app(TeacherPermissionService::class)->classAssignmentsForPermission(
                $request->user(),
                'results.publish'
            )
            : collect();
        $allowedClassIds = $permissionAssignments->pluck('institute_class_id')->unique()->values();
        $filters = [
            'exam_id' => $request->query('exam_id'),
            'class_id' => $request->query('class_id'),
            'section_id' => $request->query('section_id'),
            'subject_id' => $request->query('subject_id'),
            'status' => $request->query('status', $publishPage ? 'unpublished' : 'all'),
        ];
        $classQuery = InstituteClass::query()
            ->where('institute_id', $instituteId)
            ->when($isTeacher, fn ($query) => $query->whereIn('id', $allowedClassIds))
            ->orderBy('numeric_value');
        $examQuery = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with(['instituteClass:id,name', 'classes:id,name', 'academicYear:id,session'])
            ->orderByDesc('start_date');
        if ($isTeacher) {
            $examQuery->where(function ($query) use ($allowedClassIds) {
                $query->whereIn('institute_class_id', $allowedClassIds)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereIn('institute_classes.id', $allowedClassIds));
            });
        }

        if (is_numeric($filters['class_id'])) {
            $examQuery->where(function ($query) use ($filters) {
                $query->where('institute_class_id', $filters['class_id'])
                    ->orWhereHas('classes', fn ($classes) => $classes->whereKey($filters['class_id']));
            });
        }

        $classes = $classQuery->get(['id', 'name', 'group']);
        $exams = $examQuery->get(['id', 'name', 'institute_class_id', 'academic_year_id'])
            ->each(fn (Exam $exam) => $exam->setAttribute('class_ids', $exam->classIds()));
        if ($isTeacher) {
            $exams = $exams->filter(function (Exam $exam) use ($permissionAssignments, $allowedClassIds) {
                $yearClassIds = $permissionAssignments
                    ->where('academic_year_id', (int) $exam->academic_year_id)
                    ->pluck('institute_class_id')
                    ->unique();
                $visibleClassIds = collect($exam->class_ids)->intersect($yearClassIds)->values();
                if ($visibleClassIds->isEmpty()) {
                    return false;
                }
                $exam->setAttribute('class_ids', $visibleClassIds->all());

                return true;
            })->values();
        }
        $sectionQuery = Section::query()->where('institute_id', $instituteId);
        if (is_numeric($filters['class_id'])) {
            $sectionQuery->where('institute_class_id', $filters['class_id']);
        }
        if ($isTeacher) {
            $classHasAllSections = $permissionAssignments
                ->where('institute_class_id', (int) $filters['class_id'])
                ->contains(fn ($assignment) => $assignment->section_id === null);
            if (! $classHasAllSections) {
                $sectionQuery->whereIn(
                    'id',
                    $permissionAssignments->where('institute_class_id', (int) $filters['class_id'])
                        ->pluck('section_id')->filter()
                );
            }
        }
        $sections = $sectionQuery->orderBy('name')->get(['id', 'name', 'institute_class_id']);

        if (! $classes->contains('id', (int) $filters['class_id'])) {
            $filters['class_id'] = '';
        }
        if (! $exams->contains('id', (int) $filters['exam_id'])) {
            $filters['exam_id'] = '';
        }
        if (! $sections->contains('id', (int) $filters['section_id'])) {
            $filters['section_id'] = '';
        }
        $selectedExam = $exams->firstWhere('id', (int) $filters['exam_id']);
        $canGenerateResults = ! $isTeacher;
        $canPrintTabulation = ! $isTeacher;
        $canViewMarks = ! $isTeacher || app(TeacherPermissionService::class)
            ->hasAnySubjectPermission($request->user(), 'marks.view');
        if ($isTeacher && $selectedExam) {
            $permissionService = app(TeacherPermissionService::class);
            $targetClassIds = is_numeric($filters['class_id'])
                ? [(int) $filters['class_id']]
                : $selectedExam->class_ids;
            $canGenerateResults = collect($selectedExam->classIds())->every(
                fn (int $classId) => $permissionService->hasClassPermission(
                    $request->user(),
                    'results.publish',
                    $classId,
                    null,
                    (int) $selectedExam->academic_year_id
                )
            );
            $canPrintTabulation = $targetClassIds !== [] && collect($targetClassIds)->every(
                fn (int $classId) => $permissionService->hasClassPermission(
                    $request->user(),
                    'tabulation_sheet.print',
                    $classId,
                    is_numeric($filters['section_id']) ? (int) $filters['section_id'] : null,
                    (int) $selectedExam->academic_year_id
                )
            );
        }

        $query = $this->instituteResults($request)->with(['student', 'exam', 'section', 'instituteClass']);
        if ($isTeacher) {
            $query->where(function ($query) use ($permissionAssignments) {
                foreach ($permissionAssignments as $assignment) {
                    $query->orWhere(function ($scope) use ($assignment) {
                        $scope->where('institute_class_id', $assignment->institute_class_id)
                            ->whereHas('exam', fn ($exam) => $exam->where(
                                'academic_year_id',
                                $assignment->academic_year_id
                            ));
                        if ($assignment->section_id !== null) {
                            $scope->where('section_id', $assignment->section_id);
                        }
                    });
                }
            });
        }
        if (is_numeric($filters['exam_id'])) {
            $query->where('exam_id', $filters['exam_id']);
        }
        if (is_numeric($filters['class_id'])) {
            $query->where('institute_class_id', $filters['class_id']);
        }
        if (is_numeric($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }
        if ($filters['status'] === 'published') {
            $query->where('is_published', true);
        } elseif ($filters['status'] === 'unpublished') {
            $query->where('is_published', false);
        } else {
            $filters['status'] = 'all';
        }

        $results = $query->orderBy('position')->orderBy('class_position')->get();
        $subjects = collect();
        $tabulationRows = collect();

        if (is_numeric($filters['exam_id'])) {
            $examSubjectRows = ExamInstituteSubjec::query()
                ->with('instituteSubject:id,name')
                ->where('exam_id', $filters['exam_id'])
                ->when(is_numeric($filters['class_id']), fn ($query) => $query->where(
                    'institute_class_id',
                    $filters['class_id']
                ))
                ->when(is_numeric($filters['class_id']), fn ($query) => $query->whereHas(
                    'instituteSubject.classes',
                    fn ($classes) => $classes->whereKey($filters['class_id'])
                ))
                ->get(['id', 'exam_id', 'institute_subject_id', 'full_marks', 'pass_marks']);
            $examSubjectIds = $examSubjectRows->pluck('institute_subject_id')->unique()->values();
            $targetClassIds = is_numeric($filters['class_id'])
                ? collect([(int) $filters['class_id']])
                : collect($selectedExam?->class_ids ?? []);
            $classSubjectGroups = $targetClassIds->mapWithKeys(fn ($classId) => [
                $classId => app(SubjectGroupService::class)->forClass((int) $classId, $examSubjectIds),
            ]);
            $subjects = $classSubjectGroups->flatten(1)
                ->unique('key')
                ->map(function (array $group) use ($examSubjectRows) {
                    $groupRows = $examSubjectRows
                        ->whereIn('institute_subject_id', $group['subject_ids'])
                        ->unique('institute_subject_id');
                    $firstSubject = $groupRows->first()?->instituteSubject;

                    return [
                        'id' => (int) $group['subject_ids']->first(),
                        'key' => $group['key'],
                        'subject_ids' => $group['subject_ids'],
                        'name' => $group['name'] ?? $firstSubject?->name ?? 'Subject',
                        'full_marks' => $groupRows->sum('full_marks'),
                        'pass_marks' => $groupRows->sum('pass_marks'),
                    ];
                })
                ->values();

            if (! $subjects->contains(
                fn (array $subject) => collect($subject['subject_ids'])->contains((int) $filters['subject_id'])
            )) {
                $filters['subject_id'] = '';
            }

            $marksByStudent = Mark::query()
                ->where('exam_id', $filters['exam_id'])
                ->whereIn('student_id', $results->pluck('student_id'))
                ->get()
                ->groupBy('student_id');

            $gradeRanges = app(MarkGradingService::class)->gradeRangesForInstitute((int) $instituteId);
            $groupedMark = function (Collection $subjectIds, Collection $rawMarks) use (
                $examSubjectRows,
                $gradeRanges
            ) {
                $subjectMarks = $rawMarks->whereIn('institute_subject_id', $subjectIds);
                if ($subjectMarks->isEmpty()) {
                    return null;
                }

                $subjectExamRows = $examSubjectRows->whereIn('institute_subject_id', $subjectIds);
                $fullMarks = (float) $subjectExamRows->sum('full_marks');
                $passMarks = (float) $subjectExamRows->sum('pass_marks');
                $totalMarks = (float) $subjectMarks->sum(fn (Mark $mark) => $mark->is_absent ? 0 : $mark->total_marks);
                $isAbsent = $subjectMarks->count() === $subjectExamRows->count()
                    && $subjectMarks->every(fn (Mark $mark) => $mark->is_absent);
                $grade = app(MarkGradingService::class)->gradeForExam(
                    $totalMarks,
                    $fullMarks,
                    $passMarks,
                    $gradeRanges,
                    $isAbsent
                );

                return [
                    'total_marks' => number_format($totalMarks, 2, '.', ''),
                    'grade' => $grade['grade'],
                    'grade_point' => number_format($grade['point'], 2, '.', ''),
                    'is_absent' => $isAbsent,
                ];
            };
            $results->each(function (Result $result) use ($marksByStudent, $classSubjectGroups, $groupedMark) {
                $groups = $classSubjectGroups->get($result->institute_class_id, collect());
                $rawMarks = $marksByStudent->get($result->student_id, collect());
                $subjectMarks = $groups->mapWithKeys(fn (array $group) => [
                    $group['key'] => $groupedMark($group['subject_ids'], $rawMarks),
                ])->filter();
                $result->setAttribute(
                    'subject_marks',
                    $subjectMarks
                );
            });

            if (is_numeric($filters['subject_id'])) {
                $studentInfoQuery = StudentAcademicInfo::query()
                    ->where('academic_year_id', $selectedExam->academic_year_id)
                    ->whereIn(
                        'institute_class_id',
                        is_numeric($filters['class_id']) ? [(int) $filters['class_id']] : $selectedExam->class_ids
                    )
                    ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
                    ->with(['student:id,student_id,roll_no,name', 'section:id,name']);
                if ($isTeacher) {
                    $studentInfoQuery->where(function ($query) use ($permissionAssignments) {
                        foreach ($permissionAssignments as $assignment) {
                            $query->orWhere(function ($scope) use ($assignment) {
                                $scope->where('institute_class_id', $assignment->institute_class_id)
                                    ->where('academic_year_id', $assignment->academic_year_id);
                                if ($assignment->section_id !== null) {
                                    $scope->where('section_id', $assignment->section_id);
                                }
                            });
                        }
                    });
                }

                if (is_numeric($filters['section_id'])) {
                    $studentInfoQuery->where('section_id', $filters['section_id']);
                }

                $studentInfos = $studentInfoQuery
                    ->orderBy('roll_number')
                    ->get(['student_id', 'section_id', 'roll_number', 'institute_class_id']);
                $studentIds = $studentInfos->pluck('student_id');
                $subjectMarks = Mark::query()
                    ->where('exam_id', $filters['exam_id'])
                    ->whereIn('institute_subject_id', $examSubjectIds)
                    ->whereIn('student_id', $studentIds)
                    ->get()
                    ->groupBy('student_id');
                $studentResults = $this->instituteResults($request)
                    ->where('exam_id', $filters['exam_id'])
                    ->whereIn('student_id', $studentIds)
                    ->get()
                    ->keyBy('student_id');

                $tabulationRows = $studentInfos->map(function (StudentAcademicInfo $studentInfo) use (
                    $classSubjectGroups,
                    $filters,
                    $subjectMarks,
                    $studentResults,
                    $groupedMark
                ) {
                    $group = $classSubjectGroups->get($studentInfo->institute_class_id, collect())
                        ->first(fn (array $group) => collect($group['subject_ids'])
                            ->contains((int) $filters['subject_id']));

                    return [
                        'student' => $studentInfo->student,
                        'section' => $studentInfo->section,
                        'roll_number' => $studentInfo->roll_number,
                        'mark' => $group
                            ? $groupedMark($group['subject_ids'], $subjectMarks->get($studentInfo->student_id, collect()))
                            : null,
                        'result' => $studentResults->get($studentInfo->student_id),
                    ];
                });
            }
        }

        return Inertia::render('Institute/Results/Index', [
            'results' => $results,
            'showUnpublishedOnly' => $publishPage,
            'filters' => $filters,
            'classes' => $classes,
            'sections' => $sections,
            'exams' => $exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'academic_year' => $exam->academicYear?->session,
                'institute_class' => [
                    'name' => $classes->firstWhere('id', $exam->class_ids[0] ?? $exam->institute_class_id)?->name,
                ],
            ])->values(),
            'subjects' => $subjects,
            'tabulationRows' => $tabulationRows,
            'canGenerateResults' => $canGenerateResults,
            'canPrintTabulation' => $canPrintTabulation,
            'canViewMarks' => $canViewMarks,
            'isTeacher' => strtolower((string) $request->user()->usertype) === 'teacher',
        ]);
    }

    public function toggleResultPublish(Request $request, Result $result): RedirectResponse
    {
        $result = $this->instituteResults($request)
            ->whereKey($result->getKey())
            ->firstOrFail();

        $result->is_published = ! $result->is_published;
        $result->save();

        return back()->with('success', $result->is_published
            ? 'Result published successfully.'
            : 'Result unpublished successfully.');
    }

    public function generateResults(Request $request, int $exam): RedirectResponse
    {
        $instituteId = $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned before generating results.');

        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('classes')
            ->findOrFail($exam);

        $examSubjects = ExamInstituteSubjec::query()
            ->where('exam_id', $exam->id)
            ->get([
                'institute_class_id',
                'institute_subject_id',
                'full_marks',
                'pass_marks',
            ]);

        if ($examSubjects->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'Assign subjects to this exam before generating results.',
            ]);
        }

        $subjectIds = $examSubjects->pluck('institute_subject_id')->unique();
        $classIds = collect($exam->classIds())
            ->filter(fn ($classId) => InstituteClass::query()
                ->where('institute_id', $instituteId)
                ->whereKey($classId)
                ->exists())
            ->values();
        $assignedSubjectsByClass = $examSubjects->groupBy('institute_class_id')
            ->map(fn ($assignments) => $assignments->pluck('institute_subject_id')->unique()->values());
        $classSubjectAssignments = DB::table('institute_class_institute_subjects')
            ->whereIn('institute_class_id', $classIds)
            ->whereIn('institute_subject_id', $subjectIds)
            ->get(['institute_class_id', 'institute_subject_id', 'type', 'is_group_based', 'group_name']);
        $subjectTypesByClass = $classSubjectAssignments->groupBy('institute_class_id')
            ->map(fn ($assignments) => $assignments->keyBy('institute_subject_id')
                ->map(fn ($assignment) => $assignment->type));
        $subjectGroupsByClass = $classSubjectAssignments->groupBy('institute_class_id')
            ->map(function ($assignments) {
                return $assignments->keyBy('institute_subject_id')
                    ->map(fn ($assignment) => (bool) $assignment->is_group_based && ! empty($assignment->group_name)
                        ? (string) $assignment->group_name
                        : (string) $assignment->institute_subject_id);
            });
        $choiceSubjectIdsByClass = DB::table('institute_class_institute_subjects')
            ->whereIn('institute_class_id', $classIds)
            ->whereIn('type', ['elective', 'optional', 'additional'])
            ->get(['institute_class_id', 'institute_subject_id', 'type'])
            ->groupBy('institute_class_id')
            ->map(fn ($assignments) => $assignments->groupBy('type')
                ->map(fn ($choices) => $choices->pluck('institute_subject_id')->unique()->values()));
        $hasClassSubjectAssignments = $assignedSubjectsByClass->isNotEmpty();
        $subjectIdsByClass = $classIds->mapWithKeys(fn ($classId) => [
            $classId => $assignedSubjectsByClass->get(
                $classId,
                $hasClassSubjectAssignments ? collect() : $subjectIds->values()
            ),
        ]);
        $fullMarksByClass = $examSubjects->groupBy('institute_class_id')
            ->map(fn ($assignments) => $assignments->keyBy('institute_subject_id')
                ->map(fn (ExamInstituteSubjec $examSubject) => (float) $examSubject->full_marks));
        $passMarksByClass = $examSubjects->groupBy('institute_class_id')
            ->map(fn ($assignments) => $assignments->keyBy('institute_subject_id')
                ->map(fn (ExamInstituteSubjec $examSubject) => (float) $examSubject->pass_marks));
        $subjectsById = InstituteSubject::query()
            ->whereIn('id', $subjectIds)
            ->where('institute_id', $instituteId)
            ->get([
                'id', 'theory_marks', 'theory_pass_marks',
                'mcq_marks', 'mcq_pass_marks',
                'practical_marks', 'practical_pass_marks',
            ])
            ->keyBy('id');
        $markGrading = app(MarkGradingService::class);
        $grades = Grade::forInstitute($instituteId)->orderByDesc('marks_from')->get();
        $gradeRanges = $grades->isNotEmpty()
            ? $grades
            : collect(Grade::getDefaultGrades());
        $students = StudentAcademicInfo::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->whereIn('institute_class_id', $classIds)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('student:id')
            ->get([
                'student_id', 'section_id', 'institute_class_id',
                'elective_subject_id', 'optional_subject_id', 'additional_subject_id',
            ])
            ->unique('student_id');

        foreach ($students as $studentInfo) {
            $classChoices = $choiceSubjectIdsByClass->get($studentInfo->institute_class_id, collect());
            foreach ([
                'elective' => 'elective_subject_id',
                'optional' => 'optional_subject_id',
                'additional' => 'additional_subject_id',
            ] as $type => $field) {
                $choices = $classChoices->get($type, collect());
                if ($choices->isNotEmpty() && ! $choices->contains($studentInfo->{$field})) {
                    throw ValidationException::withMessages([
                        'exam' => "Select each student's {$type} subject before generating results.",
                    ]);
                }
                if ($studentInfo->{$field} && ! $subjectIdsByClass
                    ->get($studentInfo->institute_class_id, collect())
                    ->contains($studentInfo->{$field})) {
                    throw ValidationException::withMessages([
                        'exam' => "Assign each student's selected {$type} subject to this exam before generating results.",
                    ]);
                }
            }
        }

        $marksByStudent = Mark::query()
            ->where('exam_id', $exam->id)
            ->whereIn('institute_class_id', $classIds)
            ->whereIn('institute_subject_id', $subjectIds)
            ->whereIn('student_id', $students->pluck('student_id'))
            ->get()
            ->groupBy('student_id');

        $generatedCount = 0;

        DB::transaction(function () use (
            $students,
            $marksByStudent,
            $subjectIds,
            $subjectIdsByClass,
            $subjectTypesByClass,
            $fullMarksByClass,
            $passMarksByClass,
            $choiceSubjectIdsByClass,
            $subjectsById,
            $subjectGroupsByClass,
            $markGrading,
            $gradeRanges,
            $exam,
            &$generatedCount
        ) {
            foreach ($students->groupBy('institute_class_id') as $classId => $classStudents) {
                $classSubjectIds = $subjectIdsByClass->get($classId, collect())->sort()->values();
                if ($classSubjectIds->isEmpty()) {
                    continue;
                }
                $classSubjectTypes = $subjectTypesByClass->get($classId, collect());
                $fullMarksBySubject = $fullMarksByClass->get($classId, collect());
                $passMarksBySubject = $passMarksByClass->get($classId, collect());

                foreach ($classStudents as $studentInfo) {
                $expectedSubjectIds = $classSubjectIds->reject(fn ($subjectId) => in_array(
                    $classSubjectTypes->get($subjectId),
                    ['elective', 'optional', 'additional'],
                    true
                ));
                foreach ([
                    'elective_subject_id',
                    'optional_subject_id',
                    'additional_subject_id',
                ] as $field) {
                    if ($studentInfo->{$field} && $classSubjectIds->contains($studentInfo->{$field})) {
                        $expectedSubjectIds->push($studentInfo->{$field});
                    }
                }
                $expectedSubjectIds = $expectedSubjectIds->sort()->values();
                $marks = $marksByStudent->get($studentInfo->student_id, collect())
                    ->filter(fn (Mark $mark) => $expectedSubjectIds->contains($mark->institute_subject_id))
                    ->unique('institute_subject_id')
                    ->keyBy('institute_subject_id');

                if ($marks->keys()->sort()->values()->all() !== $expectedSubjectIds->all()) {
                    continue;
                }

                $optionalSubjectId = $studentInfo->optional_subject_id;
                $regularMarks = $marks->reject(
                    fn (Mark $mark) => (int) $mark->institute_subject_id === (int) $optionalSubjectId
                );
                $marks->each(function (Mark $mark) use (
                    $subjectsById,
                    $fullMarksBySubject,
                    $passMarksBySubject,
                    $markGrading,
                    $gradeRanges
                ) {
                    $subject = $subjectsById->get($mark->institute_subject_id);
                    if (! $subject) {
                        return;
                    }

                    $fullMarks = $fullMarksBySubject->get($mark->institute_subject_id);
                    $passMarks = $passMarksBySubject->get($mark->institute_subject_id);
                    $grade = $markGrading->gradeForExam(
                        $mark->total_marks,
                        $fullMarks,
                        $passMarks,
                        $gradeRanges,
                        $mark->is_absent || $markGrading->failsMarkForExam($mark, $subject, (float) $fullMarks)
                    );
                    $mark->forceFill([
                        'grade' => $grade['grade'],
                        'grade_point' => $grade['point'],
                    ])->save();
                });

                $groupMap = $subjectGroupsByClass->get($classId, collect());
                $groupedMarks = $marks->groupBy(fn (Mark $mark) => $groupMap->get(
                    $mark->institute_subject_id,
                    (string) $mark->institute_subject_id
                ));
                $groupedResults = $groupedMarks->map(function (Collection $groupMarks) use (
                    $subjectsById,
                    $fullMarksBySubject,
                    $passMarksBySubject,
                    $markGrading,
                    $gradeRanges
                ) {
                    $subjectIds = $groupMarks->pluck('institute_subject_id')->unique()->values();
                    $groupFullMarks = (float) $subjectIds->sum(
                        fn ($subjectId) => (float) $fullMarksBySubject->get($subjectId, 0)
                    );
                    $groupPassMarks = (float) $subjectIds->sum(
                        fn ($subjectId) => (float) $passMarksBySubject->get($subjectId, 0)
                    );
                    $groupTotalMarks = (float) $groupMarks->sum('total_marks');
                    $failedComponents = $groupMarks->contains(function (Mark $mark) use (
                        $subjectsById,
                        $markGrading,
                        $fullMarksBySubject
                    ) {
                        $subject = $subjectsById->get($mark->institute_subject_id);

                        return $mark->is_absent
                            || ($subject && $markGrading->failsMarkForExam(
                                $mark,
                                $subject,
                                (float) $fullMarksBySubject->get($mark->institute_subject_id, 0)
                            ));
                    });
                    $grade = $markGrading->gradeForExam(
                        $groupTotalMarks,
                        $groupFullMarks,
                        $groupPassMarks,
                        $gradeRanges,
                        $failedComponents
                    );
                    $failed = $grade['grade'] === 'F';

                    return [
                        'subject_ids' => $subjectIds,
                        'total_marks' => $groupTotalMarks,
                        'full_marks' => $groupFullMarks,
                        'grade_point' => (float) $grade['point'],
                        'failed' => $failed,
                    ];
                });
                $regularGroups = $groupedResults->reject(fn (array $group) => $optionalSubjectId !== null
                    && $group['subject_ids']->contains((int) $optionalSubjectId)
                );
                $hasFailedSubject = $regularGroups->contains(fn (array $group) => (bool) $group['failed']);
                $totalMarks = (float) $regularGroups->sum('total_marks');
                $totalFullMarks = (float) $regularGroups->sum('full_marks');
                $subjectPoints = $regularGroups->sum('grade_point');
                $optionalGroup = $optionalSubjectId
                    ? $groupedResults->first(fn (array $group) => $group['subject_ids']->contains((int) $optionalSubjectId))
                    : null;
                $optionalGradePoint = $optionalGroup && ! $optionalGroup['failed']
                    ? (float) $optionalGroup['grade_point']
                    : 0;
                $optionalBonus = max(0, $optionalGradePoint - 2);
                $totalPoint = (float) $subjectPoints + $optionalBonus;
                $gpa = $regularGroups->isNotEmpty() && ! $hasFailedSubject
                    ? min(5, $totalPoint / $regularGroups->count())
                    : 0;
                $finalGrade = $hasFailedSubject
                    ? ($gradeRanges->first(fn ($range) => $range['name'] === 'F') ?? ['name' => 'F'])
                    : $gradeRanges->first(fn ($range) => (float) $range['point'] <= $gpa);
                $result = Result::query()->firstOrNew([
                    'exam_id' => $exam->id,
                    'student_id' => $studentInfo->student_id,
                ]);
                $result->fill([
                    'institute_class_id' => $classId,
                    'section_id' => $studentInfo->section_id,
                    'total_subjects' => $groupedResults->count(),
                    'total_marks' => $totalMarks,
                    'total_full_marks' => $totalFullMarks,
                    'total_point' => $totalPoint,
                    'gpa' => round($gpa, 2),
                    'final_grade' => is_array($finalGrade) ? $finalGrade['name'] : ($finalGrade?->name ?? 'F'),
                    'total_absent' => $marks->where('is_absent', true)->count(),
                ]);
                $result->save();
                $generatedCount++;
                }
            }

            $results = Result::query()
                ->where('exam_id', $exam->id)
                ->orderBy('student_id')
                ->get()
                ->sort(function (Result $left, Result $right) {
                    $leftPercentage = (float) $left->total_full_marks > 0
                        ? (float) $left->total_marks / (float) $left->total_full_marks
                        : 0;
                    $rightPercentage = (float) $right->total_full_marks > 0
                        ? (float) $right->total_marks / (float) $right->total_full_marks
                        : 0;

                    return ($rightPercentage <=> $leftPercentage)
                        ?: ((float) $right->total_marks <=> (float) $left->total_marks)
                        ?: ((int) $left->student_id <=> (int) $right->student_id);
                })
                ->values();

            foreach ($results->groupBy('institute_class_id') as $classResults) {
                foreach ($classResults->values() as $index => $result) {
                    $result->position = $index + 1;
                    $result->save();
                }

                foreach ($classResults->groupBy('section_id') as $sectionResults) {
                    foreach ($sectionResults->values() as $index => $result) {
                        $result->class_position = $index + 1;
                        $result->save();
                    }
                }
            }
        });

        if ($generatedCount === 0) {
            throw ValidationException::withMessages([
                'exam' => 'No students have marks entered for every subject in this exam yet.',
            ]);
        }

        return back()->with('success', "{$generatedCount} result(s) generated successfully.");
    }

    public function grades(Request $request): Response
    {
        $grades = Grade::query()
            ->where('institute_id', $request->user()->institute_id)
            ->orderByDesc('marks_from')
            ->get(['id', 'name', 'point', 'marks_from', 'marks_to', 'remarks']);

        return Inertia::render('Institute/Grades/Index', [
            'grades' => $grades,
            'canSeedDefaults' => $grades->isEmpty(),
        ]);
    }

    public function storeGrade(Request $request): RedirectResponse
    {
        $instituteId = $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned before configuring grades.');

        $gradeData = $this->validatedGradeData($request, $instituteId);

        Grade::create([
            ...$gradeData,
            'institute_id' => $instituteId,
        ]);

        return redirect()->route('grades.index')->with('success', 'Grade added successfully.');
    }

    public function updateGrade(Request $request, int $grade): RedirectResponse
    {
        $instituteId = $request->user()->institute_id;
        $grade = Grade::query()
            ->where('institute_id', $instituteId)
            ->findOrFail($grade);

        $grade->update($this->validatedGradeData($request, $instituteId, $grade->id));

        return redirect()->route('grades.index')->with('success', 'Grade updated successfully.');
    }

    public function destroyGrade(Request $request, int $grade): RedirectResponse
    {
        $grade = Grade::query()
            ->where('institute_id', $request->user()->institute_id)
            ->findOrFail($grade);

        if ($grade->results()
            ->whereHas('student', fn ($query) => $query->where('institute_id', $request->user()->institute_id))
            ->exists()) {
            throw ValidationException::withMessages([
                'grade' => 'This grade is used by existing results and cannot be deleted.',
            ]);
        }

        $grade->delete();

        return redirect()->route('grades.index')->with('success', 'Grade deleted successfully.');
    }

    public function seedDefaultGrades(Request $request): RedirectResponse
    {
        $instituteId = $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned before configuring grades.');

        if (Grade::query()->where('institute_id', $instituteId)->exists()) {
            throw ValidationException::withMessages([
                'grades' => 'Default grades can only be added before configuring any grades.',
            ]);
        }

        DB::transaction(fn () => Grade::seedDefaultGrades($instituteId));

        return redirect()->route('grades.index')->with('success', 'Standard grade ranges added successfully.');
    }

    private function validatedGradeData(Request $request, int $instituteId, ?int $ignoreGradeId = null): array
    {
        $gradeData = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grades', 'name')
                    ->where('institute_id', $instituteId)
                    ->ignore($ignoreGradeId),
            ],
            'point' => 'required|numeric|min:0|max:5',
            'marks_from' => 'required|numeric|min:0|max:100',
            'marks_to' => 'required|numeric|min:0|max:100|gte:marks_from',
            'remarks' => 'nullable|string|max:255',
        ]);

        $overlappingGrades = Grade::query()
            ->where('institute_id', $instituteId)
            ->where('marks_from', '<=', $gradeData['marks_to'])
            ->where('marks_to', '>=', $gradeData['marks_from']);

        if ($ignoreGradeId !== null) {
            $overlappingGrades->whereKeyNot($ignoreGradeId);
        }

        if ($overlappingGrades->exists()) {
            throw ValidationException::withMessages([
                'marks_from' => 'This marks range overlaps with another configured grade.',
            ]);
        }

        return $gradeData;
    }

    public function attendance(Request $request): Response
    {
        $attendance = Attendance::query()
            ->whereHas('student', function ($query) use ($request) {
                $query->where('institute_id', $request->user()->institute_id);
            })
            ->with(['student:id,name,student_id,roll_no', 'instituteClass:id,name', 'section:id,name'])
            ->latest('date')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Institute/Attendance/Index', [
            'attendance' => $attendance,
        ]);
    }

    private function instituteResults(Request $request): Builder
    {
        return Result::query()
            ->whereHas('student', function ($query) use ($request) {
                $query->where('institute_id', $request->user()->institute_id);
            })
            ->whereHas('exam.instituteClass', function ($query) use ($request) {
                $query->where('institute_id', $request->user()->institute_id);
            });
    }
}
