<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Section;
use App\Models\TeacherSubject;
use App\Models\Institute;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->instituteOwner = User::factory()->create();
    $this->institute = Institute::create(['user_id' => $this->instituteOwner->id]);
    $this->instituteOwner->update(['institute_id' => $this->institute->id]);
});

function createTeacherLogin(Institute $institute, array $overrides = []): array
{
    $user = User::create([
        'name' => 'Login Teacher',
        'email' => 'login-teacher@example.test',
        'password' => Hash::make('TeacherPass123!'),
        'institute_id' => $institute->id,
        'usertype' => 'teacher',
    ]);

    $teacher = Teacher::create(array_merge([
        'user_id' => $user->id,
        'institute_id' => $institute->id,
        'employee_id' => 'EMP-LOGIN-1',
        'name' => 'Login Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'profile-teacher@example.test',
        'gender' => 'other',
        'joining_date' => now()->toDateString(),
        'status' => 'active',
    ], $overrides));

    return [$user, $teacher];
}

test('active teachers can log in and reach only the teacher dashboard', function () {
    createTeacherLogin($this->institute);

    $this->post(route('login'), [
        'email' => 'login-teacher@example.test',
        'password' => 'TeacherPass123!',
    ])->assertRedirect(route('teacher.dashboard'));

    $this->get(route('teacher.dashboard'))->assertOk();
    $this->get(route('institute.dashboard'))->assertForbidden();
});

test('inactive teachers cannot log in', function () {
    createTeacherLogin($this->institute, ['status' => 'inactive']);

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => 'login-teacher@example.test',
            'password' => 'TeacherPass123!',
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('teachers without permissions cannot open institute work pages', function () {
    [$user] = createTeacherLogin($this->institute);

    $this->actingAs($user)
        ->get(route('marks.entry'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('attendance.index'))
        ->assertForbidden();
});

test('subject assignment permissions grant only the selected marks actions', function () {
    [$user, $teacher] = createTeacherLogin($this->institute);
    $class = InstituteClass::create([
        'institute_id' => $this->institute->id,
        'name' => 'Class 7',
        'numeric_value' => 7,
    ]);
    $subject = InstituteSubject::create([
        'institute_id' => $this->institute->id,
        'name' => 'Science',
        'code' => 'SCI-7',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $academicYear = AcademicYear::create([
        'institute_id' => $this->institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);
    $this->actingAs($this->instituteOwner)
        ->post(route('teacher-subjects.store'), [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'academic_year_id' => $academicYear->id,
            'permissions' => ['marks.enter'],
        ])
        ->assertRedirect(route('teacher-subjects.index'));
    $assignment = TeacherSubject::query()->where('teacher_id', $teacher->id)->firstOrFail();
    expect($assignment->permissions)->toContain('marks.enter');

    $this->actingAs($user)
        ->get(route('marks.entry'))
        ->assertOk();
    $this->get(route('marks.index'))->assertForbidden();

    $permissions = app(\App\Services\TeacherPermissionService::class);
    expect($permissions->hasSubjectPermission(
        $user,
        'marks.enter',
        $class->id,
        $subject->id,
        null,
        $academicYear->id
    ))->toBeTrue();
    expect($permissions->hasSubjectPermission(
        $user,
        'marks.update',
        $class->id,
        $subject->id,
        null,
        $academicYear->id
    ))->toBeFalse();
});

test('class permissions scope teacher access to attendance actions', function () {
    [$user, $teacher] = createTeacherLogin($this->institute);
    $class = InstituteClass::create([
        'institute_id' => $this->institute->id,
        'name' => 'Class 8',
        'numeric_value' => 8,
    ]);
    $section = Section::create([
        'institute_id' => $this->institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);
    $academicYear = AcademicYear::create([
        'institute_id' => $this->institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);
    $assignment = ClassTeacher::create([
        'teacher_id' => $teacher->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'academic_year_id' => $academicYear->id,
        'permissions' => ['attendance.view'],
    ]);

    $this->actingAs($user)
        ->get(route('attendance.index'))
        ->assertOk();
    $this->get(route('attendance.roster', [
        'class_id' => $class->id,
        'section_id' => $section->id,
        'date' => now()->toDateString(),
    ]))->assertForbidden();

    $assignment->update(['permissions' => ['attendance.view', 'attendance.manage']]);
    $this->get(route('attendance.roster', [
        'class_id' => $class->id,
        'section_id' => $section->id,
        'date' => now()->toDateString(),
    ]))->assertOk();
});

test('shared teacher sidebar permissions include only current year assignments', function () {
    [$user, $teacher] = createTeacherLogin($this->institute);
    $class = InstituteClass::create([
        'institute_id' => $this->institute->id,
        'name' => 'Class 9',
        'numeric_value' => 9,
    ]);
    $academicYear = AcademicYear::create([
        'institute_id' => $this->institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);
    $oldAcademicYear = AcademicYear::create([
        'institute_id' => $this->institute->id,
        'name' => '2025-2026',
        'year' => 2025,
        'session' => '2025-2026',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'is_current' => false,
    ]);
    ClassTeacher::create([
        'teacher_id' => $teacher->id,
        'institute_class_id' => $class->id,
        'academic_year_id' => $academicYear->id,
        'permissions' => ['attendance.view', 'admit_cards.print', 'results.publish'],
    ]);
    ClassTeacher::create([
        'teacher_id' => $teacher->id,
        'institute_class_id' => $class->id,
        'academic_year_id' => $oldAcademicYear->id,
        'permissions' => ['seat_plan.print'],
    ]);
    $subject = InstituteSubject::create([
        'institute_id' => $this->institute->id,
        'name' => 'History',
        'code' => 'HIS-9',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'institute_subject_id' => $subject->id,
        'institute_class_id' => $class->id,
        'academic_year_id' => $academicYear->id,
        'is_active' => true,
        'permissions' => ['marks.enter'],
    ]);

    $this->actingAs($user)
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('teacherPermissions', [
                'attendance.view' => true,
                'attendance.manage' => false,
                'admit_cards.print' => true,
                'seat_plan.print' => false,
                'results.publish' => true,
                'marksheets.print' => false,
                'tabulation_sheet.print' => false,
                'marks.view' => false,
                'marks.enter' => true,
                'marks.update' => false,
                'marks.delete' => false,
                'homework.manage' => false,
            ]));
});
