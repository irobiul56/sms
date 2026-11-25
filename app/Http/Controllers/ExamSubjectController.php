<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\InstituteSubject;
use App\Models\AcademicYear;
use App\Models\ExamInstituteSubjec;
use App\Models\InstituteClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamSubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
      public function index()
    {
        $instituteId = Auth::user()->institute_id;

        // Get all exam subjects with relationships
        $examSubjects = ExamInstituteSubjec::with(['exam', 'instituteClass', 'instituteSubject'])
            ->whereHas('exam', function($query) use ($instituteId) {
                $query->whereHas('instituteClass', function($q) use ($instituteId) {
                    $q->where('institute_id', $instituteId);
                });
            })
            ->orderBy('exam_date', 'desc')
            ->get()
            ->map(function($examSubject) {
                return [
                    'id' => $examSubject->id,
                    'exam_id' => $examSubject->exam_id,
                    'exam_name' => $examSubject->exam->name ?? 'N/A',
                    'exam_type' => $examSubject->exam->type_label ?? 'N/A',
                    'exam_status' => $examSubject->exam->status_label ?? 'N/A',
                    'exam_status_badge_class' => $examSubject->exam->status_badge_class ?? 'bg-gray-100 text-gray-800',
                    'class_name' => $examSubject->instituteClass->name ?? 'N/A',
                    'class_id' => $examSubject->institute_class_id,
                    'institute_subject_id' => $examSubject->institute_subject_id,
                    'subject_name' => $examSubject->instituteSubject->name ?? 'N/A',
                    'subject_code' => $examSubject->instituteSubject->code ?? 'N/A',
                    'exam_date' => $examSubject->exam_date,
                    'formatted_date' => $examSubject->formatted_date,
                    'start_time' => $examSubject->start_time,
                    'end_time' => $examSubject->end_time,
                    'time_range' => $examSubject->time_range,
                    'duration' => $examSubject->duration, // ✅ Using model accessor
                    'duration_minutes' => $examSubject->duration_in_minutes,
                    'full_marks' => $examSubject->full_marks,
                    'pass_marks' => $examSubject->pass_marks,
                    'created_at' => $examSubject->created_at,
                    'updated_at' => $examSubject->updated_at
                ];
            });

        // Get dropdown data
        $exams = Exam::whereHas('instituteClass', function($query) use ($instituteId) {
            $query->where('institute_id', $instituteId);
        })
        ->with(['instituteClass', 'academicYear', 'classes'])
        ->orderBy('start_date', 'desc')
        ->get()
        ->map(function($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'type' => $exam->type,
                'class_name' => $exam->classes->isNotEmpty()
                    ? $exam->classes->pluck('name')->implode(', ')
                    : ($exam->instituteClass->name ?? 'N/A'),
                'class_ids' => $exam->classIds(),
                'academic_year' => $exam->academicYear->session ?? 'N/A',
                'type_label' => $exam->type_label
            ];
        });

        $subjects = InstituteSubject::forInstitute($instituteId)
            ->with(['classes'])
            ->ordered()
            ->get(['id', 'name', 'code', 'full_marks', 'pass_marks'])
            ->map(fn (InstituteSubject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'full_marks' => $subject->full_marks,
                'pass_marks' => $subject->pass_marks,
                'class_ids' => $subject->classes->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]);
        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $stats = [
            'total' => $examSubjects->count(),
            'total_subjects' => $subjects->count(),
            'total_exams' => $exams->count()
        ];

        return Inertia::render('Institute/ExamSubjects/Index', [
            'examSubjects' => $examSubjects,
            'exams' => $exams,
            'subjects' => $subjects,
            'classes' => $classes,
            'stats' => $stats
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $instituteId = Auth::user()->institute_id;
        $usesSubjectArray = $request->exists('institute_subject_ids');
        $usesSubjectMarks = $usesSubjectArray && $request->exists('subject_marks');
        $subjectField = $usesSubjectArray ? 'institute_subject_ids' : 'institute_subject_id';

        $rules = [
            'exam_id' => [
                'required',
                Rule::exists('exams', 'id')->where(fn ($query) => $query
                    ->whereIn('institute_class_id', InstituteClass::forInstitute($instituteId)->select('id'))),
            ],
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'class_id' => [
                'nullable',
                'integer',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'class_ids' => ['nullable', 'array', 'min:1'],
            'class_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
        ];

        if ($usesSubjectArray) {
            $rules['institute_subject_ids'] = ['required', 'array', 'min:1'];
            $rules['institute_subject_ids.*'] = [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('institute_subjects', 'id')->where('institute_id', $instituteId),
            ];
            if ($usesSubjectMarks) {
                $rules['subject_marks'] = ['required', 'array'];
                $rules['subject_marks.*.full_marks'] = ['required', 'integer', 'min:1'];
                $rules['subject_marks.*.pass_marks'] = ['required', 'integer', 'min:0'];
            } else {
                $rules['full_marks'] = 'required|integer|min:1';
                $rules['pass_marks'] = 'required|integer|min:0|lt:full_marks';
            }
        } else {
            $rules['institute_subject_id'] = [
                'required',
                Rule::exists('institute_subjects', 'id')->where('institute_id', $instituteId),
            ];
            $rules['full_marks'] = 'required|integer|min:1';
            $rules['pass_marks'] = 'required|integer|min:0|lt:full_marks';
        }

        $validator = Validator::make($request->all(), $rules);
        $classIds = $request->input('class_ids');
        if ($classIds === null) {
            $classIds = $request->filled('class_id') ? [$request->input('class_id')] : [];
        }

        $validator->after(function ($validator) use (
            $request,
            $instituteId,
            $usesSubjectArray,
            $usesSubjectMarks,
            $subjectField,
            $classIds
        ) {
            $hasClassIdError = collect($validator->errors()->keys())
                ->contains(fn ($key) => str_starts_with($key, 'class_ids'));
            if ($validator->errors()->has('exam_id') || ! is_array($classIds) || $hasClassIdError) {
                return;
            }

            $exam = Exam::query()
                ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
                ->with('classes')
                ->find($request->exam_id);
            if ($exam === null) {
                return;
            }

            $allowsMultipleSelections = in_array($exam->type, ['monthly', 'test'], true);
            if (count($classIds) > 1 && ! $allowsMultipleSelections) {
                $validator->errors()->add('class_ids', 'Only monthly exams and class tests can include multiple classes.');
                return;
            }

            foreach ($classIds as $classId) {
                if (! $this->classBelongsToExam($request->exam_id, $classId, $instituteId)) {
                    $validator->errors()->add('class_ids', 'One or more selected classes are not included in this exam.');
                    return;
                }
            }

            $subjectIds = $usesSubjectArray
                ? $request->input('institute_subject_ids', [])
                : [$request->input('institute_subject_id')];

            if (! is_array($subjectIds)) {
                return;
            }

            if (count($subjectIds) > 1 && ! $allowsMultipleSelections) {
                $validator->errors()->add($subjectField, 'Only monthly exams and class tests can have multiple subjects.');
                return;
            }

            foreach ($subjectIds as $index => $subjectId) {
                $errorKey = $usesSubjectArray ? "{$subjectField}.{$index}" : $subjectField;
                if ($validator->errors()->has($errorKey)) {
                    continue;
                }

                $isAssignedToSelectedClass = $classIds
                    ? collect($classIds)->contains(fn ($classId) => $this->subjectBelongsToExamClass(
                        $request->exam_id,
                        $subjectId,
                        $instituteId,
                        [$classId]
                    ))
                    : $this->subjectBelongsToExamClass($request->exam_id, $subjectId, $instituteId);

                if (! $isAssignedToSelectedClass) {
                    $validator->errors()->add($errorKey, $classIds
                        ? 'The selected subject is not assigned to any selected class.'
                        : 'The selected subject is not assigned to a class in this exam.');
                }
            }

            if ($usesSubjectMarks && is_array($request->input('subject_marks'))) {
                $subjectMarks = $request->input('subject_marks');
                foreach ($subjectIds as $subjectId) {
                    $subjectMark = $subjectMarks[$subjectId] ?? null;
                    if (! is_array($subjectMark)) {
                        $validator->errors()->add("subject_marks.{$subjectId}.full_marks", 'Enter full and pass marks for each selected subject.');
                        continue;
                    }

                    if (isset($subjectMark['full_marks'], $subjectMark['pass_marks'])
                        && is_numeric($subjectMark['full_marks'])
                        && is_numeric($subjectMark['pass_marks'])
                        && (float) $subjectMark['pass_marks'] >= (float) $subjectMark['full_marks']) {
                        $validator->errors()->add(
                            "subject_marks.{$subjectId}.pass_marks",
                            'Pass marks must be less than full marks.'
                        );
                    }
                }

                foreach (array_keys($subjectMarks) as $subjectId) {
                    if (! in_array((string) $subjectId, array_map('strval', $subjectIds), true)) {
                        $validator->errors()->add("subject_marks.{$subjectId}", 'Marks can only be set for selected subjects.');
                    }
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $subjectIds = $usesSubjectArray
            ? $request->input('institute_subject_ids')
            : [$request->input('institute_subject_id')];

        $assignmentClassIds = $classIds ?: Exam::with('classes')
            ->findOrFail($request->exam_id)
            ->classIds();
        $assignments = collect($subjectIds)->flatMap(function ($subjectId) use ($request, $assignmentClassIds, $instituteId) {
            return collect($assignmentClassIds)
                ->filter(fn ($classId) => $this->subjectBelongsToExamClass(
                    $request->exam_id,
                    $subjectId,
                    $instituteId,
                    [$classId]
                ))
                ->map(fn ($classId) => [
                    'institute_subject_id' => $subjectId,
                    'institute_class_id' => $classId,
                ]);
        })->values();

        $exists = $assignments->contains(fn ($assignment) => ExamInstituteSubjec::query()
            ->where('exam_id', $request->exam_id)
            ->where('institute_subject_id', $assignment['institute_subject_id'])
            ->where('institute_class_id', $assignment['institute_class_id'])
            ->exists());

        if ($exists) {
            return redirect()->back()
                ->with('error', count($subjectIds) === 1
                    ? 'This subject is already assigned to this exam.'
                    : 'One or more selected subjects are already assigned to this exam.')
                ->withInput();
        }

        DB::transaction(function () use ($request, $assignments, $usesSubjectMarks) {
            foreach ($assignments as $assignment) {
                $subjectId = $assignment['institute_subject_id'];
                $subjectMarks = $usesSubjectMarks
                    ? $request->input("subject_marks.{$subjectId}")
                    : [
                        'full_marks' => $request->input('full_marks'),
                        'pass_marks' => $request->input('pass_marks'),
                    ];

                ExamInstituteSubjec::create([
                    'exam_id' => $request->exam_id,
                    'institute_subject_id' => $subjectId,
                    'institute_class_id' => $assignment['institute_class_id'],
                    'exam_date' => $request->exam_date,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'full_marks' => $subjectMarks['full_marks'],
                    'pass_marks' => $subjectMarks['pass_marks'],
                ]);
            }
        });

        return redirect()->route('exam-subjects.index')
            ->with('success', count($subjectIds) === 1
                ? 'Subject assigned to exam successfully.'
                : 'Subjects assigned to exam successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ExamInstituteSubjec $examSubject)
    {
        $instituteId = Auth::user()->institute_id;
        $examSubject = ExamInstituteSubjec::query()
            ->whereHas('exam.instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->findOrFail($examSubject->id);

        $validator = Validator::make($request->all(), [
            'exam_id' => [
                'required',
                Rule::exists('exams', 'id')->where(fn ($query) => $query
                    ->whereIn('institute_class_id', InstituteClass::forInstitute($instituteId)->select('id'))),
            ],
            'institute_subject_id' => [
                'required',
                Rule::exists('institute_subjects', 'id')->where('institute_id', $instituteId),
            ],
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'full_marks' => 'required|integer|min:1',
            'pass_marks' => 'required|integer|min:0|lt:full_marks',
            'class_id' => [
                'nullable',
                'integer',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
        ]);

        $validator->after(function ($validator) use ($request, $instituteId, $examSubject) {
            if ($validator->errors()->has('exam_id') || $validator->errors()->has('class_id')) {
                return;
            }

            $classId = $request->input('class_id') ?: $examSubject->institute_class_id;
            if (! $classId || ! $this->classBelongsToExam($request->exam_id, $classId, $instituteId)) {
                $validator->errors()->add('class_id', 'The selected class is not included in this exam.');
                return;
            }

            if (! $validator->errors()->has('institute_subject_id')
                && ! $this->subjectBelongsToExamClass($request->exam_id, $request->institute_subject_id, $instituteId, [$classId])) {
                $validator->errors()->add('institute_subject_id', 'The selected subject is not assigned to the selected class.');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for duplicate (excluding current)
        $classId = $request->input('class_id', $examSubject->institute_class_id);
        $exists = ExamInstituteSubjec::where('exam_id', $request->exam_id)
            ->where('institute_subject_id', $request->institute_subject_id)
            ->where('institute_class_id', $classId)
            ->where('id', '!=', $examSubject->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This subject is already assigned to this exam.')
                ->withInput();
        }

        $examSubject->update([
            'exam_id' => $request->exam_id,
            'institute_subject_id' => $request->institute_subject_id,
            'institute_class_id' => $classId,
            'exam_date' => $request->exam_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'full_marks' => $request->full_marks,
            'pass_marks' => $request->pass_marks
        ]);

        return redirect()->route('exam-subjects.index')
            ->with('success', 'Exam subject updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExamInstituteSubjec $examSubject)
    {
        $examSubject = ExamInstituteSubjec::query()
            ->whereHas('exam.instituteClass', fn ($query) => $query->where('institute_id', Auth::user()->institute_id))
            ->findOrFail($examSubject->id);

        // Check if subject has marks
        if ($examSubject->marks()->count() > 0) {
            return redirect()->back()
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Cannot delete this exam subject. It has marks recorded.',
                ]);
        }

        $examSubject->delete();

        return redirect()->route('exam-subjects.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Exam subject deleted successfully.',
            ]);
    }

    /**
     * Get subjects for an exam (API).
     */
    public function getSubjectsByExam($examId)
    {
        Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', Auth::user()->institute_id))
            ->findOrFail($examId);

        $examSubjects = ExamInstituteSubjec::where('exam_id', $examId)
            ->with('instituteClass')
            ->with('instituteSubject')
            ->get()
            ->map(function($examSubject) {
                return [
                    'id' => $examSubject->id,
                    'subject_id' => $examSubject->institute_subject_id,
                    'subject_name' => $examSubject->instituteSubject->name ?? 'N/A',
                    'class_id' => $examSubject->institute_class_id,
                    'class_name' => $examSubject->instituteClass->name ?? 'N/A',
                    'exam_date' => $examSubject->exam_date,
                    'start_time' => $examSubject->start_time,
                    'end_time' => $examSubject->end_time,
                    'full_marks' => $examSubject->full_marks,
                    'pass_marks' => $examSubject->pass_marks
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $examSubjects
        ]);
    }

    public function routinePrint(Request $request)
    {
        $instituteId = Auth::user()->institute_id;
        $class = InstituteClass::query()
            ->where('institute_id', $instituteId)
            ->findOrFail($request->integer('class_id'));
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('academicYear')
            ->findOrFail($request->integer('exam_id'));

        abort_unless(in_array($class->id, $exam->classIds(), true), 404);

        $subjects = ExamInstituteSubjec::query()
            ->where('exam_id', $exam->id)
            ->where('institute_class_id', $class->id)
            ->with('instituteSubject')
            ->orderBy('exam_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (ExamInstituteSubjec $examSubject) => [
                'id' => $examSubject->id,
                'subject_name' => $examSubject->instituteSubject?->name ?? 'N/A',
                'subject_bn_name' => $examSubject->instituteSubject?->bn_name,
                'subject_code' => $examSubject->instituteSubject?->code,
                'exam_date' => $examSubject->exam_date?->format('Y-m-d'),
                'start_time' => $examSubject->start_time?->format('H:i'),
                'end_time' => $examSubject->end_time?->format('H:i'),
                'duration' => $examSubject->duration,
                'full_marks' => $examSubject->full_marks,
                'pass_marks' => $examSubject->pass_marks,
            ]);

        abort_if($subjects->isEmpty(), 404);

        return Inertia::render('Institute/ExamSubjects/RoutinePrint', [
            'institute' => [
                'name' => Auth::user()->institute->name,
                'address' => Auth::user()->institute->address,
            ],
            'schoolClass' => [
                'name' => $class->name,
                'bn_name' => $class->bn_name,
            ],
            'exam' => [
                'name' => $exam->name,
                'bn_name' => $exam->bn_name,
                'academic_year' => $exam->academicYear?->session,
            ],
            'subjects' => $subjects,
        ]);
    }

    private function subjectBelongsToExamClass($examId, $subjectId, int $instituteId, $classIds = null): bool
    {
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('classes')
            ->find($examId);

        $examClassIds = $exam?->classIds() ?? [];
        $classIds = $classIds === null ? $examClassIds : array_map('intval', (array) $classIds);
        if ($exam === null || array_diff($classIds, $examClassIds)) {
            return false;
        }

        return InstituteSubject::forInstitute($instituteId)
            ->whereKey($subjectId)
            ->whereHas('classes', fn ($query) => $query->whereIn('institute_classes.id', $classIds))
            ->exists();
    }

    private function classBelongsToExam($examId, $classId, int $instituteId): bool
    {
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('classes')
            ->find($examId);

        return $exam !== null && in_array((int) $classId, $exam->classIds(), true);
    }
}