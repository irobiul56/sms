<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\InstituteSubject;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\TeacherPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instituteId = Auth::user()->institute_id;

        $teachers = Teacher::forInstitute($instituteId)
            ->with(['classes', 'sections'])
            ->latest()
            ->get()
            ->map(function($teacher) {
                return [
                    'id' => $teacher->id,
                    'employee_id' => $teacher->employee_id,
                    'name' => $teacher->name,
                    'bn_name' => $teacher->bn_name,
                    'designation' => $teacher->designation,
                    'phone' => $teacher->phone,
                    'email' => $teacher->email,
                    'gender' => $teacher->gender,
                    'gender_label' => $teacher->gender_label,
                    'photo' => $teacher->photo_url,
                    'status' => $teacher->status,
                    'status_label' => $teacher->status_label,
                    'status_badge_class' => $teacher->status_badge_class,
                    'classes' => $teacher->classes->map(function($class) {
                        return [
                            'id' => $class->id,
                            'name' => $class->name,
                        ];
                    }),
                    'sections' => $teacher->sections->map(function($section) {
                        return [
                            'id' => $section->id,
                            'name' => $section->name,
                        ];
                    }),
                    'classes_count' => $teacher->classes->count(),
                    'created_at' => $teacher->created_at,
                    'updated_at' => $teacher->updated_at
                ];
            });

        $stats = [
            'total' => $teachers->count(),
            'active' => $teachers->where('status', 'active')->count(),
            'inactive' => $teachers->where('status', 'inactive')->count(),
            'retired' => $teachers->where('status', 'retired')->count(),
            'transferred' => $teachers->where('status', 'transferred')->count(),
            'male' => $teachers->where('gender', 'male')->count(),
            'female' => $teachers->where('gender', 'female')->count()
        ];

        return Inertia::render('Institute/Teachers/Index', [
            'teachers' => $teachers,
            'stats' => $stats
        ]);
    }

    /**
     * Show the form for creating a new teacher.
     */
   public function create()
    {
        $instituteId =Auth::user()->institute_id;

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        // FIX: Get sections with institute_class_id
        $sections = Section::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']); // institute_class_id গুরুত্বপূর্ণ

        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        // Debug: Check if sections have institute_class_id
        // dd($sections);

        return Inertia::render('Institute/Teachers/Create', [
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            'classPermissionOptions' => TeacherPermissionService::CLASS_PERMISSIONS,
        ]);
    }

    /**
     * Store a newly created teacher in storage.
     */
    public function store(Request $request)
    {
        $instituteId = Auth::user()->institute_id;
        $classPermissions = $request->input('class_permissions', []);
        if (is_string($classPermissions)) {
            $classPermissions = json_decode($classPermissions, true) ?? [];
        }
        $request->merge(['class_permissions' => $classPermissions]);

        $validator = Validator::make($request->all(), [
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('teachers', 'employee_id')],
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'designation' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'account_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'required|date',
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'required|in:active,inactive,retired,transferred',
            'class_ids' => 'nullable|array',
            'class_ids.*' => ['integer', Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId)],
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'nullable|integer',
            'class_permissions' => ['nullable', 'array'],
            'class_permissions.*' => ['array'],
            'class_permissions.*.*' => ['string', Rule::in(array_keys(TeacherPermissionService::CLASS_PERMISSIONS))],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where('institute_id', $instituteId),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $classIds = $validator->validated()['class_ids'] ?? [];
        $academicYearId = $request->input('academic_year_id')
            ?: AcademicYear::forInstitute($instituteId)->where('is_current', true)->value('id');

        if ($classIds && ! $academicYearId) {
            return redirect()->back()
                ->withErrors(['academic_year_id' => 'Create or select an academic year before assigning classes.'])
                ->withInput();
        }

        $sectionIds = $request->input('section_ids', []);
        $invalidSection = $this->findInvalidAssignedSection($classIds, $sectionIds, $instituteId);
        if ($invalidSection !== null) {
            return redirect()->back()
                ->withErrors(['section_ids' => 'Select a section that belongs to the chosen class.'])
                ->withInput();
        }

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('teacher-photos', 'public');
        }

        $classPermissions = $validator->validated()['class_permissions'] ?? [];

        DB::transaction(function () use ($request, $instituteId, $photoPath, $classIds, $sectionIds, $classPermissions, $academicYearId) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->account_email,
                'password' => $request->password,
                'institute_id' => $instituteId,
                'usertype' => 'teacher',
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'institute_id' => $instituteId,
                'employee_id' => $request->employee_id,
                'name' => $request->name,
                'bn_name' => $request->bn_name,
                'designation' => $request->designation,
                'phone' => $request->phone,
                'email' => $request->email,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'joining_date' => $request->joining_date,
                'qualification' => $request->qualification,
                'address' => $request->address,
                'photo' => $photoPath,
                'status' => $request->status ?? 'active',
            ]);

            foreach ($classIds as $classId) {
                $sectionId = $sectionIds[$classId] ?? null;

                ClassTeacher::create([
                    'teacher_id' => $teacher->id,
                    'institute_class_id' => $classId,
                    'section_id' => $sectionId ?: null,
                    'academic_year_id' => $academicYearId,
                    'permissions' => $classPermissions[$classId] ?? [],
                ]);
            }
        });

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher created successfully.');
    }

    /**
     * Show the form for editing the specified teacher.
     */
     public function edit(Teacher $teacher)
    {
        $instituteId = Auth::user()->institute_id;
        $teacher = Teacher::forInstitute($instituteId)->findOrFail($teacher->id);

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        // FIX: Get sections with institute_class_id
        $sections = Section::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']); // institute_class_id গুরুত্বপূর্ণ

        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        $assignedClasses = $teacher->classTeachers()->get()->map(function($ct) {
            return [
                'class_id' => $ct->institute_class_id,
                'section_id' => $ct->section_id,
                'academic_year_id' => $ct->academic_year_id,
                'permissions' => $ct->permissions ?? [],
            ];
        });

        return Inertia::render('Institute/Teachers/Edit', [
            'teacher' => [
                'id' => $teacher->id,
                'employee_id' => $teacher->employee_id,
                'name' => $teacher->name,
                'bn_name' => $teacher->bn_name,
                'designation' => $teacher->designation,
                'phone' => $teacher->phone,
                'email' => $teacher->email,
                'account_email' => $teacher->user?->email,
                'has_login_account' => (bool) $teacher->user_id,
                'gender' => $teacher->gender,
                'date_of_birth' => $teacher->date_of_birth,
                'joining_date' => $teacher->joining_date,
                'qualification' => $teacher->qualification,
                'address' => $teacher->address,
                'photo' => $teacher->photo_url,
                'status' => $teacher->status,
                'assigned_classes' => $assignedClasses,
                'created_at' => $teacher->created_at,
                'updated_at' => $teacher->updated_at
            ],
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            'classPermissionOptions' => TeacherPermissionService::CLASS_PERMISSIONS,
        ]);
    }

    /**
     * Update the specified teacher in storage.
    
    
     */
      public function update(Request $request, Teacher $teacher)
    {
        $instituteId = Auth::user()->institute_id;
        $teacher = Teacher::forInstitute($instituteId)->findOrFail($teacher->id);
          $classPermissions = $request->input('class_permissions', []);
          if (is_string($classPermissions)) {
              $classPermissions = json_decode($classPermissions, true) ?? [];
          }
          $request->merge(['class_permissions' => $classPermissions]);

        // Get section_ids (for both normal and FormData)
        $sectionIds = [];
        if ($request->has('section_ids')) {
            if (is_string($request->section_ids)) {
                $sectionIds = json_decode($request->section_ids, true) ?? [];
            } else {
                $sectionIds = $request->section_ids;
            }
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('teachers', 'employee_id')->ignore($teacher->id)],
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'designation' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'account_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($teacher->user_id),
            ],
            'password' => [$teacher->user_id ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'joining_date' => 'required|date',
            'qualification' => 'nullable|string',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'required|in:active,inactive,retired,transferred',
            'remove_photo' => 'nullable|boolean',
            'class_ids' => 'nullable|array',
            'class_ids.*' => ['integer', Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId)],
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'nullable|integer',
            'class_permissions' => ['nullable', 'array'],
            'class_permissions.*' => ['array'],
            'class_permissions.*.*' => ['string', Rule::in(array_keys(TeacherPermissionService::CLASS_PERMISSIONS))],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where('institute_id', $instituteId),
            ],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $classIds = $validator->validated()['class_ids'] ?? [];
        $classPermissions = $validator->validated()['class_permissions'] ?? [];
        $academicYearId = $request->input('academic_year_id')
            ?: AcademicYear::forInstitute($instituteId)->where('is_current', true)->value('id');

        if ($classIds && ! $academicYearId) {
            return redirect()->back()
                ->withErrors(['academic_year_id' => 'Create or select an academic year before assigning classes.'])
                ->withInput();
        }

        $invalidSection = $this->findInvalidAssignedSection($classIds, $sectionIds, $instituteId);
        if ($invalidSection !== null) {
            return redirect()->back()
                ->withErrors(['section_ids' => 'Select a section that belongs to the chosen class.'])
                ->withInput();
        }

        // Handle photo
        $photoPath = $teacher->photo;
        
        if ($request->remove_photo == '1' || $request->remove_photo === true) {
            if ($teacher->photo && Storage::disk('public')->exists($teacher->photo)) {
                Storage::disk('public')->delete($teacher->photo);
            }
            $photoPath = null;
        }
        
        if ($request->hasFile('photo')) {
            if ($teacher->photo && Storage::disk('public')->exists($teacher->photo)) {
                Storage::disk('public')->delete($teacher->photo);
            }
            $photoPath = $request->file('photo')->store('teacher-photos', 'public');
        }

        DB::transaction(function () use ($request, $teacher, $photoPath, $classIds, $sectionIds, $classPermissions, $academicYearId, $instituteId) {
            $user = $teacher->user;
            $userData = [
                'name' => $request->name,
                'email' => $request->account_email,
                'institute_id' => $instituteId,
                'usertype' => 'teacher',
            ];
            if ($request->filled('password')) {
                $userData['password'] = $request->password;
            }

            if ($user) {
                $user->update($userData);
            } else {
                $user = User::create($userData);
                $teacher->user_id = $user->id;
                $teacher->save();
            }

            $teacher->update([
                'employee_id' => $request->employee_id,
                'name' => $request->name,
                'bn_name' => $request->bn_name,
                'designation' => $request->designation,
                'phone' => $request->phone,
                'email' => $request->email,
                'gender' => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'joining_date' => $request->joining_date,
                'qualification' => $request->qualification,
                'address' => $request->address,
                'photo' => $photoPath,
                'status' => $request->status ?? 'active',
            ]);

            ClassTeacher::where('teacher_id', $teacher->id)->delete();

            foreach ($classIds as $classId) {
                $sectionId = $sectionIds[$classId] ?? null;

                ClassTeacher::create([
                    'teacher_id' => $teacher->id,
                    'institute_class_id' => $classId,
                    'section_id' => $sectionId ?: null,
                    'academic_year_id' => $academicYearId,
                    'permissions' => $classPermissions[$classId] ?? [],
                ]);
            }
        });

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher updated successfully.');
    }

    /**
     * Remove the specified teacher from storage.
     */
    public function destroy(Teacher $teacher)
    {
        $teacher = Teacher::forInstitute(Auth::user()->institute_id)->findOrFail($teacher->id);
        // Delete photo if exists
        if ($teacher->photo && Storage::disk('public')->exists($teacher->photo)) {
            Storage::disk('public')->delete($teacher->photo);
        }

        // Delete class assignments
        ClassTeacher::where('teacher_id', $teacher->id)->delete();

        $teacher->delete();

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher deleted successfully.');
    }

    private function findInvalidAssignedSection(array $classIds, mixed $sectionIds, int $instituteId): ?int
    {
        if (! is_array($sectionIds)) {
            return null;
        }

        foreach ($classIds as $classId) {
            $sectionId = $sectionIds[$classId] ?? null;
            if ($sectionId === null || $sectionId === '') {
                continue;
            }

            $valid = Section::query()
                ->where('institute_id', $instituteId)
                ->where('institute_class_id', $classId)
                ->whereKey($sectionId)
                ->exists();

            if (! $valid) {
                return (int) $classId;
            }
        }

        return null;
    }

    /**
     * Toggle teacher status.
     */
    public function toggleStatus(Teacher $teacher)
    {
        $teacher = Teacher::forInstitute(Auth::user()->institute_id)->findOrFail($teacher->id);
        $newStatus = $teacher->status === 'active' ? 'inactive' : 'active';
        $teacher->update(['status' => $newStatus]);

        return redirect()->back()
            ->with('success', 'Teacher status updated successfully.');
    }


     /**
     * Show the form for assigning subjects to a teacher.
     */
    public function assignSubjects(Teacher $teacher)
    {
        $instituteId = Auth::user()->institute_id;
        $teacher = Teacher::forInstitute($instituteId)->findOrFail($teacher->id);

        $teacher->load(['subjectAssignments.instituteSubject', 'subjectAssignments.instituteClass', 'subjectAssignments.section', 'subjectAssignments.academicYear']);

        // Get all subjects for the institute
        $subjects = InstituteSubject::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'code', 'full_marks', 'pass_marks']);

        // Get all classes
        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        // Get all sections
        $sections = Section::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']);

        // Get academic years
        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        // Get already assigned subjects with details
        $assignedSubjects = $teacher->subjectAssignments()
            ->with(['instituteSubject', 'instituteClass', 'section', 'academicYear'])
            ->get()
            ->map(function($assignment) {
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
                    'display_order' => $assignment->display_order,
                    'note' => $assignment->note,
                    'status_label' => $assignment->status_label,
                    'status_badge_class' => $assignment->status_badge_class,
                    'type_label' => $assignment->type_label,
                    'type_badge_class' => $assignment->type_badge_class
                ];
            });

        $stats = [
            'total_assignments' => $assignedSubjects->count(),
            'primary_subjects' => $assignedSubjects->where('is_primary', true)->count(),
            'active_assignments' => $assignedSubjects->where('is_active', true)->count(),
            'unique_classes' => $assignedSubjects->pluck('class_id')->unique()->count(),
            'unique_subjects' => $assignedSubjects->pluck('subject_id')->unique()->count()
        ];

        return Inertia::render('Institute/Teachers/AssignSubjects', [
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
            'subjects' => $subjects,
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            'assignedSubjects' => $assignedSubjects,
            'stats' => $stats
        ]);
    }

    /**
     * Store teacher subject assignments.
     */
    public function storeSubjectAssignment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:institute_subjects,id',
            'class_id' => 'required|exists:institute_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer',
            'note' => 'nullable|string'
        ]);

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
                ->with('error', 'This subject is already assigned to this teacher for the selected class and academic year.')
                ->withInput();
        }

        // If this is primary, unset other primary for this subject and class
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
            'display_order' => $request->display_order ?? 0,
            'note' => $request->note
        ]);

        return redirect()->route('teachers.assign-subjects', $request->teacher_id)
            ->with('success', 'Subject assigned to teacher successfully.');
    }

    /**
     * Update teacher subject assignment.
     */
    public function updateSubjectAssignment(Request $request, TeacherSubject $teacherSubject)
    {
        $validator = Validator::make($request->all(), [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // If this is primary, unset other primary for this subject and class
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
            'display_order' => $request->display_order ?? $teacherSubject->display_order,
            'note' => $request->note ?? $teacherSubject->note
        ]);

        return redirect()->route('teachers.assign-subjects', $teacherSubject->teacher_id)
            ->with('success', 'Subject assignment updated successfully.');
    }

    /**
     * Remove teacher subject assignment.
     */
    public function removeSubjectAssignment(TeacherSubject $teacherSubject)
    {
        $teacherId = $teacherSubject->teacher_id;
        $teacherSubject->delete();

        return redirect()->route('teachers.assign-subjects', $teacherId)
            ->with('success', 'Subject assignment removed successfully.');
    }

    /**
     * Get teachers for a subject (API).
     */
    public function getTeachersBySubject(Request $request)
    {
        $instituteId = Auth::user()->institute_id;

        $teachers = TeacherSubject::forInstitute($instituteId)
            ->where('institute_subject_id', $request->subject_id)
            ->where('institute_class_id', $request->class_id)
            ->where('academic_year_id', $request->academic_year_id)
            ->with(['teacher', 'section'])
            ->active()
            ->get()
            ->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'teacher_id' => $assignment->teacher_id,
                    'teacher_name' => $assignment->teacher->name ?? 'N/A',
                    'section_name' => $assignment->section->name ?? 'All Sections',
                    'is_primary' => $assignment->is_primary,
                    'is_active' => $assignment->is_active,
                    'display_order' => $assignment->display_order
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $teachers
        ]);
    }

    /**
     * Get teacher's assigned subjects (API).
     */
    public function getTeacherSubjects(Request $request, Teacher $teacher)
    {
        $teacher = Teacher::forInstitute(Auth::user()->institute_id)->findOrFail($teacher->id);
        $academicYearId = $request->academic_year_id;
        
        $query = $teacher->subjectAssignments()
            ->with(['instituteSubject', 'instituteClass', 'section'])
            ->active();
        
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }
        
        $subjects = $query->get()->map(function($assignment) {
            return [
                'id' => $assignment->id,
                'subject_id' => $assignment->institute_subject_id,
                'subject_name' => $assignment->instituteSubject->name ?? 'N/A',
                'class_name' => $assignment->instituteClass->name ?? 'N/A',
                'section_name' => $assignment->section->name ?? 'All',
                'is_primary' => $assignment->is_primary,
                'is_active' => $assignment->is_active
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $subjects
        ]);
    }
}