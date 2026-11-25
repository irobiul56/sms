<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->institute = Institute::create(['user_id' => $this->user->id]);
    $this->user->update(['institute_id' => $this->institute->id]);
});

function validTeacherPayload(array $overrides = []): array
{
    return array_merge([
        'employee_id' => 'EMP-TEST-1',
        'name' => 'Test Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'teacher@example.test',
        'account_email' => 'teacher-login@example.test',
        'password' => 'TeacherPass123!',
        'password_confirmation' => 'TeacherPass123!',
        'gender' => 'other',
        'joining_date' => now()->toDateString(),
        'status' => 'active',
    ], $overrides);
}

test('teachers can be added without assigning a class', function () {
    $this->actingAs($this->user)
        ->post(route('teachers.store'), validTeacherPayload())
        ->assertRedirect(route('teachers.index'));

    $this->assertDatabaseHas('teachers', [
        'institute_id' => $this->institute->id,
        'employee_id' => 'EMP-TEST-1',
    ]);
    $this->assertDatabaseHas('users', [
        'email' => 'teacher-login@example.test',
        'institute_id' => $this->institute->id,
        'usertype' => 'teacher',
    ]);
    $account = User::where('email', 'teacher-login@example.test')->firstOrFail();
    expect(Hash::check('TeacherPass123!', $account->password))->toBeTrue();
    $this->assertDatabaseCount('class_teachers', 0);
});

test('teachers can be assigned to a class without requiring a section', function () {
    $class = InstituteClass::create([
        'institute_id' => $this->institute->id,
        'name' => 'Class 6',
        'numeric_value' => 6,
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

    $this->actingAs($this->user)
        ->post(route('teachers.store'), validTeacherPayload([
            'class_ids' => [$class->id],
            'section_ids' => [$class->id => ''],
            'class_permissions' => [$class->id => ['attendance.manage']],
            'academic_year_id' => $academicYear->id,
        ]))
        ->assertRedirect(route('teachers.index'));

    $teacher = Teacher::query()->where('employee_id', 'EMP-TEST-1')->firstOrFail();
    $this->assertDatabaseHas('class_teachers', [
        'teacher_id' => $teacher->id,
        'institute_class_id' => $class->id,
        'section_id' => null,
        'academic_year_id' => $academicYear->id,
    ]);
    $assignment = ClassTeacher::query()->where('teacher_id', $teacher->id)->firstOrFail();
    expect($assignment->permissions)->toContain('attendance.manage');
    expect(app(\App\Services\TeacherPermissionService::class)->hasClassPermission(
        $teacher->user,
        'attendance.manage',
        $class->id,
        null,
        $academicYear->id
    ))->toBeTrue();
});

test('class assignment requires an academic year when none is current', function () {
    $class = InstituteClass::create([
        'institute_id' => $this->institute->id,
        'name' => 'Class 6',
        'numeric_value' => 6,
    ]);

    $this->actingAs($this->user)
        ->post(route('teachers.store'), validTeacherPayload([
            'class_ids' => [$class->id],
        ]))
        ->assertSessionHasErrors('academic_year_id');

    $this->assertDatabaseCount('teachers', 0);
});
