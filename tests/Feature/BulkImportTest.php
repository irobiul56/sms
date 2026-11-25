<?php

use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Models\User;

function createBulkImportFixture(): array
{
    $user = User::factory()->create();
    $institute = Institute::create([
        'user_id' => $user->id,
        'name' => 'Bulk Import School',
    ]);
    $user->update(['institute_id' => $institute->id]);
    $year = AcademicYear::create([
        'institute_id' => $institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);
    $class = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Class 1',
        'numeric_value' => 1,
    ]);
    $section = Section::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);

    return compact('user', 'institute', 'year', 'class', 'section');
}

test('subjects can be bulk imported into multiple selected classes', function () {
    $fixture = createBulkImportFixture();
    $secondClass = InstituteClass::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 2',
        'numeric_value' => 2,
    ]);
    $row = [
        'name' => 'Biology',
        'bn_name' => 'জীববিজ্ঞান',
        'code' => 'BIO-001',
        'type' => 'optional',
        'full_marks' => '100',
        'pass_marks' => '33',
        'theory_marks' => '70',
        'theory_pass_marks' => '23',
        'practical_marks' => '30',
        'practical_pass_marks' => '10',
        'display_order' => '1',
        'has_practical' => '1',
    ];

    $this->actingAs($fixture['user'])
        ->get(route('subjects.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institute/Subjects/Import')
            ->has('classes', 2));

    $this->actingAs($fixture['user'])
        ->post(route('subjects.import.store'), [
            'class_ids' => [$fixture['class']->id, $secondClass->id],
            'rows' => [$row],
        ])
        ->assertRedirect(route('subjects.index'));

    $subject = InstituteSubject::forInstitute($fixture['institute']->id)->firstOrFail();
    $assignedClasses = $subject->classes()->orderBy('numeric_value')->get();
    expect($subject->name)->toBe('Biology')
        ->and($subject->type)->toBe('optional')
        ->and($subject->has_practical)->toBeTrue()
        ->and($assignedClasses->pluck('id')->all())->toBe([$fixture['class']->id, $secondClass->id])
        ->and($assignedClasses->pluck('pivot.type')->unique()->all())->toBe(['optional']);
});

test('subject import is all or nothing for duplicate codes', function () {
    $fixture = createBulkImportFixture();
    $row = [
        'name' => 'Biology',
        'code' => 'BIO-002',
        'type' => 'optional',
        'full_marks' => '100',
        'pass_marks' => '33',
    ];

    $this->actingAs($fixture['user'])
        ->post(route('subjects.import.store'), [
            'class_ids' => [$fixture['class']->id],
            'rows' => [$row, [...$row, 'name' => 'Second Biology']],
        ])
        ->assertSessionHasErrors('import');

    expect(InstituteSubject::forInstitute($fixture['institute']->id)->count())->toBe(0);
});

test('student xlsx import endpoint creates students and current academic records', function () {
    $fixture = createBulkImportFixture();
    $rows = [
        [
            'student_id' => 'BULK-001',
            'name' => 'Alice',
            'father_name' => 'Parent One',
            'mother_name' => 'Parent Two',
            'date_of_birth' => '2018-01-01',
            'gender' => 'female',
            'address' => 'School Road',
            'roll_number' => '1',
            'password' => 'StudentPass123!',
        ],
        [
            'student_id' => 'BULK-002',
            'name' => 'Bob',
            'father_name' => 'Parent One',
            'mother_name' => 'Parent Two',
            'date_of_birth' => '2018-02-01',
            'gender' => 'male',
            'address' => 'School Road',
            'roll_number' => '2',
            'password' => 'StudentPass456!',
        ],
    ];

    $this->actingAs($fixture['user'])
        ->get(route('students.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institute/Imports/BulkImport')
            ->where('kind', 'students')
            ->where('columns', [
                'student_id', 'name', 'bn_name', 'father_name', 'mother_name',
                'date_of_birth', 'gender', 'religion', 'blood_group', 'phone',
                'email', 'address', 'registration_no', 'roll_number', 'monthly_fee',
                'password', 'elective_subject_code', 'optional_subject_code', 'additional_subject_code',
            ])
            ->has('classes', 1)
            ->has('sections', 1)
            ->has('academicYears', 1));

    $this->actingAs($fixture['user'])
        ->post(route('students.import.store'), [
            'rows' => $rows,
            'academic_year_id' => $fixture['year']->id,
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'group' => 'science',
        ])
        ->assertRedirect(route('students.index'));

    expect(Student::forInstitute($fixture['institute']->id)->count())->toBe(2)
        ->and(User::query()->where('usertype', 'student')->count())->toBe(2)
        ->and(User::query()->where('username', 'bulk-001')->firstOrFail()->password)
        ->toBeString()
        ->not->toBe('StudentPass123!')
        ->and(Student::forInstitute($fixture['institute']->id)->pluck('status')->unique()->all())->toBe(['active'])
        ->and(StudentAcademicInfo::query()
            ->where('academic_year_id', $fixture['year']->id)
            ->where('institute_class_id', $fixture['class']->id)
            ->where('section_id', $fixture['section']->id)
            ->where('group', 'science')
            ->count())->toBe(2);
});

test('student bulk import does not require a group when the class has none', function () {
    $fixture = createBulkImportFixture();
    $row = [
        'student_id' => 'BULK-NO-GROUP',
        'name' => 'Ungrouped Student',
        'father_name' => 'Parent One',
        'mother_name' => 'Parent Two',
        'date_of_birth' => '2018-01-01',
        'gender' => 'female',
        'address' => 'School Road',
        'roll_number' => '1',
        'password' => 'StudentPass123!',
    ];

    $this->actingAs($fixture['user'])
        ->get(route('students.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institute/Imports/BulkImport')
            ->has('sections', 1));

    $this->actingAs($fixture['user'])
        ->post(route('students.import.store'), [
            'rows' => [$row],
            'academic_year_id' => $fixture['year']->id,
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
        ])
        ->assertRedirect(route('students.index'));

    expect(StudentAcademicInfo::query()->firstOrFail()->group)->toBeNull();
});

test('student bulk import is all or nothing when an uploaded row is invalid', function () {
    $fixture = createBulkImportFixture();
    $valid = [
        'student_id' => 'BULK-001',
        'name' => 'Alice',
        'father_name' => 'Parent One',
        'mother_name' => 'Parent Two',
        'date_of_birth' => '2018-01-01',
        'gender' => 'female',
        'address' => 'School Road',
        'roll_number' => '1',
        'password' => 'StudentPass123!',
    ];

    $this->actingAs($fixture['user'])
        ->post(route('students.import.store'), [
            'rows' => [$valid, [...$valid, 'student_id' => 'BULK-002', 'gender' => 'invalid']],
            'academic_year_id' => $fixture['year']->id,
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'group' => 'general',
        ])
        ->assertSessionHasErrors('import');

    expect(Student::forInstitute($fixture['institute']->id)->count())->toBe(0);
});

test('student bulk import requires and saves elective subject codes for the selected class', function () {
    $fixture = createBulkImportFixture();
    $subjects = [];
    foreach ([
        'elective' => 'BULK-EL',
        'optional' => 'BULK-OP',
        'additional' => 'BULK-AD',
    ] as $type => $code) {
        $subject = InstituteSubject::create([
            'institute_id' => $fixture['institute']->id,
            'name' => ucfirst($type).' Subject',
            'code' => $code,
            'type' => $type,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        $fixture['class']->subjects()->attach($subject->id, [
            'type' => $type,
            'full_marks' => 100,
            'pass_marks' => 33,
            'is_group_based' => false,
        ]);
        $subjects[$type] = $subject;
    }
    $row = [
        'student_id' => 'BULK-EL-001',
        'name' => 'Elective Student',
        'father_name' => 'Parent One',
        'mother_name' => 'Parent Two',
        'date_of_birth' => '2018-01-01',
        'gender' => 'female',
        'address' => 'School Road',
        'roll_number' => '1',
        'password' => 'StudentPass123!',
    ];

    $selection = [
        'academic_year_id' => $fixture['year']->id,
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'group' => 'science',
    ];

    $this->actingAs($fixture['user'])
        ->post(route('students.import.store'), ['rows' => [$row], ...$selection])
        ->assertSessionHasErrors('import');
    expect(Student::forInstitute($fixture['institute']->id)->count())->toBe(0);

    $this->actingAs($fixture['user'])
        ->post(route('students.import.store'), [
            'rows' => [[
                ...$row,
                'elective_subject_code' => 'BULK-EL',
                'optional_subject_code' => 'BULK-OP',
                'additional_subject_code' => 'BULK-AD',
            ]],
            ...$selection,
        ])
        ->assertRedirect(route('students.index'));

    $academicInfo = StudentAcademicInfo::query()->firstOrFail();
    expect($academicInfo->elective_subject_id)->toBe($subjects['elective']->id)
        ->and($academicInfo->optional_subject_id)->toBe($subjects['optional']->id)
        ->and($academicInfo->additional_subject_id)->toBe($subjects['additional']->id);
});

test('teacher xlsx import endpoint creates teachers in the authenticated institute', function () {
    $fixture = createBulkImportFixture();
    $rows = [
        [
            'employee_id' => 'TEA-BULK-1',
            'name' => 'Teacher One',
            'designation' => 'Teacher',
            'phone' => '01700000001',
            'email' => 'bulk-teacher1@example.test',
            'gender' => 'female',
            'joining_date' => '2025-01-01',
        ],
        [
            'employee_id' => 'TEA-BULK-2',
            'name' => 'Teacher Two',
            'designation' => 'Teacher',
            'phone' => '01700000002',
            'email' => 'bulk-teacher2@example.test',
            'gender' => 'male',
            'joining_date' => '2025-01-01',
        ],
    ];

    $this->actingAs($fixture['user'])
        ->get(route('teachers.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institute/Imports/BulkImport')
            ->where('kind', 'teachers'));

    $this->actingAs($fixture['user'])
        ->post(route('teachers.import.store'), ['rows' => $rows])
        ->assertRedirect(route('teachers.index'));

    expect(Teacher::forInstitute($fixture['institute']->id)->count())->toBe(2);
});
