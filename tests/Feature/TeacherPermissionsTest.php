<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createPermissionManagementFixture(string $suffix = '1'): array
{
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);

    $teacher = Teacher::create([
        'institute_id' => $institute->id,
        'employee_id' => 'PERM-TEACHER-'.$suffix,
        'name' => 'Permission Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'permission-teacher-'.$suffix.'@example.test',
        'gender' => 'other',
        'joining_date' => now()->toDateString(),
        'status' => 'active',
    ]);
    $class = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Class 5',
        'numeric_value' => 5,
    ]);
    $subject = InstituteSubject::create([
        'institute_id' => $institute->id,
        'name' => 'Mathematics',
        'code' => 'PERM-MATH-'.$suffix,
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $academicYear = AcademicYear::create([
        'institute_id' => $institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);
    $classAssignment = ClassTeacher::create([
        'teacher_id' => $teacher->id,
        'institute_class_id' => $class->id,
        'academic_year_id' => $academicYear->id,
        'permissions' => [],
    ]);
    $subjectAssignment = TeacherSubject::create([
        'teacher_id' => $teacher->id,
        'institute_subject_id' => $subject->id,
        'institute_class_id' => $class->id,
        'academic_year_id' => $academicYear->id,
        'is_active' => true,
        'permissions' => [],
    ]);

    return compact('user', 'institute', 'teacher', 'classAssignment', 'subjectAssignment');
}

test('permission manager lists the teacher assignment scopes', function () {
    $fixture = createPermissionManagementFixture();

    $this->actingAs($fixture['user'])
        ->get(route('teacher-permissions.index', ['teacher_id' => $fixture['teacher']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/TeacherPermissions/Index')
            ->where('selectedTeacherId', $fixture['teacher']->id)
            ->where('classAssignments.0.class_name', 'Class 5')
            ->where('subjectAssignments.0.subject_name', 'Mathematics')
            ->where('classPermissionOptions', \App\Services\TeacherPermissionService::CLASS_PERMISSIONS)
            ->where('subjectPermissionOptions', \App\Services\TeacherPermissionService::SUBJECT_PERMISSIONS));
});

test('admin can update class and subject role permissions together', function () {
    $fixture = createPermissionManagementFixture();

    $this->actingAs($fixture['user'])
        ->put(route('teacher-permissions.update', $fixture['teacher']->id), [
            'class_assignments' => [[
                'id' => $fixture['classAssignment']->id,
                'permissions' => [
                    'attendance.view',
                    'attendance.manage',
                    'admit_cards.print',
                    'seat_plan.print',
                    'results.publish',
                    'marksheets.print',
                    'tabulation_sheet.print',
                ],
            ]],
            'subject_assignments' => [[
                'id' => $fixture['subjectAssignment']->id,
                'permissions' => ['marks.enter', 'marks.update'],
            ]],
        ])
        ->assertRedirect(route('teacher-permissions.index', ['teacher_id' => $fixture['teacher']->id]));

    expect($fixture['classAssignment']->fresh()->permissions)
        ->toBe([
            'attendance.view',
            'attendance.manage',
            'admit_cards.print',
            'seat_plan.print',
            'results.publish',
            'marksheets.print',
            'tabulation_sheet.print',
        ]);
    expect($fixture['subjectAssignment']->fresh()->permissions)
        ->toBe(['marks.enter', 'marks.update']);
});

test('permission manager rejects unknown permissions and assignments outside the teacher', function () {
    $fixture = createPermissionManagementFixture();
    $otherFixture = createPermissionManagementFixture('2');

    $this->actingAs($fixture['user'])
        ->put(route('teacher-permissions.update', $fixture['teacher']->id), [
            'class_assignments' => [[
                'id' => $fixture['classAssignment']->id,
                'permissions' => ['admin.manage'],
            ]],
            'subject_assignments' => [[
                'id' => $otherFixture['subjectAssignment']->id,
                'permissions' => ['marks.enter'],
            ]],
        ])
        ->assertSessionHasErrors([
            'class_assignments.0.permissions.0',
            'subject_assignments.0.id',
        ]);

    expect($fixture['classAssignment']->fresh()->permissions)->toBe([]);
    expect($otherFixture['subjectAssignment']->fresh()->permissions)->toBe([]);
});
