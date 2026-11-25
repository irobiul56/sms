<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\StudentAcademicInfo;
use App\Models\InstituteSubject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function print(Request $request)
    {
        $instituteId = (int) Auth::user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned to this account.');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive,passed,transferred'],
            'academic_year_id' => ['nullable', 'integer'],
        ]);

        $classId = $filters['class_id'] ?? null;
        $sectionId = $filters['section_id'] ?? null;
        $academicYearId = $filters['academic_year_id'] ?? null;

        if ($classId) {
            abort_unless(InstituteClass::forInstitute($instituteId)->whereKey($classId)->exists(), 404);
        }
        if ($sectionId) {
            abort_unless(
                Section::forInstitute($instituteId)
                    ->when($classId, fn ($query) => $query->where('institute_class_id', $classId))
                    ->whereKey($sectionId)
                    ->exists(),
                404
            );
        }
        if ($academicYearId) {
            abort_unless(AcademicYear::forInstitute($instituteId)->whereKey($academicYearId)->exists(), 404);
        }

        $students = Student::forInstitute($instituteId)
            ->with([
                'currentClass' => fn ($query) => $query->where('institute_id', $instituteId),
                'currentSection' => fn ($query) => $query->where('institute_id', $instituteId),
                'currentAcademicInfo.academicYear',
            ])
            ->get()
            ->filter(function (Student $student) use ($filters, $classId, $sectionId, $academicYearId) {
                $info = $student->currentAcademicInfo;

                if ($classId && (string) $student->currentClass?->id !== (string) $classId) {
                    return false;
                }
                if ($sectionId && (string) $student->currentSection?->id !== (string) $sectionId) {
                    return false;
                }
                if ($academicYearId && (string) $info?->academic_year_id !== (string) $academicYearId) {
                    return false;
                }
                if (! empty($filters['status']) && $student->status !== $filters['status']) {
                    return false;
                }

                $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
                if ($search !== '') {
                    $searchable = [
                        $student->name,
                        $student->bn_name,
                        $student->student_id,
                        $student->roll_no,
                        $student->father_name,
                        $student->mother_name,
                        $student->phone,
                        $student->email,
                    ];
                    $matches = collect($searchable)->contains(
                        fn ($value) => str_contains(mb_strtolower((string) $value), $search)
                    );

                    if (! $matches) {
                        return false;
                    }
                }

                return true;
            })
            ->sort(function (Student $first, Student $second) {
                $classOrder = ($first->currentClass?->numeric_value ?? PHP_INT_MAX)
                    <=> ($second->currentClass?->numeric_value ?? PHP_INT_MAX);
                if ($classOrder !== 0) {
                    return $classOrder;
                }

                $sectionOrder = strnatcasecmp(
                    (string) $first->currentSection?->name,
                    (string) $second->currentSection?->name
                );
                if ($sectionOrder !== 0) {
                    return $sectionOrder;
                }

                $rollOrder = strnatcasecmp(
                    (string) ($first->currentAcademicInfo?->roll_number ?? $first->roll_no),
                    (string) ($second->currentAcademicInfo?->roll_number ?? $second->roll_no)
                );

                return $rollOrder !== 0
                    ? $rollOrder
                    : strnatcasecmp((string) $first->name, (string) $second->name);
            })
            ->values()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->name,
                'bn_name' => $student->bn_name,
                'roll_no' => $student->currentAcademicInfo?->roll_number ?? $student->roll_no,
                'registration_no' => $student->registration_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'gender' => $student->gender,
                'phone' => $student->phone,
                'email' => $student->email,
                'status' => $student->status,
                'class_name' => $student->currentClass?->name,
                'class_group' => $student->currentClass?->group,
                'group' => $student->currentAcademicInfo?->group,
                'section_name' => $student->currentSection?->name,
                'academic_year' => $student->currentAcademicInfo?->academicYear?->session,
            ]);

        $institute = Institute::findOrFail($instituteId);

        return Inertia::render('Institute/Students/Print', [
            'institute' => [
                ...$institute->only(['name', 'address', 'phone', 'eiin']),
                'logo_url' => $institute->logo
                    ? Storage::disk('public')->url($institute->logo)
                    : null,
            ],
            'students' => $students,
            'classes' => InstituteClass::forInstitute($instituteId)
                ->ordered()
                ->get(['id', 'name', 'group']),
            'filters' => [
                'class_id' => $classId,
                'class_name' => $classId
                    ? InstituteClass::forInstitute($instituteId)->findOrFail($classId)->name
                    : null,
                'section_name' => $sectionId
                    ? Section::forInstitute($instituteId)->findOrFail($sectionId)->name
                    : null,
                'status' => $filters['status'] ?? null,
                'academic_year_id' => $academicYearId,
                'academic_year' => $academicYearId
                    ? AcademicYear::forInstitute($instituteId)->findOrFail($academicYearId)->session
                    : null,
                'search' => $filters['search'] ?? null,
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
        public function index()
    {
        $instituteId =Auth::user()->institute_id;

        // Get all students with relationships
        $students = Student::forInstitute($instituteId)
            ->with(['currentClass', 'currentSection', 'currentAcademicInfo.academicYear'])
            ->latest()
            ->get()
            ->map(function($student) {
                $currentAcademicInfo = $student->currentAcademicInfo;
                
                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                    'bn_name' => $student->bn_name,
                    'roll_no' => $student->roll_no,
                    'registration_no' => $student->registration_no,
                    'father_name' => $student->father_name,
                    'mother_name' => $student->mother_name,
                    'date_of_birth' => $student->date_of_birth,
                    'gender' => $student->gender,
                    'gender_label' => $student->gender_label,
                    'religion' => $student->religion,
                    'blood_group' => $student->blood_group,
                    'phone' => $student->phone,
                    'email' => $student->email,
                    'address' => $student->address,
                    'photo' => $student->photo_url,
                    'status' => $student->status,
                    'status_label' => $student->status_label,
                    'status_badge_class' => $student->status_badge_class,
                    
                    // Current class
                    'current_class' => $student->currentClass ? [
                        'id' => $student->currentClass->id,
                        'name' => $student->currentClass->name,
                        'group' => $student->currentClass->group,
                    ] : null,
                    
                    // Current section
                    'current_section' => $student->currentSection ? [
                        'id' => $student->currentSection->id,
                        'name' => $student->currentSection->name,
                    ] : null,
                    
                    // Current academic year
                    'current_academic_year' => $currentAcademicInfo && $currentAcademicInfo->academicYear ? [
                        'id' => $currentAcademicInfo->academicYear->id,
                        'session' => $currentAcademicInfo->academicYear->session,
                    ] : null,
                    
                    // Group and fee
                    'group' => $currentAcademicInfo ? $currentAcademicInfo->group : null,
                    'group_label' => $currentAcademicInfo ? $currentAcademicInfo->group_label : null,
                    'monthly_fee' => $currentAcademicInfo ? $currentAcademicInfo->monthly_fee : 0,
                    
                    'created_at' => $student->created_at,
                    'updated_at' => $student->updated_at
                ];
            });

        // Get dropdown data
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
            'total' => $students->count(),
            'active' => $students->where('status', 'active')->count(),
            'inactive' => $students->where('status', 'inactive')->count(),
            'passed' => $students->where('status', 'passed')->count(),
            'transferred' => $students->where('status', 'transferred')->count(),
            'male' => $students->where('gender', 'male')->count(),
            'female' => $students->where('gender', 'female')->count()
        ];

        return Inertia::render('Institute/Students/Index', [
            'students' => $students,
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            'stats' => $stats
        ]);
    }

     /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        $instituteId = Auth::user()->institute_id;

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $sections = Section::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']);

        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        $subjectChoices = $this->studentSubjectChoices($instituteId);

        return Inertia::render('Institute/Students/Create', [
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            ...$subjectChoices,
        ]);
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit(Student $student)
    {
        $instituteId = Auth::user()->institute_id;
        $student = Student::forInstitute($instituteId)->findOrFail($student->id);

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $sections = Section::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'institute_class_id']);

        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        // Load student with relationships
        $student->load(['academicInfos.academicYear', 'academicInfos.instituteClass', 'academicInfos.section']);
        $subjectChoices = $this->studentSubjectChoices($instituteId);

        // Get current academic info
        $currentAcademicInfo = $student->academicInfos->where('academicYear.is_current', true)->first();

        return Inertia::render('Institute/Students/Edit', [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->name,
                'bn_name' => $student->bn_name,
                'roll_no' => $student->roll_no,
                'registration_no' => $student->registration_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'date_of_birth' => $student->date_of_birth,
                'gender' => $student->gender,
                'religion' => $student->religion,
                'blood_group' => $student->blood_group,
                'phone' => $student->phone,
                'email' => $student->email,
                'address' => $student->address,
                'photo' => $student->photo,
                'status' => $student->status,
                'created_at' => $student->created_at,
                'updated_at' => $student->updated_at,
                'current_class' => $currentAcademicInfo?->instituteClass ? [
                    'id' => $currentAcademicInfo->instituteClass->id,
                    'name' => $currentAcademicInfo->instituteClass->name,
                    'group' => $currentAcademicInfo->instituteClass->group,
                ] : null,
                'current_section' => $currentAcademicInfo?->section ? [
                    'id' => $currentAcademicInfo->section->id,
                    'name' => $currentAcademicInfo->section->name,
                ] : null,
                'current_academic_year' => $currentAcademicInfo?->academicYear ? [
                    'id' => $currentAcademicInfo->academicYear->id,
                    'session' => $currentAcademicInfo->academicYear->session,
                ] : null,
                'roll_number' => $currentAcademicInfo?->roll_number ?? $student->roll_no,
                'monthly_fee' => $currentAcademicInfo?->monthly_fee ?? 0,
                'group' => $currentAcademicInfo?->group ?? null,
                'elective_subject_id' => $currentAcademicInfo?->elective_subject_id,
                'optional_subject_id' => $currentAcademicInfo?->optional_subject_id,
                'additional_subject_id' => $currentAcademicInfo?->additional_subject_id,
                'has_login_account' => (bool) $student->user_id,
            ],
            'classes' => $classes,
            'sections' => $sections,
            'academicYears' => $academicYears,
            ...$subjectChoices,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
     public function store(Request $request)
    {
        $instituteId = Auth::user()->institute_id;

        $validator = Validator::make($request->all(), [
            // Student info
            'student_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_id'),
            ],
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'religion' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:5',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'status' => 'required|in:active,inactive,passed,transferred',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            
            // Academic info
            'academic_year_id' => 'required|exists:academic_years,id',
            'institute_class_id' => 'required|exists:institute_classes,id',
            'section_id' => 'required|exists:sections,id',
            'roll_number' => 'required|string|max:50',
            'monthly_fee' => 'nullable|numeric|min:0',
            'group' => 'nullable|in:science,commerce,arts,general',
            'elective_subject_id' => 'nullable|integer',
            'optional_subject_id' => 'nullable|integer',
            'additional_subject_id' => 'nullable|integer',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->validateStudentSubjectChoices($validator, $request, $instituteId);
        $this->validateStudentLoginId($validator, $request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();
        $selectedClass = InstituteClass::forInstitute($instituteId)
            ->findOrFail($validated['institute_class_id']);
        $validated['group'] = $selectedClass->group
            ?: ($validated['group'] ?? null);

        // Check roll number uniqueness
        $rollExists = StudentAcademicInfo::where('academic_year_id', $request->academic_year_id)
            ->where('institute_class_id', $request->institute_class_id)
            ->where('roll_number', $request->roll_number)
            ->exists();

        if ($rollExists) {
            return redirect()->back()
                ->with('error', 'Roll number already exists for this class and academic year.')
                ->withInput();
        }

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('student-photos', 'public');
        }

        DB::transaction(function () use ($request, $validated, $instituteId, $photoPath) {
            $student = Student::create([
                'institute_id' => $instituteId,
                'student_id' => $request->student_id,
                'roll_no' => $request->roll_number,
                'registration_no' => $request->registration_no,
                'name' => $request->name,
                'bn_name' => $request->bn_name,
                'father_name' => $request->father_name,
                'mother_name' => $request->mother_name,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'religion' => $request->religion,
                'blood_group' => $request->blood_group,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'photo' => $photoPath,
                'status' => $request->status ?? 'active',
            ]);

            StudentAcademicInfo::create([
                'student_id' => $student->id,
                'academic_year_id' => $validated['academic_year_id'],
                'institute_class_id' => $validated['institute_class_id'],
                'section_id' => $validated['section_id'],
                'roll_number' => $validated['roll_number'],
                'monthly_fee' => $validated['monthly_fee'] ?? 0,
                'group' => $validated['group'] ?? null,
                'elective_subject_id' => $validated['elective_subject_id'] ?? null,
                'optional_subject_id' => $validated['optional_subject_id'] ?? null,
                'additional_subject_id' => $validated['additional_subject_id'] ?? null,
                'attendance_days' => 0,
                'is_promoted' => false,
            ]);

            $this->syncStudentLoginAccount($student, $request->input('password'));
        });

        return redirect()->route('students.index')
            ->with('success', 'Student created successfully.');
    }

    /**
     * Display the specified resource.
     */
        public function show(Student $student)
    {
        $instituteId = Auth::user()->institute_id;
        $student = Student::forInstitute($instituteId)->findOrFail($student->id);

        // Load relationships
        $student->load([
            'academicInfos.academicYear',
            'academicInfos.instituteClass',
            'academicInfos.section',
            'academicInfos.electiveSubject',
            'academicInfos.optionalSubject',
            'academicInfos.additionalSubject',
            'currentClass',
            'currentSection',
            'currentAcademicInfo.academicYear'
        ]);

        // Get current academic info
        $currentAcademicInfo = $student->currentAcademicInfo;

        return Inertia::render('Institute/Students/View', [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->name,
                'bn_name' => $student->bn_name,
                'roll_no' => $student->roll_no,
                'registration_no' => $student->registration_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'date_of_birth' => $student->date_of_birth,
                'gender' => $student->gender,
                'religion' => $student->religion,
                'blood_group' => $student->blood_group,
                'phone' => $student->phone,
                'email' => $student->email,
                'address' => $student->address,
                'photo' => $student->photo_url,
                'status' => $student->status,
                'age' => $student->age,
                'created_at' => $student->created_at,
                
                // Current class
                'current_class' => $student->currentClass ? [
                    'id' => $student->currentClass->id,
                    'name' => $student->currentClass->name,
                    'group' => $student->currentClass->group,
                ] : null,
                
                // Current section
                'current_section' => $student->currentSection ? [
                    'id' => $student->currentSection->id,
                    'name' => $student->currentSection->name,
                ] : null,
                
                // Current academic year
                'current_academic_year' => $currentAcademicInfo && $currentAcademicInfo->academicYear ? [
                    'id' => $currentAcademicInfo->academicYear->id,
                    'session' => $currentAcademicInfo->academicYear->session,
                ] : null,
                
                // Academic history
                'academic_infos' => $student->academicInfos->map(function($info) {
                    return [
                        'id' => $info->id,
                        'academic_year' => $info->academicYear->session ?? 'N/A',
                        'class_name' => $info->instituteClass->name ?? 'N/A',
                        'class_group' => $info->instituteClass->group,
                        'section_name' => $info->section->name ?? 'N/A',
                        'roll_number' => $info->roll_number,
                        'monthly_fee' => $info->monthly_fee,
                        'group' => $info->group,
                        'elective_subject' => $info->electiveSubject?->name,
                        'optional_subject' => $info->optionalSubject?->name,
                        'additional_subject' => $info->additionalSubject?->name,
                        'attendance_days' => $info->attendance_days,
                        'is_promoted' => $info->is_promoted,
                    ];
                })
            ]
        ]);

    }

    /**
     * Update the specified resource in storage.
     */
     public function update(Request $request, Student $student)
    {
        $instituteId = Auth::user()->institute_id;
        $student = Student::forInstitute($instituteId)->findOrFail($student->id);

        $validator = Validator::make($request->all(), [
            'student_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_id')->ignore($student->id),
            ],
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'religion' => 'nullable|string|max:50',
            'blood_group' => 'nullable|string|max:5',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string',
            'status' => 'required|in:active,inactive,passed,transferred',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'remove_photo' => 'nullable|boolean',
            
            // Academic info
            'academic_year_id' => 'required|exists:academic_years,id',
            'institute_class_id' => 'required|exists:institute_classes,id',
            'section_id' => 'required|exists:sections,id',
            'roll_number' => 'required|string|max:50',
            'monthly_fee' => 'nullable|numeric|min:0',
            'group' => 'nullable|in:science,commerce,arts,general',
            'elective_subject_id' => 'nullable|integer',
            'optional_subject_id' => 'nullable|integer',
            'additional_subject_id' => 'nullable|integer',
            'password' => [
                $student->user_id ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $this->validateStudentSubjectChoices($validator, $request, $instituteId);
        $this->validateStudentLoginId($validator, $request, $student->user_id);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $selectedClass = InstituteClass::forInstitute($instituteId)
            ->findOrFail($request->institute_class_id);
        $studentGroup = $selectedClass->group ?: $request->group;

        // Check roll number uniqueness
        $rollExists = StudentAcademicInfo::where('academic_year_id', $request->academic_year_id)
            ->where('institute_class_id', $request->institute_class_id)
            ->where('roll_number', $request->roll_number)
            ->where('student_id', '!=', $student->id)
            ->exists();

        if ($rollExists) {
            return redirect()->back()
                ->with('error', 'Roll number already exists for this class and academic year.')
                ->withInput();
        }

        // Handle photo upload
        $photoPath = $student->photo;
        
        // If remove_photo is true, delete the photo
        if ($request->remove_photo == '1' || $request->remove_photo === true) {
            if ($student->photo && Storage::disk('public')->exists($student->photo)) {
                Storage::disk('public')->delete($student->photo);
            }
            $photoPath = null;
        }
        
        // If new photo is uploaded
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($student->photo && Storage::disk('public')->exists($student->photo)) {
                Storage::disk('public')->delete($student->photo);
            }
            $photoPath = $request->file('photo')->store('student-photos', 'public');
        }

        // Update student
        $student->update([
            'student_id' => $request->student_id,
            'roll_no' => $request->roll_number,
            'registration_no' => $request->registration_no,
            'name' => $request->name,
            'bn_name' => $request->bn_name,
            'father_name' => $request->father_name,
            'mother_name' => $request->mother_name,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'religion' => $request->religion,
            'blood_group' => $request->blood_group,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'photo' => $photoPath,
            'status' => $request->status ?? 'active'
        ]);

        $this->syncStudentLoginAccount($student, $request->input('password'));

        // Update or create academic info
        $academicInfo = StudentAcademicInfo::where('student_id', $student->id)
            ->where('academic_year_id', $request->academic_year_id)
            ->first();

        if ($academicInfo) {
            $academicInfo->update([
                'institute_class_id' => $request->institute_class_id,
                'section_id' => $request->section_id,
                'roll_number' => $request->roll_number,
                'monthly_fee' => $request->monthly_fee ?? 0,
                'group' => $studentGroup,
                'elective_subject_id' => $request->elective_subject_id,
                'optional_subject_id' => $request->optional_subject_id,
                'additional_subject_id' => $request->additional_subject_id,
            ]);
        } else {
            StudentAcademicInfo::create([
                'student_id' => $student->id,
                'academic_year_id' => $request->academic_year_id,
                'institute_class_id' => $request->institute_class_id,
                'section_id' => $request->section_id,
                'roll_number' => $request->roll_number,
                'monthly_fee' => $request->monthly_fee ?? 0,
                'group' => $studentGroup,
                'elective_subject_id' => $request->elective_subject_id,
                'optional_subject_id' => $request->optional_subject_id,
                'additional_subject_id' => $request->additional_subject_id,
                'attendance_days' => 0,
                'is_promoted' => false,
            ]);
        }

        return redirect()->route('students.index')
            ->with('success', 'Student updated successfully.');
    }

    private function studentSubjectChoices(int $instituteId): array
    {
        return collect(['elective', 'optional', 'additional'])
            ->mapWithKeys(fn (string $type) => [
                $type . 'Subjects' => $this->studentSubjectsForInstitute($instituteId, $type),
            ])
            ->all();
    }

    private function syncStudentLoginAccount(Student $student, ?string $password): void
    {
        $user = $student->user;
        $attributes = [
            'name' => $student->name,
            'username' => Str::lower($student->student_id),
            'institute_id' => $student->institute_id,
            'usertype' => 'student',
        ];

        if (! $user) {
            $attributes['email'] = 'student-' . $student->id . '@accounts.invalid';
        }
        if ($password !== null && $password !== '') {
            $attributes['password'] = $password;
        }

        if ($user) {
            $user->update($attributes);
            return;
        }

        $user = User::create($attributes);
        $student->update(['user_id' => $user->id]);
    }

    private function studentSubjectsForInstitute(int $instituteId, string $type)
    {
        return DB::table('institute_class_institute_subjects')
            ->join('institute_classes', 'institute_classes.id', '=', 'institute_class_institute_subjects.institute_class_id')
            ->join('institute_subjects', 'institute_subjects.id', '=', 'institute_class_institute_subjects.institute_subject_id')
            ->where('institute_classes.institute_id', $instituteId)
            ->where('institute_subjects.institute_id', $instituteId)
            ->where('institute_class_institute_subjects.type', $type)
            ->orderBy('institute_subjects.display_order')
            ->orderBy('institute_subjects.name')
            ->get([
                'institute_subjects.id',
                'institute_subjects.name',
                'institute_subjects.code',
                'institute_classes.id as class_id',
            ]);
    }

    private function validateStudentSubjectChoices($validator, Request $request, int $instituteId): void
    {
        $validator->after(function ($validator) use ($request, $instituteId) {
            $classId = $request->input('institute_class_id');
            $class = is_numeric($classId)
                ? InstituteClass::forInstitute($instituteId)->find($classId)
                : null;
            foreach ([
                'elective' => 'elective_subject_id',
                'optional' => 'optional_subject_id',
                'additional' => 'additional_subject_id',
            ] as $type => $field) {
                $hasChoices = $class !== null
                    && $class->subjects()->wherePivot('type', $type)->exists();

                if ($hasChoices && ! $request->filled($field)) {
                    $validator->errors()->add($field, "Please select one {$type} subject.");
                }

                if ($request->filled($field)) {
                    $subject = InstituteSubject::forInstitute($instituteId)
                        ->find($request->input($field));
                    $isValidChoice = is_numeric($classId) && $subject !== null
                        && $subject->classes()
                            ->wherePivot('type', $type)
                            ->whereKey($classId)
                            ->exists();

                    if (! $isValidChoice) {
                        $validator->errors()->add($field, "Select a {$type} subject assigned to this class.");
                    }
                }
            }
        });
    }

    private function validateStudentLoginId($validator, Request $request, ?int $ignoreUserId = null): void
    {
        $validator->after(function ($validator) use ($request, $ignoreUserId) {
            $usernameQuery = User::query()
                ->whereRaw('LOWER(username) = ?', [Str::lower((string) $request->input('student_id'))]);

            if ($ignoreUserId) {
                $usernameQuery->where('id', '!=', $ignoreUserId);
            }

            if ($request->filled('student_id') && $usernameQuery->exists()) {
                $validator->errors()->add('student_id', 'This Student ID is already used as a login name.');
            }
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        $student = Student::forInstitute(Auth::user()->institute_id)->findOrFail($student->id);

        // Check if student has marks
        if ($student->marks()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete student. They have marks recorded.');
        }

        // Check if student has results
        if ($student->results()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete student. They have results recorded.');
        }

        // Delete academic info first
        $student->academicInfos()->delete();
        
        $user = $student->user;
        $student->delete();
        $user?->delete();

        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully.');
    }

    /**
     * Toggle student status.
     */
    public function toggleStatus(Student $student)
    {
        $student = Student::forInstitute(Auth::user()->institute_id)->findOrFail($student->id);

        $newStatus = $student->status === 'active' ? 'inactive' : 'active';
        $student->update(['status' => $newStatus]);

        return redirect()->back()
            ->with('success', 'Student status updated successfully.');
    }

    /**
     * Get students by class (API).
     */
      public function getByClassSection(Request $request)
    {
        $instituteId = Auth::user()->institute_id;

        $classId = $request->class_id;
        $sectionId = $request->section_id;

        // Get students for the selected class and section
        $students = Student::forInstitute($instituteId)
            ->whereHas('academicInfos', function($query) use ($classId, $sectionId) {
                $query->where('institute_class_id', $classId)
                      ->where('section_id', $sectionId);
            })
            ->with(['currentAcademicInfo'])
            ->orderBy('roll_no')
            ->get()
            ->map(function($student) {
                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                    'roll_no' => $student->roll_no,
                    'gender' => $student->gender ?? 'male',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }

    /**
     * Get students by class (Alternative method).
     */
    public function getStudentsByClass($classId)
    {
        $instituteId = Auth::user()->institute_id;

        $students = Student::forInstitute($instituteId)
            ->forClass($classId)
            ->active()
            ->get(['id', 'name', 'roll_no', 'student_id']);

        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }
}