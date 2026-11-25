<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CombinedResult;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Mark;
use App\Models\Section;
use App\Models\StudentAcademicInfo;
use App\Services\MarkGradingService;
use App\Services\SubjectGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CombinedResultController extends Controller
{
    public function index(Request $request): Response
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned to this account.');

        $classes = InstituteClass::query()
            ->where('institute_id', $instituteId)
            ->orderBy('numeric_value')
            ->orderBy('name')
            ->get(['id', 'name', 'bn_name', 'group']);
        $academicYears = AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->orderByDesc('year')
            ->get(['id', 'session', 'year']);

        $filters = [
            'class_id' => (string) $request->query('class_id', ''),
            'academic_year_id' => (string) $request->query('academic_year_id', ''),
            'section_id' => (string) $request->query('section_id', ''),
            'exam_ids' => [],
            'mode' => 'percentage',
            'allocations' => [],
        ];
        $class = $classes->firstWhere('id', (int) $filters['class_id']);
        $academicYear = $academicYears->firstWhere('id', (int) $filters['academic_year_id']);
        if (! $class) {
            $filters['class_id'] = '';
        }
        if (! $academicYear) {
            $filters['academic_year_id'] = '';
        }

        $exams = collect();
        $sections = collect();
        if ($filters['class_id'] !== '' && $filters['academic_year_id'] !== '') {
            $exams = Exam::query()
                ->where('academic_year_id', $filters['academic_year_id'])
                ->where(function ($query) use ($filters) {
                    $query->where('institute_class_id', $filters['class_id'])
                        ->orWhereHas('classes', fn ($classes) => $classes->whereKey($filters['class_id']));
                })
                ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
                ->with(['instituteClass:id,name', 'classes:id,name'])
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'bn_name', 'institute_class_id', 'academic_year_id']);
            $sections = Section::query()
                ->where('institute_id', $instituteId)
                ->where('institute_class_id', $filters['class_id'])
                ->orderBy('name')
                ->get(['id', 'name', 'institute_class_id']);
        }

        $section = $sections->firstWhere('id', (int) $filters['section_id']);
        if (! $section) {
            $filters['section_id'] = '';
        }

        $report = null;
        if ($request->query->has('exam_ids')) {
            $validated = $request->validate([
                'class_id' => ['required', 'integer'],
                'academic_year_id' => ['required', 'integer'],
                'section_id' => ['nullable', 'integer'],
                'exam_ids' => ['required', 'array', 'min:2', 'max:12'],
                'exam_ids.*' => ['required', 'integer', 'distinct'],
                'mode' => ['required', 'in:percentage,conversion'],
                'allocations' => ['required', 'array'],
            ]);

            if ($class === null || $academicYear === null) {
                throw ValidationException::withMessages([
                    'class_id' => 'শ্রেণি ও শিক্ষাবর্ষ নির্বাচন করুন।',
                ]);
            }

            $selectedIds = collect($validated['exam_ids'])->map(fn ($id) => (int) $id);
            if ($selectedIds->diff($exams->pluck('id'))->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'exam_ids' => 'নির্বাচিত পরীক্ষাগুলো এই শ্রেণি ও শিক্ষাবর্ষের নয়।',
                ]);
            }
            if ($filters['section_id'] !== '' && ! $sections->contains('id', (int) $filters['section_id'])) {
                throw ValidationException::withMessages([
                    'section_id' => 'নির্বাচিত সেকশনটি এই শ্রেণির নয়।',
                ]);
            }

            $allocationRules = [];
            foreach ($selectedIds as $examId) {
                $allocationRules["allocations.{$examId}"] = [
                    'required',
                    'numeric',
                    'gt:0',
                    $validated['mode'] === 'percentage' ? 'lte:100' : 'lte:1000',
                ];
            }
            $request->validate($allocationRules);

            $allocations = collect($validated['allocations'])
                ->only($selectedIds->map(fn ($id) => (string) $id)->all())
                ->map(fn ($value) => (float) $value);
            if ($validated['mode'] === 'percentage' && abs($allocations->sum() - 100) > 0.01) {
                throw ValidationException::withMessages([
                    'allocations' => 'শতাংশ পদ্ধতিতে সব পরীক্ষার ওজনের যোগফল ১০০% হতে হবে।',
                ]);
            }

            $filters['exam_ids'] = $selectedIds->map(fn ($id) => (string) $id)->all();
            $filters['mode'] = $validated['mode'];
            $filters['allocations'] = $allocations->map(fn ($value) => (string) $value)->all();
            $report = $this->buildReport(
                $instituteId,
                $class,
                $academicYear,
                $filters['section_id'] !== '' ? (int) $filters['section_id'] : null,
                $exams->whereIn('id', $selectedIds)->values(),
                $allocations,
                $validated['mode']
            );
        }

        $institute = Institute::query()->whereKey($instituteId)->firstOrFail();

        return Inertia::render('Institute/CombinedResults/Index', [
            'classes' => $classes,
            'academicYears' => $academicYears,
            'exams' => $exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'bn_name' => $exam->bn_name,
                'class_name' => $exam->classes->isNotEmpty()
                    ? $exam->classes->pluck('name')->implode(', ')
                    : $exam->instituteClass?->name,
            ]),
            'sections' => $sections,
            'filters' => $filters,
            'report' => $report,
            'institute' => [
                ...$institute->only(['name', 'bn_name', 'address', 'phone']),
                'logo_url' => $institute->logo
                    ? asset('storage/' . ltrim($institute->logo, '/'))
                    : null,
            ],
        ]);
    }

    public function generate(Request $request)
    {
        $selection = $this->resolveSelection($request);

        return redirect()->route('combined-results.index', [
            'class_id' => $selection['class']->id,
            'academic_year_id' => $selection['academicYear']->id,
            'section_id' => $selection['sectionId'] ?? '',
            'exam_ids' => $selection['exams']->pluck('id')->all(),
            'mode' => $selection['mode'],
            'allocations' => $selection['allocations']->all(),
        ]);
    }

    public function finalize(Request $request)
    {
        $selection = $this->resolveSelection($request);
        $report = $this->buildReport(
            $selection['instituteId'],
            $selection['class'],
            $selection['academicYear'],
            $selection['sectionId'],
            $selection['exams'],
            $selection['allocations'],
            $selection['mode']
        );
        $selectionKey = hash('sha256', json_encode([
            'class_id' => $selection['class']->id,
            'academic_year_id' => $selection['academicYear']->id,
            'section_id' => $selection['sectionId'],
            'exam_ids' => $selection['exams']->pluck('id')->sort()->values()->all(),
            'mode' => $selection['mode'],
            'allocations' => $selection['allocations']->sortKeys()->all(),
        ]));

        CombinedResult::query()->updateOrCreate(
            [
                'institute_id' => $selection['instituteId'],
                'selection_key' => $selectionKey,
            ],
            [
                'institute_class_id' => $selection['class']->id,
                'academic_year_id' => $selection['academicYear']->id,
                'section_id' => $selection['sectionId'],
                'report' => $report,
                'finalized_by' => $request->user()->id,
                'finalized_at' => now(),
            ]
        );

        return redirect()->route('combined-results.index', [
            'class_id' => $selection['class']->id,
            'academic_year_id' => $selection['academicYear']->id,
            'section_id' => $selection['sectionId'] ?? '',
            'exam_ids' => $selection['exams']->pluck('id')->all(),
            'mode' => $selection['mode'],
            'allocations' => $selection['allocations']->all(),
        ])->with('success', 'কম্বাইন ফলাফল চূড়ান্ত করে শিক্ষার্থীদের জন্য প্রকাশ করা হয়েছে।');
    }

    private function resolveSelection(Request $request): array
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'exam_ids' => ['required', 'array', 'min:2', 'max:12'],
            'exam_ids.*' => ['required', 'integer', 'distinct'],
            'mode' => ['required', 'in:percentage,conversion'],
            'allocations' => ['required', 'array'],
        ]);
        $instituteId = (int) $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned to this account.');

        $class = InstituteClass::query()
            ->where('institute_id', $instituteId)
            ->find($validated['class_id']);
        $academicYear = AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->find($validated['academic_year_id']);
        if (! $class || ! $academicYear) {
            throw ValidationException::withMessages([
                'class_id' => 'শ্রেণি ও শিক্ষাবর্ষ নির্বাচন করুন।',
            ]);
        }

        $exams = Exam::query()
            ->where('academic_year_id', $academicYear->id)
            ->where(function ($query) use ($class) {
                $query->where('institute_class_id', $class->id)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereKey($class->id));
            })
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->whereIn('id', $validated['exam_ids'])
            ->get(['id', 'name', 'bn_name', 'institute_class_id', 'academic_year_id']);
        $selectedIds = collect($validated['exam_ids'])->map(fn ($id) => (int) $id);
        if ($selectedIds->diff($exams->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'exam_ids' => 'নির্বাচিত পরীক্ষাগুলো এই শ্রেণি ও শিক্ষাবর্ষের নয়।',
            ]);
        }

        $sectionId = $validated['section_id'] ?? null;
        if ($sectionId && ! Section::query()
            ->where('id', $sectionId)
            ->where('institute_id', $instituteId)
            ->where('institute_class_id', $class->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'section_id' => 'নির্বাচিত সেকশনটি এই শ্রেণির নয়।',
            ]);
        }

        $allocationRules = [];
        foreach ($selectedIds as $examId) {
            $allocationRules["allocations.{$examId}"] = [
                'required',
                'numeric',
                'gt:0',
                $validated['mode'] === 'percentage' ? 'lte:100' : 'lte:1000',
            ];
        }
        $allocations = collect($request->validate($allocationRules)['allocations'])
            ->only($selectedIds->map(fn ($id) => (string) $id)->all())
            ->map(fn ($value) => (float) $value);
        if ($validated['mode'] === 'percentage' && abs($allocations->sum() - 100) > 0.01) {
            throw ValidationException::withMessages([
                'allocations' => 'শতাংশ পদ্ধতিতে সব পরীক্ষার ওজনের যোগফল ১০০% হতে হবে।',
            ]);
        }

        return [
            'instituteId' => $instituteId,
            'class' => $class,
            'academicYear' => $academicYear,
            'sectionId' => $sectionId ? (int) $sectionId : null,
            'exams' => $selectedIds->map(fn ($id) => $exams->firstWhere('id', $id))->values(),
            'allocations' => $allocations,
            'mode' => $validated['mode'],
        ];
    }

    private function buildReport(
        int $instituteId,
        InstituteClass $class,
        AcademicYear $academicYear,
        ?int $sectionId,
        Collection $exams,
        Collection $allocations,
        string $mode
    ): array {
        $roster = StudentAcademicInfo::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('institute_class_id', $class->id)
            ->when($sectionId, fn ($query) => $query->where('section_id', $sectionId))
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->with(['student', 'section'])
            ->orderBy('roll_number')
            ->get()
            ->unique('student_id')
            ->sort(fn (StudentAcademicInfo $first, StudentAcademicInfo $second) => strnatcasecmp(
                (string) $first->roll_number,
                (string) $second->roll_number
            ))
            ->values();

        $examIds = $exams->pluck('id');
        $examSubjects = ExamInstituteSubjec::query()
            ->whereIn('exam_id', $examIds)
            ->where('institute_class_id', $class->id)
            ->whereHas('instituteSubject', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('instituteSubject:id,name,bn_name')
            ->get(['id', 'exam_id', 'institute_subject_id', 'full_marks', 'pass_marks']);

        $hasClassAssignments = DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $class->id)
            ->whereIn('institute_subject_id', $examSubjects->pluck('institute_subject_id'))
            ->exists();
        if ($hasClassAssignments) {
            $classSubjectIds = DB::table('institute_class_institute_subjects')
                ->where('institute_class_id', $class->id)
                ->whereIn('institute_subject_id', $examSubjects->pluck('institute_subject_id'))
                ->pluck('institute_subject_id');
            $examSubjects = $examSubjects->whereIn('institute_subject_id', $classSubjectIds)->values();
        }

        $subjectGroups = app(SubjectGroupService::class)->forClass(
            (int) $class->id,
            $examSubjects->pluck('institute_subject_id')
        );
        $subjects = $subjectGroups
            ->map(function (array $group) use ($examSubjects) {
                $groupSubjects = $examSubjects
                    ->whereIn('institute_subject_id', $group['subject_ids'])
                    ->unique('institute_subject_id')
                    ->values();
                $firstSubject = $groupSubjects->first()?->instituteSubject;

                return [
                    'id' => (int) $group['subject_ids']->first(),
                    'key' => $group['key'],
                    'subject_ids' => $group['subject_ids'],
                    'name' => $group['name'] ?? $firstSubject?->name ?? 'বিষয়',
                    'bn_name' => $group['name'] ? null : $firstSubject?->bn_name,
                ];
            })
            ->values();
        $examSubjectMap = $examSubjects->keyBy(
            fn (ExamInstituteSubjec $subject) => "{$subject->exam_id}:{$subject->institute_subject_id}"
        );

        $marks = Mark::query()
            ->whereIn('exam_id', $examIds)
            ->whereIn('student_id', $roster->pluck('student_id'))
            ->get()
            ->keyBy(fn (Mark $mark) => "{$mark->exam_id}:{$mark->student_id}:{$mark->institute_subject_id}");

        $choiceSubjects = DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $class->id)
            ->whereIn('type', ['elective', 'optional', 'additional'])
            ->get(['type', 'institute_subject_id'])
            ->groupBy('type')
            ->map(fn ($rows) => $rows->pluck('institute_subject_id')->map(fn ($id) => (int) $id));
        $grading = app(MarkGradingService::class);
        $gradeRanges = $grading->gradeRangesForInstitute($instituteId);

        $rows = $roster->map(function (StudentAcademicInfo $info) use (
            $subjects,
            $exams,
            $allocations,
            $examSubjectMap,
            $marks,
            $choiceSubjects,
            $gradeRanges,
            $grading
        ) {
            $selectedSubjects = collect([
                $info->elective_subject_id,
                $info->optional_subject_id,
                $info->additional_subject_id,
            ])->filter()->map(fn ($id) => (int) $id);
            $subjectRows = [];
            $totalMarks = 0.0;
            $totalFullMarks = 0.0;
            $regularPoints = 0.0;
            $regularSubjects = 0;
            $optionalPoint = 0.0;
            $failedRegularSubject = false;
            $incomplete = false;

            foreach ($subjects as $subject) {
                $subjectIds = collect($subject['subject_ids'])->map(fn ($id) => (int) $id);
                $isChoice = $subjectIds->contains(fn ($subjectId) => collect(['elective', 'optional', 'additional'])->contains(
                    fn ($type) => $choiceSubjects->get($type, collect())->contains($subjectId)
                ));
                if ($isChoice && $selectedSubjects->intersect($subjectIds)->isEmpty()) {
                    $subjectRows[] = ['id' => $subject['id'], 'key' => $subject['key'], 'applies' => false];

                    continue;
                }

                $components = [];
                $obtained = 0.0;
                $fullMarks = 0.0;
                $passMarks = 0.0;
                $subjectIncomplete = false;
                foreach ($exams as $exam) {
                    $allocation = (float) $allocations->get($exam->id);
                    $examSubjectRows = $subjectIds
                        ->map(fn ($subjectId) => $examSubjectMap->get("{$exam->id}:{$subjectId}"))
                        ->filter(fn ($examSubject) => $examSubject && (float) $examSubject->full_marks > 0)
                        ->values();
                    $examFullMarks = (float) $examSubjectRows->sum('full_marks');
                    if ($examFullMarks <= 0) {
                        continue;
                    }

                    $examMarks = $examSubjectRows->map(fn (ExamInstituteSubjec $examSubject) => $marks->get(
                        "{$exam->id}:{$info->student_id}:{$examSubject->institute_subject_id}"
                    ));
                    $hasMissingMarks = $examMarks->contains(fn (?Mark $mark) => ! $mark);
                    $examObtained = (float) $examMarks->sum(
                        fn (?Mark $mark) => $mark && ! $mark->is_absent ? (float) $mark->total_marks : 0
                    );
                    $targetMarks = $allocation;
                    $converted = ($examObtained / $examFullMarks) * $targetMarks;
                    $fullMarks += $targetMarks;
                    $examPassMarks = (float) $examSubjectRows->sum('pass_marks');
                    $passMarks += ($examPassMarks / $examFullMarks) * $targetMarks;
                    $obtained += $converted;
                    $subjectIncomplete = $subjectIncomplete || $hasMissingMarks;
                    $components[] = [
                        'exam_id' => $exam->id,
                        'exam_name' => $exam->name,
                        'allocation' => $allocation,
                        'obtained' => $hasMissingMarks ? null : round($converted, 2),
                        'full_marks' => round($targetMarks, 2),
                        'original_marks' => $hasMissingMarks ? null : round($examObtained, 2),
                        'original_full_marks' => $examFullMarks,
                        'is_absent' => $examMarks->isNotEmpty() && $examMarks->every(fn (?Mark $mark) => $mark?->is_absent),
                    ];
                }

                if ($fullMarks <= 0) {
                    $subjectRows[] = ['id' => $subject['id'], 'key' => $subject['key'], 'applies' => false];

                    continue;
                }

                $grade = $subjectIncomplete
                    ? ['grade' => '—', 'point' => 0.0]
                    : $grading->gradeForExam($obtained, $fullMarks, $passMarks, $gradeRanges);
                $totalMarks += $obtained;
                $totalFullMarks += $fullMarks;
                $incomplete = $incomplete || $subjectIncomplete;
                if ($subjectIds->contains((int) $info->optional_subject_id)) {
                    $optionalPoint = $grade['point'];
                } else {
                    $regularSubjects++;
                    $regularPoints += $grade['point'];
                    $failedRegularSubject = $failedRegularSubject || $grade['grade'] === 'F';
                }
                $subjectRows[] = [
                    'id' => $subject['id'],
                    'key' => $subject['key'],
                    'subject_ids' => $subjectIds,
                    'applies' => true,
                    'obtained' => round($obtained, 2),
                    'full_marks' => round($fullMarks, 2),
                    'pass_marks' => round($passMarks, 2),
                    'grade' => $grade['grade'],
                    'point' => round($grade['point'], 2),
                    'incomplete' => $subjectIncomplete,
                    'components' => $components,
                ];
            }

            $gpa = $regularSubjects > 0 && ! $failedRegularSubject && ! $incomplete
                ? min(5, ($regularPoints + max(0, $optionalPoint - 2)) / $regularSubjects)
                : 0;
            $finalGrade = 'F';
            if ($incomplete) {
                $finalGrade = 'অসম্পূর্ণ';
            } elseif (! $failedRegularSubject && $regularSubjects > 0) {
                $finalGrade = data_get(
                    $gradeRanges->first(fn ($range) => (float) data_get($range, 'point') <= $gpa),
                    'name',
                    'F'
                );
            }

            return [
                'student' => [
                    'id' => $info->student_id,
                    'name' => $info->student?->name,
                    'bn_name' => $info->student?->bn_name,
                    'student_id' => $info->student?->student_id,
                    'father_name' => $info->student?->father_name,
                    'mother_name' => $info->student?->mother_name,
                    'roll_number' => $info->roll_number,
                    'section_name' => $info->section?->name,
                ],
                'subjects' => $subjectRows,
                'total_marks' => round($totalMarks, 2),
                'total_full_marks' => round($totalFullMarks, 2),
                'gpa' => round($gpa, 2),
                'grade' => $finalGrade,
                'incomplete' => $incomplete,
            ];
        });
        $rankedRows = $rows
            ->filter(fn (array $row) => ! $row['incomplete'])
            ->sort(function (array $first, array $second) {
                $firstPercentage = $first['total_full_marks'] > 0
                    ? $first['total_marks'] / $first['total_full_marks']
                    : 0;
                $secondPercentage = $second['total_full_marks'] > 0
                    ? $second['total_marks'] / $second['total_full_marks']
                    : 0;

                return ($secondPercentage <=> $firstPercentage)
                    ?: ($second['total_marks'] <=> $first['total_marks'])
                    ?: ($first['student']['id'] <=> $second['student']['id']);
            })
            ->values()
            ->mapWithKeys(fn (array $row, int $index) => [$row['student']['id'] => $index + 1]);
        $rows = $rows->map(function (array $row) use ($rankedRows) {
            $row['merit_rank'] = $rankedRows->get($row['student']['id']);

            return $row;
        });

        return [
            'class_id' => (int) $class->id,
            'academic_year_id' => (int) $academicYear->id,
            'section_id' => $sectionId ?? '',
            'class_name' => $class->name,
            'class_bn_name' => $class->bn_name,
            'academic_year' => $academicYear->session,
            'section_name' => $sectionId ? Section::find($sectionId)?->name : 'সকল সেকশন',
            'mode' => $mode,
            'exams' => $exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'bn_name' => $exam->bn_name,
                'allocation' => (float) $allocations->get($exam->id),
            ])->values(),
            'subjects' => $subjects,
            'rows' => $rows,
        ];
    }
}
