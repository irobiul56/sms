<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassRoutine;
use App\Models\CombinedResult;
use App\Models\Exam;
use App\Models\Homework;
use App\Models\Mark;
use App\Models\Notice;
use App\Models\Result;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\StudentHomeworkCompletion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $student = Student::query()
            ->where('user_id', Auth::id())
            ->with('institute')
            ->firstOrFail();

        $academicInfo = StudentAcademicInfo::query()
            ->with(['academicYear', 'instituteClass', 'section'])
            ->where('student_id', $student->id)
            ->whereHas('academicYear', fn ($query) => $query->where('is_current', true))
            ->first();

        if (! $academicInfo) {
            $academicInfo = StudentAcademicInfo::query()
                ->with(['academicYear', 'instituteClass', 'section'])
                ->where('student_id', $student->id)
                ->latest('academic_year_id')
                ->first();
        }

        $academicInfos = StudentAcademicInfo::query()
            ->where('student_id', $student->id)
            ->get(['academic_year_id', 'institute_class_id', 'section_id']);
        $combinedResults = CombinedResult::query()
            ->where('institute_id', $student->institute_id)
            ->where(function ($query) use ($academicInfos) {
                foreach ($academicInfos as $info) {
                    $query->orWhere(function ($query) use ($info) {
                        $query->where('academic_year_id', $info->academic_year_id)
                            ->where('institute_class_id', $info->institute_class_id)
                            ->where(function ($query) use ($info) {
                                $query->whereNull('section_id')
                                    ->orWhere('section_id', $info->section_id);
                            });
                    });
                }
            })
            ->latest('finalized_at')
            ->get()
            ->map(function (CombinedResult $result) use ($student) {
                $report = $result->report;
                $row = collect($report['rows'] ?? [])->first(
                    fn (array $candidate) => (int) data_get($candidate, 'student.id') === $student->id
                );
                if (! $row) {
                    return null;
                }

                $subjects = collect($report['subjects'] ?? [])->keyBy('key');
                $exams = collect($report['exams'] ?? []);

                return [
                    'id' => 'combined-' . $result->id,
                    'exam_name' => 'কম্বাইন: ' . $exams
                        ->map(fn (array $exam) => $exam['bn_name'] ?: $exam['name'])
                        ->implode(' + '),
                    'exam_date' => $result->finalized_at?->toDateString(),
                    'gpa' => $row['gpa'],
                    'grade' => $row['grade'],
                    'position' => $row['merit_rank'],
                    'total_marks' => $row['total_marks'],
                    'total_full_marks' => $row['total_full_marks'],
                    'combined' => true,
                    'mode' => $report['mode'],
                    'exams' => $exams->map(fn (array $exam) => [
                        'name' => $exam['bn_name'] ?: $exam['name'],
                        'allocation' => $exam['allocation'],
                    ])->values(),
                    'marks' => collect($row['subjects'] ?? [])
                        ->filter(fn (array $subject) => $subject['applies'] ?? false)
                        ->map(fn (array $subject) => [
                            'subject' => data_get($subjects->get($subject['key']), 'bn_name')
                                ?: data_get($subjects->get($subject['key']), 'name')
                                ?: 'বিষয়',
                            'score' => $subject['obtained'],
                            'full_marks' => $subject['full_marks'],
                            'grade' => $subject['grade'],
                            'absent' => collect($subject['components'] ?? [])
                                ->every(fn (array $component) => $component['is_absent']),
                            'incomplete' => $subject['incomplete'],
                            'components' => collect($subject['components'] ?? [])
                                ->map(function (array $component) use ($exams) {
                                    $exam = $exams->firstWhere('id', $component['exam_id']);

                                    return [
                                        'exam_name' => $exam['bn_name'] ?: $exam['name'],
                                        'allocation' => $component['allocation'],
                                        'obtained' => $component['obtained'],
                                        'full_marks' => $component['full_marks'],
                                        'original_marks' => $component['original_marks'],
                                        'original_full_marks' => $component['original_full_marks'],
                                    ];
                                })
                                ->values(),
                        ])
                        ->values(),
                ];
            })
            ->filter()
            ->values();

        $results = Result::query()
            ->with('exam:id,name,bn_name,start_date')
            ->where('student_id', $student->id)
            ->where('is_published', true)
            ->latest()
            ->limit(10)
            ->get();

        $marksByExam = Mark::query()
            ->with('instituteSubject:id,name,bn_name')
            ->where('student_id', $student->id)
            ->whereIn('exam_id', $results->pluck('exam_id'))
            ->get()
            ->groupBy('exam_id');

        $attendanceMonth = now()->startOfMonth();
        $requestedMonth = request()->query('month');
        if (is_string($requestedMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $requestedMonth)) {
            [$year, $month] = array_map('intval', explode('-', $requestedMonth));
            if (
                checkdate($month, 1, $year)
                && $requestedMonth <= now()->format('Y-m')
                && $requestedMonth >= now()->subMonths(11)->format('Y-m')
            ) {
                $attendanceMonth = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $requestedMonth)->startOfMonth();
            }
        }
        $attendanceRecords = Attendance::query()
            ->where('student_id', $student->id)
            ->whereYear('date', $attendanceMonth->year)
            ->whereMonth('date', $attendanceMonth->month)
            ->orderBy('date')
            ->get(['date', 'status']);
        $workingDays = $attendanceRecords->where('status', '!=', 'holiday')->count();
        $presentDays = $attendanceRecords->whereIn('status', ['present', 'late', 'half_day'])->count();

        $examRoutine = collect();
        $today = now()->toDateString();
        $now = now();
        $nextSubjectId = null;
        if ($academicInfo?->institute_class_id) {
            $classId = $academicInfo->institute_class_id;
            $examRoutine = Exam::query()
                ->with([
                    'examSubjects' => fn ($subjects) => $subjects
                        ->where('institute_class_id', $classId)
                        ->with('instituteSubject:id,name,bn_name')
                        ->orderBy('exam_date')
                        ->orderBy('start_time'),
                ])
                ->where('academic_year_id', $academicInfo->academic_year_id)
                ->where(function ($query) use ($classId) {
                    $query->where('institute_class_id', $classId)
                        ->orWhereHas('classes', fn ($classes) => $classes->whereKey($classId));
                })
                ->where('is_published', true)
                ->orderBy('start_date')
                ->get();

            $routineSubjects = $examRoutine
                ->flatMap(fn (Exam $exam) => $exam->examSubjects)
                ->sortBy(fn ($subject) => $subject->exam_date->toDateString() . ' ' . ($subject->start_time?->format('H:i:s') ?? '00:00:00'))
                ->values();

            $hasCompleted = false;
            $ongoingSubject = null;
            foreach ($routineSubjects as $subject) {
                $date = $subject->exam_date?->toDateString();
                $start = $date && $subject->start_time
                    ? Carbon::parse($date . ' ' . $subject->start_time->format('H:i:s'))
                    : null;
                $end = $date && $subject->end_time
                    ? Carbon::parse($date . ' ' . $subject->end_time->format('H:i:s'))
                    : null;

                if ($end && $end->lte($now)) {
                    $hasCompleted = true;
                } elseif ($start && $end && $start->lte($now) && $now->lt($end)) {
                    $ongoingSubject = $subject;
                }
            }

            if ($ongoingSubject) {
                $nextSubjectId = $routineSubjects
                    ->first(fn ($subject) => $subject->id !== $ongoingSubject->id
                        && $subject->exam_date?->toDateString() . ' ' . ($subject->start_time?->format('H:i:s') ?? '00:00:00')
                            >= $ongoingSubject->exam_date?->toDateString() . ' ' . ($ongoingSubject->start_time?->format('H:i:s') ?? '00:00:00'))
                        ?->id;
            } elseif ($hasCompleted) {
                $nextSubjectId = $routineSubjects
                    ->first(function ($subject) use ($now) {
                        $date = $subject->exam_date?->toDateString();
                        $end = $date && $subject->end_time
                            ? Carbon::parse($date . ' ' . $subject->end_time->format('H:i:s'))
                            : null;

                        return $end && $end->gt($now);
                    })?->id;
            } else {
                $firstToday = $routineSubjects->first(
                    fn ($subject) => $subject->exam_date?->toDateString() === $today
                        && $subject->end_time
                        && Carbon::parse($today . ' ' . $subject->end_time->format('H:i:s'))->gte(now())
                );
                $nextSubjectId = $firstToday
                    ? null
                    : $routineSubjects
                        ->first(fn ($subject) => $subject->exam_date?->toDateString() >= $today)
                        ?->id;
            }
        }

        $classRoutine = collect();
        if ($academicInfo?->institute_class_id) {
            $classRoutine = ClassRoutine::query()
                ->where('institute_id', $student->institute_id)
                ->where('academic_year_id', $academicInfo->academic_year_id)
                ->where('institute_class_id', $academicInfo->institute_class_id)
                ->where(function ($query) use ($academicInfo) {
                    $query->whereNull('section_id');
                    if ($academicInfo->section_id) {
                        $query->orWhere('section_id', $academicInfo->section_id);
                    }
                })
                ->with(['instituteSubject:id,name,bn_name', 'teacher:id,name,bn_name'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->map(fn (ClassRoutine $item) => [
                    'id' => $item->id,
                    'day_of_week' => $item->day_of_week,
                    'subject' => $item->instituteSubject?->bn_name ?: $item->instituteSubject?->name,
                    'teacher' => $item->teacher?->bn_name ?: $item->teacher?->name,
                    'start_time' => substr($item->start_time, 0, 5),
                    'end_time' => substr($item->end_time, 0, 5),
                    'classroom' => $item->classroom,
                ])->values();
        }

        $notices = Notice::query()
            ->where('institute_id', $student->institute_id)
            ->where('is_active', true)
            ->whereDate('publish_date', '<=', now()->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('expire_date')
                ->orWhereDate('expire_date', '>=', now()->toDateString()))
            ->orderByDesc('publish_date')
            ->limit(6)
            ->get(['id', 'title', 'description', 'publish_date', 'priority']);

        $homework = collect();
        if ($academicInfo?->institute_class_id) {
            $homeworkItems = Homework::query()
                ->with(['instituteSubject:id,name,bn_name', 'teacher:id,name'])
                ->where('institute_id', $student->institute_id)
                ->where('academic_year_id', $academicInfo->academic_year_id)
                ->where('institute_class_id', $academicInfo->institute_class_id)
                ->where(function ($query) use ($academicInfo) {
                    $query->whereNull('section_id');
                    if ($academicInfo->section_id) {
                        $query->orWhere('section_id', $academicInfo->section_id);
                    }
                })
                ->where('is_published', true)
                ->orderBy('due_date')
                ->get();
            $completedHomeworkIds = StudentHomeworkCompletion::query()
                ->where('student_id', $student->id)
                ->whereIn('homework_id', $homeworkItems->pluck('id'))
                ->pluck('homework_id')
                ->flip();

            $homework = $homeworkItems->map(fn (Homework $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'due_date' => $item->due_date->toDateString(),
                'subject' => $item->instituteSubject?->bn_name ?: $item->instituteSubject?->name,
                'teacher' => $item->teacher?->name,
                'is_completed' => $completedHomeworkIds->has($item->id),
            ]);
        }

        return Inertia::render('Student/Dashboard', [
            'student' => [
                'name' => $student->bn_name ?: $student->name,
                'student_id' => $student->student_id,
                'photo' => $student->photo ? asset('storage/' . $student->photo) : null,
                'class' => $academicInfo?->instituteClass?->name,
                    'group' => match ($academicInfo?->instituteClass?->group) {
                        'science' => 'বিজ্ঞান বিভাগ',
                        'commerce' => 'বাণিজ্য বিভাগ',
                        'arts' => 'মানবিক বিভাগ',
                        'general' => 'সাধারণ বিভাগ',
                        default => null,
                    },
                'section' => $academicInfo?->section?->name,
                'roll' => $academicInfo?->roll_number ?? $student->roll_no,
                'year' => $academicInfo?->academicYear?->session,
                'institute' => [
                    'name' => $student->institute?->name ?? config('app.name'),
                    'logo' => $student->institute?->logo
                        ? asset('storage/' . $student->institute->logo)
                        : null,
                ],
            ],
            'results' => $results->map(fn (Result $result) => [
                'id' => $result->id,
                'exam_name' => $result->exam?->bn_name ?: $result->exam?->name,
                'exam_date' => $result->exam?->start_date?->toDateString(),
                'gpa' => $result->gpa,
                'grade' => $result->final_grade,
                'position' => $result->position,
                'total_marks' => $result->total_marks,
                'total_full_marks' => $result->total_full_marks,
                'remarks' => $result->remarks,
                'marks' => $marksByExam->get($result->exam_id, collect())->map(fn (Mark $mark) => [
                    'subject' => $mark->instituteSubject?->bn_name ?: $mark->instituteSubject?->name,
                    'score' => $mark->total_marks,
                    'full_marks' => $mark->instituteSubject?->full_marks,
                    'grade' => $mark->grade,
                    'absent' => $mark->is_absent,
                ])->values(),
            ])->values(),
            'combinedResults' => $combinedResults,
            'attendance' => [
                'month' => $attendanceMonth->format('Y-m'),
                'month_label' => $attendanceMonth->copy()->locale('bn')->translatedFormat('F Y'),
                'summary' => [
                    'working_days' => $workingDays,
                    'present' => $presentDays,
                    'absent' => $attendanceRecords->where('status', 'absent')->count(),
                    'late' => $attendanceRecords->where('status', 'late')->count(),
                    'percentage' => $workingDays > 0 ? round($presentDays / $workingDays * 100) : 0,
                ],
                'days' => $attendanceRecords->map(fn ($attendance) => [
                    'date' => \Illuminate\Support\Carbon::parse($attendance->date)->toDateString(),
                    'status' => $attendance->status,
                ])->values(),
            ],
            'examRoutine' => $examRoutine->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->bn_name ?: $exam->name,
                'start_date' => $exam->start_date->toDateString(),
                'end_date' => $exam->end_date->toDateString(),
                'subjects' => $exam->examSubjects->map(function ($examSubject) use ($today, $now, $nextSubjectId) {
                    $date = $examSubject->exam_date?->toDateString();
                    $start = $date && $examSubject->start_time
                        ? Carbon::parse($date . ' ' . $examSubject->start_time->format('H:i:s'))
                        : null;
                    $end = $date && $examSubject->end_time
                        ? Carbon::parse($date . ' ' . $examSubject->end_time->format('H:i:s'))
                        : null;

                    if ($end && $end->lte($now)) {
                        $status = 'completed';
                    } elseif ($start && $end && $start->lte($now) && $now->lt($end)) {
                        $status = 'ongoing';
                    } elseif ($date === $today) {
                        $status = 'today';
                    } else {
                        $status = 'upcoming';
                    }

                    return [
                        'id' => $examSubject->id,
                        'name' => $examSubject->instituteSubject?->bn_name ?: $examSubject->instituteSubject?->name,
                        'date' => $date,
                        'start_time' => $examSubject->start_time?->format('H:i'),
                        'end_time' => $examSubject->end_time?->format('H:i'),
                        'full_marks' => $examSubject->full_marks,
                        'status' => $status,
                        'is_next' => $examSubject->id === $nextSubjectId,
                    ];
                })->values(),
            ])->values(),
            'classRoutine' => $classRoutine,
            'notices' => $notices->map(fn (Notice $notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'description' => $notice->description,
                'publish_date' => $notice->publish_date,
                'priority' => $notice->priority,
            ])->values(),
            'homework' => $homework,
        ]);
    }
}
