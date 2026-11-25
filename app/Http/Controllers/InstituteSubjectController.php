<?php

namespace App\Http\Controllers;

use App\Models\InstituteSubject;
use App\Models\InstituteClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InstituteSubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instituteId = Auth::user()->institute_id;

        // Get subjects with their assigned classes
        $subjects = InstituteSubject::forInstitute($instituteId)
            ->with(['classes' => function($query) {
                $query->select('institute_classes.id', 'institute_classes.name', 'institute_classes.numeric_value');
            }])
            ->ordered()
            ->get()
            ->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'bn_name' => $subject->bn_name,
                    'code' => $subject->code,
                    'type' => $subject->type,
                    'type_label' => $subject->type_label,
                    'type_badge_class' => $subject->type_badge_class,
                    'full_marks' => $subject->full_marks,
                    'pass_marks' => $subject->pass_marks,
                    'theory_marks' => $subject->theory_marks,
                    'theory_pass_marks' => $subject->theory_pass_marks,
                    'practical_marks' => $subject->practical_marks,
                    'practical_pass_marks' => $subject->practical_pass_marks,
                    'mcq_marks' => $subject->mcq_marks,
                    'mcq_pass_marks' => $subject->mcq_pass_marks,
                    'display_order' => $subject->display_order,
                    'has_practical' => $subject->has_practical,
                    'full_marks_display' => $subject->full_marks_display,
                    // Get classes with pivot data
                    'classes' => $subject->classes->map(function($class) {
                        return [
                            'id' => $class->id,
                            'name' => $class->name,
                            'numeric_value' => $class->numeric_value,
                            'pivot' => [
                                'type' => $class->pivot->type,
                                'full_marks' => $class->pivot->full_marks,
                                'pass_marks' => $class->pivot->pass_marks,
                                'is_group_based' => (bool) $class->pivot->is_group_based,
                                'group_name' => $class->pivot->group_name,
                            ]
                        ];
                    }),
                    'classes_count' => $subject->classes->count(),
                    'created_at' => $subject->created_at,
                    'updated_at' => $subject->updated_at
                ];
            });

        // Get all classes for filter dropdown
        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $stats = [
            'total' => $subjects->count(),
            'compulsory' => $subjects->where('type', 'compulsory')->count(),
            'optional' => $subjects->where('type', 'optional')->count(),
            'elective' => $subjects->where('type', 'elective')->count(),
            'additional' => $subjects->where('type', 'additional')->count(),
            'with_practical' => $subjects->where('has_practical', true)->count()
        ];

        return Inertia::render('Institute/Subjects/Index', [
            'subjects' => $subjects,
            'classes' => $classes,
            'stats' => $stats
        ]);
    }

    public function combinePapers(Request $request)
    {
        $instituteId = (int) Auth::user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned to this account.');

        $validated = $request->validate([
            'class_id' => [
                'required',
                'integer',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('institute_subjects', 'id')->where('institute_id', $instituteId),
            ],
            'group_name' => 'nullable|string|max:100',
            'old_group_name' => 'nullable|string|max:100',
        ]);
        $groupName = trim((string) ($validated['group_name'] ?? ''));
        $oldGroupName = trim((string) ($validated['old_group_name'] ?? ''));
        $subjectIds = collect($validated['subject_ids'])->map(fn ($id) => (int) $id)->values();

        $assignedIds = DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $validated['class_id'])
            ->whereIn('institute_subject_id', $subjectIds)
            ->pluck('institute_subject_id')
            ->map(fn ($id) => (int) $id);
        if ($assignedIds->count() !== $subjectIds->count()) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Select only subjects assigned to the chosen class.',
            ]);
        }

        if ($groupName !== '' && $subjectIds->count() < 2) {
            throw ValidationException::withMessages([
                'subject_ids' => 'Select at least two papers to create a combined subject.',
            ]);
        }

        DB::transaction(function () use ($validated, $subjectIds, $groupName, $oldGroupName) {
            $classAssignments = DB::table('institute_class_institute_subjects')
                ->where('institute_class_id', $validated['class_id']);

            if ($oldGroupName !== '') {
                $classAssignments
                    ->where('is_group_based', true)
                    ->where('group_name', $oldGroupName)
                    ->whereNotIn('institute_subject_id', $subjectIds)
                    ->update([
                        'is_group_based' => false,
                        'group_name' => null,
                    ]);
            }

            DB::table('institute_class_institute_subjects')
                ->where('institute_class_id', $validated['class_id'])
                ->whereIn('institute_subject_id', $subjectIds)
                ->update([
                    'is_group_based' => $groupName !== '',
                    'group_name' => $groupName !== '' ? $groupName : null,
                ]);

            DB::table('institute_class_institute_subjects')
                ->where('institute_class_id', $validated['class_id'])
                ->where('is_group_based', true)
                ->whereNotNull('group_name')
                ->get(['group_name'])
                ->pluck('group_name')
                ->unique()
                ->each(function (string $name) use ($validated) {
                    $groupSubjectIds = DB::table('institute_class_institute_subjects')
                        ->where('institute_class_id', $validated['class_id'])
                        ->where('is_group_based', true)
                        ->where('group_name', $name)
                        ->pluck('institute_subject_id');

                    if ($groupSubjectIds->count() < 2) {
                        DB::table('institute_class_institute_subjects')
                            ->where('institute_class_id', $validated['class_id'])
                            ->whereIn('institute_subject_id', $groupSubjectIds)
                            ->update([
                                'is_group_based' => false,
                                'group_name' => null,
                            ]);
                    }
                });
        });

        return redirect()->route('subjects.index')
            ->with('flash', [
                'message' => $groupName === ''
                    ? 'Selected papers are now separate subjects.'
                    : 'Combined subject saved successfully.',
            ]);
    }

    /**
     * Show the form for creating a new subject.
     */
    public function create()
    {
        $instituteId = Auth::user()->institute_id;

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        return Inertia::render('Institute/Subjects/Create', [
            'classes' => $classes
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

    // dd($request->all());
        $instituteId = Auth::user()->institute_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'code' => 'required|string|max:50|unique:institute_subjects,code,NULL,id,institute_id,' . $instituteId,
            'type' => 'required|in:compulsory,optional,elective,additional',
            'full_marks' => 'required|integer|min:1|max:1000',
            'pass_marks' => 'required|integer|min:0|max:1000|lt:full_marks',
            'theory_marks' => 'nullable|integer|min:0|max:1000',
            'practical_marks' => 'nullable|integer|min:0|max:1000',
            'mcq_marks' => 'nullable|integer|min:0|max:1000',
            'theory_pass_marks' => 'nullable|numeric|min:0|max:1000',
            'practical_pass_marks' => 'nullable|numeric|min:0|max:1000',
            'mcq_pass_marks' => 'nullable|numeric|min:0|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'has_practical' => 'boolean',
            'class_ids' => 'nullable|array',
            'class_ids.*' => [
                'integer',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'group_names' => 'nullable|array',
            'group_names.*' => 'nullable|string|max:100',
        ]);

        $this->validateComponentPassMarks($validator, $request);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Create subject
        $subject = InstituteSubject::create([
            'institute_id' => $instituteId,
            'name' => $request->name,
            'bn_name' => $request->bn_name,
            'code' => $request->code,
            'type' => $request->type,
            'full_marks' => $request->full_marks,
            'pass_marks' => $request->pass_marks,
            'theory_marks' => $request->theory_marks,
            'theory_pass_marks' => $request->theory_pass_marks,
            'practical_marks' => $request->practical_marks,
            'practical_pass_marks' => $request->practical_pass_marks,
            'mcq_marks' => $request->mcq_marks,
            'mcq_pass_marks' => $request->mcq_pass_marks,
            'display_order' => $request->display_order ?? 0,
            'has_practical' => $request->has_practical ?? false
        ]);

        // Assign to classes if provided
        if ($request->has('class_ids') && !empty($request->class_ids)) {
            $assignments = [];
            foreach ($request->class_ids as $classId) {
                $groupName = trim((string) $request->input("group_names.{$classId}", ''));
                $assignments[$classId] = [
                    'type' => $request->type,
                    'full_marks' => $request->full_marks,
                    'pass_marks' => $request->pass_marks,
                    'is_group_based' => $groupName !== '',
                    'group_name' => $groupName !== '' ? $groupName : null,
                ];
            }
            $subject->classes()->attach($assignments);
        }

        return redirect()->route('subjects.index')
            ->with('success', 'Subject created successfully.');
    }

    /**
     * Show the form for editing the specified subject.
     */
    public function edit(InstituteSubject $subject)
    {
        $instituteId = Auth::user()->institute_id;
        $subject = InstituteSubject::forInstitute($instituteId)->findOrFail($subject->id);

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $classAssignments = $subject->classes()->get(['institute_classes.id']);
        $assignedClassIds = $classAssignments->pluck('id')->all();
        $groupNames = $classAssignments->mapWithKeys(fn ($class) => [
            $class->id => $class->pivot->group_name,
        ])->all();

        return Inertia::render('Institute/Subjects/Edit', [
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'bn_name' => $subject->bn_name,
                'code' => $subject->code,
                'type' => $subject->type,
                'type_label' => $subject->type_label,
                'full_marks' => $subject->full_marks,
                'pass_marks' => $subject->pass_marks,
                'theory_marks' => $subject->theory_marks,
                'theory_pass_marks' => $subject->theory_pass_marks,
                'practical_marks' => $subject->practical_marks,
                'practical_pass_marks' => $subject->practical_pass_marks,
                'mcq_marks' => $subject->mcq_marks,
                'mcq_pass_marks' => $subject->mcq_pass_marks,
                'display_order' => $subject->display_order,
                'has_practical' => $subject->has_practical,
                'assigned_class_ids' => $assignedClassIds,
                'group_names' => $groupNames,
            ],
            'classes' => $classes
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InstituteSubject $subject)
    {
        $instituteId = Auth::user()->institute_id;
        $subject = InstituteSubject::forInstitute($instituteId)->findOrFail($subject->id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'code' => 'required|string|max:50|unique:institute_subjects,code,' . $subject->id . ',id,institute_id,' . $instituteId,
            'type' => 'required|in:compulsory,optional,elective,additional',
            'full_marks' => 'required|integer|min:1|max:1000',
            'pass_marks' => 'required|integer|min:0|max:1000|lt:full_marks',
            'theory_marks' => 'nullable|integer|min:0|max:1000',
            'practical_marks' => 'nullable|integer|min:0|max:1000',
            'mcq_marks' => 'nullable|integer|min:0|max:1000',
            'theory_pass_marks' => 'nullable|numeric|min:0|max:1000',
            'practical_pass_marks' => 'nullable|numeric|min:0|max:1000',
            'mcq_pass_marks' => 'nullable|numeric|min:0|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'has_practical' => 'boolean',
            'class_ids' => 'nullable|array',
            'class_ids.*' => [
                'integer',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'group_names' => 'nullable|array',
            'group_names.*' => 'nullable|string|max:100',
        ]);

        $this->validateComponentPassMarks($validator, $request);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Update subject
        $subject->update([
            'name' => $request->name,
            'bn_name' => $request->bn_name,
            'code' => $request->code,
            'type' => $request->type,
            'full_marks' => $request->full_marks,
            'pass_marks' => $request->pass_marks,
            'theory_marks' => $request->theory_marks,
            'theory_pass_marks' => $request->theory_pass_marks,
            'practical_marks' => $request->practical_marks,
            'practical_pass_marks' => $request->practical_pass_marks,
            'mcq_marks' => $request->mcq_marks,
            'mcq_pass_marks' => $request->mcq_pass_marks,
            'display_order' => $request->display_order ?? 0,
            'has_practical' => $request->has_practical ?? false
        ]);

        // Sync classes
        if ($request->has('class_ids')) {
            $syncData = [];
            foreach ($request->class_ids as $classId) {
                $groupName = trim((string) $request->input("group_names.{$classId}", ''));
                $syncData[$classId] = [
                    'type' => $request->type,
                    'full_marks' => $request->full_marks,
                    'pass_marks' => $request->pass_marks,
                    'is_group_based' => $groupName !== '',
                    'group_name' => $groupName !== '' ? $groupName : null,
                ];
            }
            $subject->classes()->sync($syncData);
        } else {
            $subject->classes()->detach();
        }

        return redirect()->route('subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InstituteSubject $subject)
    {
        $subject = InstituteSubject::forInstitute(Auth::user()->institute_id)->findOrFail($subject->id);

        // Check if subject has marks
        if ($subject->marks()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete this subject. It has marks recorded.');
        }

        // Detach from all classes
        $subject->classes()->detach();

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }

    /**
     * Get subjects by class (API).
     */
    public function getSubjectsByClass($classId)
    {
        $instituteId = Auth::user()->institute_id;

        $subjects = InstituteSubject::forInstitute($instituteId)
            ->whereHas('classes', function($query) use ($classId) {
                $query->where('institute_class_id', $classId);
            })
            ->ordered()
            ->get([
                'id', 'name', 'code', 'full_marks', 'pass_marks',
                'theory_marks', 'theory_pass_marks', 'mcq_marks', 'mcq_pass_marks',
                'practical_marks', 'practical_pass_marks',
            ]);

        return response()->json([
            'success' => true,
            'data' => $subjects
        ]);
    }

    private function validateComponentPassMarks(\Illuminate\Contracts\Validation\Validator $validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            foreach ([
                'theory_pass_marks' => 'theory_marks',
                'mcq_pass_marks' => 'mcq_marks',
                'practical_pass_marks' => 'practical_marks',
            ] as $passField => $maximumField) {
                $passMark = $request->input($passField);
                $maximum = $request->input($maximumField);

                if ($passMark !== null && $passMark !== '' && (
                    $maximum === null || $maximum === '' || (float) $passMark > (float) $maximum
                )) {
                    $validator->errors()->add(
                        $passField,
                        'The component pass mark must not exceed its component full marks.'
                    );
                }
            }
        });
    }
}