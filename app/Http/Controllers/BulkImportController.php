<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BulkImportController extends Controller
{
    private const MAX_ROWS = 500;

    public function subjects(): Response
    {
        $instituteId = $this->instituteId();

        return Inertia::render('Institute/Subjects/Import', [
            'classes' => InstituteClass::forInstitute($instituteId)
                ->ordered()
                ->get(['id', 'name', 'numeric_value', 'group']),
        ]);
    }

    public function importSubjects(Request $request): RedirectResponse
    {
        $instituteId = $this->instituteId();
        $selection = $request->validate([
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
        ]);
        $classIds = array_map('intval', $selection['class_ids']);
        $rows = $this->validatedRows($request);
        $errors = [];
        $prepared = [];
        $seenCodes = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $row = array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                $row
            );
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'bn_name' => ['nullable', 'string', 'max:255'],
                'code' => ['required', 'string', 'max:50', Rule::unique('institute_subjects', 'code')],
                'type' => ['required', Rule::in(['compulsory', 'optional', 'elective', 'additional'])],
                'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
                'pass_marks' => ['required', 'integer', 'min:0', 'max:1000', 'lt:full_marks'],
                'theory_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'theory_pass_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'practical_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'practical_pass_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'mcq_marks' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'mcq_pass_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'display_order' => ['nullable', 'integer', 'min:0'],
                'has_practical' => ['nullable', 'boolean'],
            ]);

            $validator->after(function ($validator) use ($row) {
                foreach ([
                    'theory_pass_marks' => 'theory_marks',
                    'practical_pass_marks' => 'practical_marks',
                    'mcq_pass_marks' => 'mcq_marks',
                ] as $passField => $maximumField) {
                    $passMark = $row[$passField] ?? null;
                    $maximum = $row[$maximumField] ?? null;

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

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Row {$rowNumber}: {$message}";
                }
                continue;
            }

            $data = $validator->validated();
            $codeKey = mb_strtolower($data['code']);
            if (isset($seenCodes[$codeKey])) {
                $errors[] = "Row {$rowNumber}: code '{$data['code']}' is duplicated in this file.";
            }
            $seenCodes[$codeKey] = true;
            $prepared[] = $data;
        }

        if ($errors) {
            return back()->withErrors(['import' => implode("\n", $errors)]);
        }

        DB::transaction(function () use ($prepared, $instituteId, $classIds) {
            foreach ($prepared as $data) {
                $subject = InstituteSubject::create([
                    'institute_id' => $instituteId,
                    'name' => $data['name'],
                    'bn_name' => $data['bn_name'] ?? null,
                    'code' => $data['code'],
                    'type' => $data['type'],
                    'full_marks' => $data['full_marks'],
                    'pass_marks' => $data['pass_marks'],
                    'theory_marks' => $data['theory_marks'] ?? null,
                    'theory_pass_marks' => $data['theory_pass_marks'] ?? null,
                    'practical_marks' => $data['practical_marks'] ?? null,
                    'practical_pass_marks' => $data['practical_pass_marks'] ?? null,
                    'mcq_marks' => $data['mcq_marks'] ?? null,
                    'mcq_pass_marks' => $data['mcq_pass_marks'] ?? null,
                    'display_order' => $data['display_order'] ?? 0,
                    'has_practical' => $data['has_practical'] ?? false,
                ]);

                $assignments = [];
                foreach ($classIds as $classId) {
                    $assignments[$classId] = [
                        'type' => $data['type'],
                        'full_marks' => $data['full_marks'],
                        'pass_marks' => $data['pass_marks'],
                        'is_group_based' => false,
                        'group_name' => null,
                    ];
                }
                $subject->classes()->attach($assignments);
            }
        });

        return redirect()->route('subjects.index')
            ->with('message', count($prepared).' subjects imported successfully.');
    }

    public function students(): Response
    {
        $instituteId = $this->instituteId();

        return Inertia::render('Institute/Imports/BulkImport', [
            'kind' => 'students',
            'title' => 'Bulk Import Students',
            'columns' => [
                'student_id', 'name', 'bn_name', 'father_name', 'mother_name',
                'date_of_birth', 'gender', 'religion', 'blood_group', 'phone',
                'email', 'address', 'registration_no', 'roll_number', 'monthly_fee', 'password',
                'elective_subject_code', 'optional_subject_code', 'additional_subject_code',
            ],
            'requiredColumns' => [
                'student_id', 'name', 'father_name', 'mother_name', 'date_of_birth',
                'gender', 'address', 'roll_number', 'password',
            ],
            'classes' => InstituteClass::forInstitute($instituteId)->ordered()->get(['id', 'name', 'group']),
            'sections' => Section::forInstitute($instituteId)->ordered()->get(['id', 'name', 'institute_class_id']),
            'academicYears' => AcademicYear::forInstitute($instituteId)
                ->orderByDesc('year')
                ->get(['id', 'session', 'is_current']),
            'electiveSubjects' => $this->studentSubjectsForInstitute($instituteId, 'elective'),
            'optionalSubjects' => $this->studentSubjectsForInstitute($instituteId, 'optional'),
            'additionalSubjects' => $this->studentSubjectsForInstitute($instituteId, 'additional'),
            'returnRoute' => 'students.index',
        ]);
    }

    public function teachers(): Response
    {
        return Inertia::render('Institute/Imports/BulkImport', [
            'kind' => 'teachers',
            'title' => 'Bulk Import Teachers',
            'columns' => [
                'employee_id', 'name', 'bn_name', 'designation', 'phone', 'email',
                'gender', 'date_of_birth', 'joining_date', 'qualification', 'address', 'status',
            ],
            'requiredColumns' => [
                'employee_id', 'name', 'designation', 'phone', 'email', 'gender', 'joining_date',
            ],
            'returnRoute' => 'teachers.index',
        ]);
    }

    public function importStudents(Request $request): RedirectResponse
    {
        $instituteId = $this->instituteId();
        $selection = $request->validate([
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('institute_id', $instituteId),
            ],
            'class_id' => [
                'required',
                'integer',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'section_id' => ['required', 'integer'],
            'group' => ['nullable', Rule::in(['science', 'commerce', 'arts', 'general'])],
        ]);
        $selectedClass = InstituteClass::forInstitute($instituteId)
            ->findOrFail($selection['class_id']);
        $selection['group'] = $selectedClass->group
            ?: ($selection['group'] ?? null);
        $sectionExists = Section::forInstitute($instituteId)
            ->where('institute_class_id', $selection['class_id'])
            ->whereKey($selection['section_id'])
            ->exists();
        if (! $sectionExists) {
            return back()->withErrors(['section_id' => 'Select a section belonging to the selected class.']);
        }

        $choicesByType = collect(['elective', 'optional', 'additional'])
            ->mapWithKeys(fn (string $type) => [
                $type => DB::table('institute_class_institute_subjects')
                    ->join('institute_classes', 'institute_classes.id', '=', 'institute_class_institute_subjects.institute_class_id')
                    ->join('institute_subjects', 'institute_subjects.id', '=', 'institute_class_institute_subjects.institute_subject_id')
                    ->where('institute_classes.institute_id', $instituteId)
                    ->where('institute_subjects.institute_id', $instituteId)
                    ->where('institute_class_institute_subjects.institute_class_id', $selection['class_id'])
                    ->where('institute_class_institute_subjects.type', $type)
                    ->get(['institute_subjects.id', 'institute_subjects.code'])
                    ->keyBy(fn ($subject) => mb_strtolower($subject->code)),
            ]);

        $rows = $this->validatedRows($request);
        $errors = [];
        $prepared = [];
        $seenStudentIds = [];
        $seenRolls = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $validator = Validator::make($row, [
                'student_id' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'bn_name' => ['nullable', 'string', 'max:255'],
                'father_name' => ['required', 'string', 'max:255'],
                'mother_name' => ['required', 'string', 'max:255'],
                'date_of_birth' => ['required', 'date'],
                'gender' => ['required', Rule::in(['male', 'female', 'other'])],
                'religion' => ['nullable', 'string', 'max:50'],
                'blood_group' => ['nullable', 'string', 'max:5'],
                'phone' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['required', 'string'],
                'registration_no' => ['nullable', 'string', 'max:255'],
                'roll_number' => ['required', 'string', 'max:50'],
                'password' => ['required', 'string', 'min:8'],
                'monthly_fee' => ['nullable', 'numeric', 'min:0'],
                'elective_subject_code' => ['nullable', 'string', 'max:50'],
                'optional_subject_code' => ['nullable', 'string', 'max:50'],
                'additional_subject_code' => ['nullable', 'string', 'max:50'],
            ]);

            $validator->after(function ($validator) use ($row, $choicesByType) {
                foreach ($choicesByType as $type => $choices) {
                    $field = $type . '_subject_code';
                    $code = mb_strtolower(trim((string) ($row[$field] ?? '')));
                    if ($choices->isNotEmpty() && $code === '') {
                        $validator->errors()->add($field, "Select one {$type} subject code.");
                    } elseif ($code !== '' && ! $choices->has($code)) {
                        $validator->errors()->add($field, "Select a {$type} subject code assigned to this class.");
                    }
                }
            });

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Row {$rowNumber}: {$message}";
                }
                continue;
            }

            $data = $validator->validated();
            foreach ($choicesByType as $type => $choices) {
                $code = mb_strtolower(trim((string) ($data[$type . '_subject_code'] ?? '')));
                $data[$type . '_subject_id'] = $code === ''
                    ? null
                    : $choices->get($code)->id;
            }
            $studentId = trim($data['student_id']);
            $usernameExists = User::query()
                ->whereRaw('LOWER(username) = ?', [Str::lower($studentId)])
                ->exists();
            if (
                isset($seenStudentIds[$studentId])
                || Student::query()->where('student_id', $studentId)->exists()
                || $usernameExists
            ) {
                $errors[] = "Row {$rowNumber}: student_id '{$studentId}' already exists.";
            }
            $seenStudentIds[$studentId] = true;

            if ($studentId !== '') {
                $rollKey = $selection['academic_year_id'] . ':' . $selection['class_id'] . ':' . trim($data['roll_number']);
                $rollExists = StudentAcademicInfo::query()
                    ->where('academic_year_id', $selection['academic_year_id'])
                    ->where('institute_class_id', $selection['class_id'])
                    ->where('roll_number', trim($data['roll_number']))
                    ->exists();
                if (isset($seenRolls[$rollKey]) || $rollExists) {
                    $errors[] = "Row {$rowNumber}: roll_number '{$data['roll_number']}' is already used in this class and academic year.";
                }
                $seenRolls[$rollKey] = true;
            }

            $prepared[] = $data;
        }

        if ($errors) {
            return back()->withErrors(['import' => implode("\n", $errors)]);
        }

        DB::transaction(function () use ($prepared, $instituteId, $selection) {
            foreach ($prepared as $data) {
                $student = Student::create([
                    'institute_id' => $instituteId,
                    'student_id' => trim($data['student_id']),
                    'roll_no' => trim($data['roll_number']),
                    'registration_no' => $data['registration_no'] ?? null,
                    'name' => trim($data['name']),
                    'bn_name' => $data['bn_name'] ?? null,
                    'father_name' => trim($data['father_name']),
                    'mother_name' => trim($data['mother_name']),
                    'date_of_birth' => $data['date_of_birth'],
                    'gender' => $data['gender'],
                    'religion' => $data['religion'] ?? null,
                    'blood_group' => $data['blood_group'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'address' => trim($data['address']),
                    'status' => 'active',
                ]);
                $user = User::create([
                    'name' => trim($data['name']),
                    'email' => 'student-' . $student->id . '@accounts.invalid',
                    'username' => Str::lower(trim($data['student_id'])),
                    'password' => $data['password'],
                    'institute_id' => $instituteId,
                    'usertype' => 'student',
                ]);
                $student->update(['user_id' => $user->id]);
                StudentAcademicInfo::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $selection['academic_year_id'],
                    'institute_class_id' => $selection['class_id'],
                    'section_id' => $selection['section_id'],
                    'roll_number' => trim($data['roll_number']),
                    'monthly_fee' => $data['monthly_fee'] ?? 0,
                    'group' => $selection['group'],
                    'elective_subject_id' => $data['elective_subject_id'] ?? null,
                    'optional_subject_id' => $data['optional_subject_id'] ?? null,
                    'additional_subject_id' => $data['additional_subject_id'] ?? null,
                ]);
            }
        });

        return redirect()->route('students.index')->with('success', count($prepared) . ' students imported successfully.');
    }

    public function importTeachers(Request $request): RedirectResponse
    {
        $instituteId = $this->instituteId();
        $rows = $this->validatedRows($request);
        $errors = [];
        $prepared = [];
        $seenEmployeeIds = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $validator = Validator::make($row, [
                'employee_id' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'bn_name' => ['nullable', 'string', 'max:255'],
                'designation' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:20'],
                'email' => ['required', 'email', 'max:255'],
                'gender' => ['required', Rule::in(['male', 'female', 'other'])],
                'date_of_birth' => ['nullable', 'date'],
                'joining_date' => ['required', 'date'],
                'qualification' => ['nullable', 'string'],
                'address' => ['nullable', 'string'],
                'status' => ['nullable', Rule::in(['active', 'inactive', 'retired', 'transferred'])],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Row {$rowNumber}: {$message}";
                }
                continue;
            }

            $data = $validator->validated();
            $employeeId = trim($data['employee_id']);
            if (isset($seenEmployeeIds[$employeeId]) || Teacher::query()->where('employee_id', $employeeId)->exists()) {
                $errors[] = "Row {$rowNumber}: employee_id '{$employeeId}' already exists.";
            }
            $seenEmployeeIds[$employeeId] = true;
            $prepared[] = $data;
        }

        if ($errors) {
            return back()->withErrors(['import' => implode("\n", $errors)]);
        }

        DB::transaction(function () use ($prepared, $instituteId) {
            foreach ($prepared as $data) {
                Teacher::create([
                    'institute_id' => $instituteId,
                    'employee_id' => trim($data['employee_id']),
                    'name' => trim($data['name']),
                    'bn_name' => $data['bn_name'] ?? null,
                    'designation' => trim($data['designation']),
                    'phone' => trim($data['phone']),
                    'email' => trim($data['email']),
                    'gender' => $data['gender'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'joining_date' => $data['joining_date'],
                    'qualification' => $data['qualification'] ?? null,
                    'address' => $data['address'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);
            }
        });

        return redirect()->route('teachers.index')->with('success', count($prepared) . ' teachers imported successfully.');
    }

    private function validatedRows(Request $request): array
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:' . self::MAX_ROWS],
            'rows.*' => ['required', 'array'],
        ]);

        return $validated['rows'];
    }

    private function studentSubjectsForInstitute(int $instituteId, string $type)
    {
        return DB::table('institute_class_institute_subjects')
            ->join('institute_classes', 'institute_classes.id', '=', 'institute_class_institute_subjects.institute_class_id')
            ->join('institute_subjects', 'institute_subjects.id', '=', 'institute_class_institute_subjects.institute_subject_id')
            ->where('institute_classes.institute_id', $instituteId)
            ->where('institute_subjects.institute_id', $instituteId)
            ->where('institute_class_institute_subjects.type', $type)
            ->orderBy('institute_classes.numeric_value')
            ->orderBy('institute_subjects.name')
            ->get([
                'institute_classes.id as class_id',
                'institute_classes.name as class_name',
                'institute_subjects.code',
                'institute_subjects.name',
            ]);
    }

    private function instituteId(): int
    {
        $instituteId = (int) Auth::user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned to this account.');

        return $instituteId;
    }
}
