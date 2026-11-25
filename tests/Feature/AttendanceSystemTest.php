<?php

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createAttendanceFixture(): array
{
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id, 'name' => 'Attendance School']);
    $user->update(['institute_id' => $institute->id]);
    $year = AcademicYear::create([
        'institute_id' => $institute->id,
        'name' => '2026',
        'year' => 2026,
        'session' => '2026',
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
    $teacher = Teacher::create([
        'institute_id' => $institute->id,
        'employee_id' => 'ATT-TEACHER-1',
        'name' => 'Attendance Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'attendance-teacher@example.test',
        'gender' => 'other',
        'joining_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $students = collect([['ATT-ST-1', 'Amina', '1'], ['ATT-ST-2', 'Babul', '2']])
        ->map(function (array $data) use ($institute, $year, $class, $section) {
            [$studentId, $name, $roll] = $data;
            $student = Student::create([
                'institute_id' => $institute->id,
                'student_id' => $studentId,
                'roll_no' => $roll,
                'name' => $name,
                'father_name' => 'Guardian',
                'mother_name' => 'Guardian',
                'date_of_birth' => '2018-01-01',
                'gender' => 'other',
                'address' => 'Student address',
                'status' => 'active',
            ]);
            StudentAcademicInfo::create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'institute_class_id' => $class->id,
                'section_id' => $section->id,
                'roll_number' => $roll,
            ]);

            return $student;
        });

    return compact('user', 'institute', 'year', 'class', 'section', 'teacher', 'students');
}

test('attendance page provides current institute options and records', function () {
    $fixture = createAttendanceFixture();

    $this->actingAs($fixture['user'])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Attendance/Index')
            ->where('academicYear.session', '2026')
            ->has('classes', 1)
            ->where('classes.0.id', $fixture['class']->id)
            ->has('sections', 1)
            ->has('teachers', 1)
            ->has('attendance.data', 0));
});

test('attendance roster only includes active students enrolled in the selected current class and section', function () {
    $fixture = createAttendanceFixture();
    $inactiveStudent = Student::create([
        'institute_id' => $fixture['institute']->id,
        'student_id' => 'ATT-ST-INACTIVE',
        'roll_no' => '3',
        'name' => 'Inactive Student',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2018-01-01',
        'gender' => 'other',
        'address' => 'Student address',
        'status' => 'inactive',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $inactiveStudent->id,
        'academic_year_id' => $fixture['year']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'roll_number' => '3',
    ]);

    $this->actingAs($fixture['user'])
        ->getJson(route('attendance.roster', [
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'date' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Amina')
        ->assertJsonPath('data.0.status', 'absent');
});

test('attendance saves the complete reviewed roster and updates existing day records', function () {
    $fixture = createAttendanceFixture();
    $payload = [
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'date' => now()->toDateString(),
        'teacher_id' => $fixture['teacher']->id,
        'reviewed_unmatched' => '1',
        'records' => [
            ['student_id' => $fixture['students'][0]->id, 'status' => 'present'],
            ['student_id' => $fixture['students'][1]->id, 'status' => 'absent'],
        ],
    ];

    $this->actingAs($fixture['user'])
        ->post(route('attendance.store'), $payload)
        ->assertRedirect(route('attendance.index'));

    $this->assertDatabaseCount('attendances', 2);
    $this->assertDatabaseHas('attendances', [
        'student_id' => $fixture['students'][0]->id,
        'date' => $payload['date'],
        'status' => 'present',
        'marked_by' => $fixture['teacher']->id,
    ]);

    $payload['records'][0]['status'] = 'absent';
    $this->actingAs($fixture['user'])
        ->post(route('attendance.store'), $payload)
        ->assertRedirect(route('attendance.index'));

    $this->assertDatabaseCount('attendances', 2);
    $this->assertDatabaseHas('attendances', [
        'student_id' => $fixture['students'][0]->id,
        'date' => $payload['date'],
        'status' => 'absent',
    ]);

    $payload['records'][0]['status'] = 'leave';
    $this->actingAs($fixture['user'])
        ->post(route('attendance.store'), $payload)
        ->assertRedirect(route('attendance.index'));

    $this->assertDatabaseHas('attendances', [
        'student_id' => $fixture['students'][0]->id,
        'date' => $payload['date'],
        'status' => 'leave',
    ]);
});

test('attendance report filters by date range and counts present absent and leave days', function () {
    $fixture = createAttendanceFixture();
    $date = now()->toDateString();
    $fixture['students'][0]->update(['phone' => '01700000000']);
    $fixture['students'][1]->update(['phone' => '01800000000']);

    DB::table('attendances')->insert([
        [
            'student_id' => $fixture['students'][0]->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'date' => $date,
            'status' => 'present',
            'marked_by' => $fixture['teacher']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'student_id' => $fixture['students'][0]->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'absent',
            'marked_by' => $fixture['teacher']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'student_id' => $fixture['students'][1]->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'date' => $date,
            'status' => 'leave',
            'marked_by' => $fixture['teacher']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('attendance.report', [
            'from_date' => now()->subDays(2)->toDateString(),
            'to_date' => $date,
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Attendance/Report')
            ->where('filters.from_date', now()->subDays(2)->toDateString())
            ->where('filters.to_date', $date)
            ->where('records.0.name', 'Amina')
            ->where('records.0.phone', '01700000000')
            ->where('records.0.present_days', 1)
            ->where('records.0.absent_days', 1)
            ->where('records.0.absent_dates.0', now()->subDay()->toDateString())
            ->where('records.0.leave_days', 0)
            ->where('records.1.leave_days', 1));
});

test('attendance report rejects an end date earlier than its start date', function () {
    $fixture = createAttendanceFixture();

    $this->actingAs($fixture['user'])
        ->get(route('attendance.report', [
            'from_date' => now()->toDateString(),
            'to_date' => now()->subDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('to_date');
});

test('attendance rejects a teacher from another institute', function () {
    $fixture = createAttendanceFixture();
    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);
    $foreignTeacher = Teacher::create([
        'institute_id' => $otherInstitute->id,
        'employee_id' => 'ATT-FOREIGN-1',
        'name' => 'Foreign Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000001',
        'email' => 'foreign-teacher@example.test',
        'gender' => 'other',
        'joining_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $payload = [
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'date' => now()->toDateString(),
        'teacher_id' => $foreignTeacher->id,
        'reviewed_unmatched' => '1',
        'records' => [
            ['student_id' => $fixture['students'][0]->id, 'status' => 'present'],
            ['student_id' => $fixture['students'][1]->id, 'status' => 'absent'],
        ],
    ];

    $this->actingAs($fixture['user'])
        ->post(route('attendance.store'), $payload)
        ->assertSessionHasErrors('teacher_id');

    $this->assertDatabaseCount('attendances', 0);
});

test('attendance rejects an incomplete roster', function () {
    $fixture = createAttendanceFixture();

    $this->actingAs($fixture['user'])
        ->post(route('attendance.store'), [
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'date' => now()->toDateString(),
            'teacher_id' => $fixture['teacher']->id,
            'reviewed_unmatched' => '1',
            'records' => [
                ['student_id' => $fixture['students'][0]->id, 'status' => 'present'],
            ],
        ])
        ->assertSessionHasErrors('records');

    $this->assertDatabaseCount('attendances', 0);
});

test('attendance roster rejects a class belonging to another institute', function () {
    $fixture = createAttendanceFixture();
    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);
    $foreignClass = InstituteClass::create([
        'institute_id' => $otherInstitute->id,
        'name' => 'Foreign Class',
        'numeric_value' => 1,
    ]);

    $this->actingAs($fixture['user'])
        ->getJson(route('attendance.roster', [
            'class_id' => $foreignClass->id,
            'section_id' => $fixture['section']->id,
            'date' => now()->toDateString(),
        ]))
        ->assertNotFound();
});
