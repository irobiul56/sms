<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\InstituteSubject;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\AcademicYear;
use App\Services\TeacherPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeacherSubjectController extends Controller
{
    /**
     * Display the teacher subjects index page.
     */
    public function index()
    {
        $instituteId = Auth::user()->institute_id;

        // Get all teachers with their subject assignments
        $teachers = Teacher::forInstitute($instituteId)
            ->with(['subjectAssignments' => function($query) {
                $query->with(['instituteSubject', 'instituteClass', 'section', 'academicYear']);
            }])
            ->get()
            ->map(function($teacher) {
                $assignments = $teacher->subjectAssignments->map(function($assignment) {
                    return [
                        'id' => $assignment->id,
                        'subject_id' => $assignment->institute_subject_id,
                        'subject_name' => $assignment->instituteSubject->name ?? 'N/A',
                        'subject_code' => $assignment->instituteSubject->code ?? 'N/A',
                        'class_id' => $assignment->institute_class_id,
                        'class_name' => $assignment->instituteClass->name ?? 'N/A',
                        'section_id' => $assignment->section_id,
                        'section_name' => $assignment->section->name ?? 'All',
                        'academic_year_id' => $assignment->academic_year_id,
                        'academic_year_session' => $assignment->academicYear->session ?? 'N/A',
                        'is_primary' => $assignment->is_primary,
                        'is_active' => $assignment->is_active,
                        'permissions' => $assignment->permissions ?? [],
                        'display_order' => $assignment->display_order,
                        'note' => $assignment->note,
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at
                    ];
                });

                return [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'bn_name' => $teacher->bn_name,
                    'employee_id' => $teacher->employee_id,
                    'designation' => $teacher->designation,
                    'phone' => $teacher->phone,
                    'email' => $teacher->email,
                    'photo' => $teacher->photo_url,
                    'status' => $teacher->status,
                    'status_label' => $teacher->status_label,
                    'status_badge_class' => $teacher->status_badge_class,
                    'assignments' => $assignments,
                    'total_assignments' => $assignments->count(),
                    'primary_count' => $assignments->where('is_primary', true)->count(),
                    'active_count' => $assignments->where('is_active', true)->count()
                ];
            });

        // Get dropdown data for create/edit modals
        $subjects = InstituteSubject::forInstitute($instituteId)
            ->with('classes:id')
            ->ordered()
            ->get(['id', 'name', 'code', 'full_marks', 'pass_marks']);
        $subjects = $subjects->map(fn (InstituteSubject $subject) => [
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

        $sections = Section::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']);

        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        $stats = [
            'total_teachers' => $teachers->count(),
            'total_assignments' => $teachers->sum('total_assignments'),
            'total_primary' => $teachers->sum('primary_count'),
            'total_active' => $teachers->sum('active_count')
        ];

        return Inertia::render('Institute/Teachers/AssignSubjects', [
            'teachers' => $teachers,
            'subjects' => $subjects,
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            'subjectPermissionOptions' => TeacherPermissionService::SUBJECT_PERMISSIONS,
            'stats' => $stats
        ]);
    }

    /**
     * Show assignments for a specific teacher.
     */
    public function show(Teacher $teacher)
    {
        $instituteId = Auth::user()->institute_id;
        $teacher = Teacher::forInstitute($instituteId)->findOrFail($teacher->id);

        $teacher->load(['subjectAssignments' => function($query) {
            $query->with(['instituteSubject', 'instituteClass', 'section', 'academicYear']);
        }]);

        $assignments = $teacher->subjectAssignments->map(function($assignment) {
            return [
                'id' => $assignment->id,
                'subject_id' => $assignment->institute_subject_id,
                'subject_name' => $assignment->instituteSubject->name ?? 'N/A',
                'subject_code' => $assignment->instituteSubject->code ?? 'N/A',
                'class_id' => $assignment->institute_class_id,
                'class_name' => $assignment->instituteClass->name ?? 'N/A',
                'section_id' => $assignment->section_id,
                'section_name' => $assignment->section->name ?? 'All',
                'academic_year_id' => $assignment->academic_year_id,
                'academic_year_session' => $assignment->academicYear->session ?? 'N/A',
                'is_primary' => $assignment->is_primary,
                'is_active' => $assignment->is_active,
                'permissions' => $assignment->permissions ?? [],
                'display_order' => $assignment->display_order,
                'note' => $assignment->note,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at
            ];
        });

        return Inertia::render('TeacherSubjects/Show', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'bn_name' => $teacher->bn_name,
                'employee_id' => $teacher->employee_id,
                'designation' => $teacher->designation,
                'phone' => $teacher->phone,
                'email' => $teacher->email,
                'photo' => $teacher->photo_url,
                'status' => $teacher->status,
                'status_label' => $teacher->status_label
            ],
            'assignments' => $assignments
        ]);
    }

    /**
     * Store a new teacher subject assignment.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => ['required', Rule::exists('teachers', 'id')->where('institute_id', Auth::user()->institute_id)],
            'subject_id' => ['required', Rule::exists('institute_subjects', 'id')->where('institute_id', Auth::user()->institute_id)],
            'class_id' => ['required', Rule::exists('institute_classes', 'id')->where('institute_id', Auth::user()->institute_id)],
            'section_id' => ['nullable', Rule::exists('sections', 'id')
                ->where('institute_id', Auth::user()->institute_id)
                ->where('institute_class_id', $request->input('class_id'))],
            'academic_year_id' => ['required', Rule::exists('academic_years', 'id')->where('institute_id', Auth::user()->institute_id)],
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(TeacherPermissionService::SUBJECT_PERMISSIONS))],
            'display_order' => 'nullable|integer',
            'note' => 'nullable|string'
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->has('subject_id') || $validator->errors()->has('class_id')) {
                return;
            }

            $assignedToClass = InstituteSubject::forInstitute(Auth::user()->institute_id)
                ->whereKey($request->input('subject_id'))
                ->whereHas('classes', fn ($query) => $query->whereKey($request->input('class_id')))
                ->exists();

            if (! $assignedToClass) {
                $validator->errors()->add('subject_id', 'The selected subject is not assigned to the selected class.');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for duplicate assignment
        $exists = TeacherSubject::where('teacher_id', $request->teacher_id)
            ->where('institute_subject_id', $request->subject_id)
            ->where('institute_class_id', $request->class_id)
            ->where('section_id', $request->section_id)
            ->where('academic_year_id', $request->academic_year_id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This subject is already assigned to this teacher for the selected class and academic year.');
        }

        // If primary, unset other primary for this subject and class
        if ($request->is_primary) {
            TeacherSubject::where('institute_subject_id', $request->subject_id)
                ->where('institute_class_id', $request->class_id)
                ->where('academic_year_id', $request->academic_year_id)
                ->update(['is_primary' => false]);
        }

        TeacherSubject::create([
            'teacher_id' => $request->teacher_id,
            'institute_subject_id' => $request->subject_id,
            'institute_class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'academic_year_id' => $request->academic_year_id,
            'is_primary' => $request->is_primary ?? false,
            'is_active' => $request->is_active ?? true,
            'permissions' => $request->input('permissions', []),
            'display_order' => $request->display_order ?? 0,
            'note' => $request->note
        ]);

        return redirect()->route('teacher-subjects.index')
            ->with('success', 'Subject assigned to teacher successfully.');
    }

    /**
     * Update a teacher subject assignment.
     */
    public function update(Request $request, TeacherSubject $teacherSubject)
    {
        $teacherSubject = TeacherSubject::forInstitute(Auth::user()->institute_id)->findOrFail($teacherSubject->id);
        $validator = Validator::make($request->all(), [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(TeacherPermissionService::SUBJECT_PERMISSIONS))],
            'display_order' => 'nullable|integer',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // If primary, unset other primary for this subject and class
        if ($request->is_primary) {
            TeacherSubject::where('institute_subject_id', $teacherSubject->institute_subject_id)
                ->where('institute_class_id', $teacherSubject->institute_class_id)
                ->where('academic_year_id', $teacherSubject->academic_year_id)
                ->where('id', '!=', $teacherSubject->id)
                ->update(['is_primary' => false]);
        }

        $teacherSubject->update([
            'is_primary' => $request->is_primary ?? $teacherSubject->is_primary,
            'is_active' => $request->is_active ?? $teacherSubject->is_active,
            'permissions' => $request->input('permissions', $teacherSubject->permissions ?? []),
            'display_order' => $request->display_order ?? $teacherSubject->display_order,
            'note' => $request->note ?? $teacherSubject->note
        ]);

        return redirect()->route('teacher-subjects.index')
            ->with('success', 'Subject assignment updated successfully.');
    }

    /**
     * Delete a teacher subject assignment.
     */
    public function destroy(TeacherSubject $teacherSubject)
    {
        $teacherSubject = TeacherSubject::forInstitute(Auth::user()->institute_id)->findOrFail($teacherSubject->id);
        $teacherSubject->delete();

        return redirect()->route('teacher-subjects.index')
            ->with('success', 'Subject assignment removed successfully.');
    }

    /**
     * Toggle assignment status.
     */
    public function toggleStatus(TeacherSubject $teacherSubject)
    {
        $teacherSubject = TeacherSubject::forInstitute(Auth::user()->institute_id)->findOrFail($teacherSubject->id);
        $teacherSubject->update([
            'is_active' => !$teacherSubject->is_active
        ]);

        $status = $teacherSubject->is_active ? 'activated' : 'deactivated';

        return redirect()->route('teacher-subjects.index')
            ->with('success', "Assignment {$status} successfully.");
    }

    /**
     * Get assignments for a teacher (AJAX).
     */
    public function getAssignments(Teacher $teacher)
    {
        $teacher = Teacher::forInstitute(Auth::user()->institute_id)->findOrFail($teacher->id);

        $assignments = $teacher->subjectAssignments()
            ->with(['instituteSubject', 'instituteClass', 'section', 'academicYear'])
            ->get()
            ->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'subject_name' => $assignment->instituteSubject->name ?? 'N/A',
                    'class_name' => $assignment->instituteClass->name ?? 'N/A',
                    'section_name' => $assignment->section->name ?? 'All',
                    'academic_year' => $assignment->academicYear->session ?? 'N/A',
                    'is_primary' => $assignment->is_primary,
                    'is_active' => $assignment->is_active
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }
}