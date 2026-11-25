<?php

use App\Models\AcademicYear;
use App\Models\ClassRoutine;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Homework;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function createStudentPortalFixture(string $status = 'active'): array
{
    $owner = User::factory()->create();
    $institute = Institute::create([
        'user_id' => $owner->id,
        'name' => 'Student Portal School',
    ]);
    $owner->update(['institute_id' => $institute->id]);
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
        'name' => 'Class 6',
        'numeric_value' => 6,
    ]);
    $section = Section::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);
    $student = Student::create([
        'institute_id' => $institute->id,
        'student_id' => 'ST-5452',
        'roll_no' => '1',
        'name' => 'Rakib Hasan',
        'bn_name' => 'রাকিব হাসান',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2014-01-01',
        'gender' => 'male',
        'address' => 'Student address',
        'status' => $status,
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '1',
    ]);
    $user = User::create([
        'name' => $student->name,
        'email' => 'student-' . $student->id . '@accounts.invalid',
        'username' => strtolower($student->student_id),
        'password' => Hash::make('StudentPass123!'),
        'institute_id' => $institute->id,
        'usertype' => 'student',
    ]);
    $student->update(['user_id' => $user->id]);

    return compact('owner', 'institute', 'year', 'class', 'section', 'student', 'user');
}

test('students can sign in with their student ID and see their dashboard', function () {
    $fixture = createStudentPortalFixture();

    $this->withSession(['url.intended' => route('dashboard')])
        ->post(route('login'), [
        'email' => $fixture['student']->student_id,
        'password' => 'StudentPass123!',
    ])->assertRedirect(route('student.dashboard'));

    $this->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Dashboard')
            ->where('student.name', 'রাকিব হাসান')
            ->where('student.class', 'Class 6')
            ->where('student.section', 'A')
            ->where('student.roll', '1'));
});

test('student exam routine marks completed, nearest, and later exams', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    try {
        $fixture = createStudentPortalFixture();
        $subject = InstituteSubject::create([
            'institute_id' => $fixture['institute']->id,
            'name' => 'Mathematics',
            'code' => 'ST-ROUTINE-MATH',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        foreach ([
            ['name' => 'Completed exam', 'date' => '2026-06-14'],
            ['name' => 'Nearest exam', 'date' => '2026-06-17'],
            ['name' => 'Later exam', 'date' => '2026-06-20'],
        ] as $examData) {
            $exam = Exam::create([
                'academic_year_id' => $fixture['year']->id,
                'institute_class_id' => $fixture['class']->id,
                'name' => $examData['name'],
                'type' => 'monthly',
                'start_date' => $examData['date'],
                'end_date' => $examData['date'],
                'is_published' => true,
            ]);

            ExamInstituteSubjec::create([
                'exam_id' => $exam->id,
                'institute_subject_id' => $subject->id,
                'institute_class_id' => $fixture['class']->id,
                'exam_date' => $examData['date'],
                'start_time' => '09:00',
                'end_time' => '12:00:00',
                'full_marks' => 100,
                'pass_marks' => 33,
            ]);
        }

        $this->actingAs($fixture['user'])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('examRoutine', 3)
                ->where('examRoutine.0.subjects.0.status', 'completed')
                ->where('examRoutine.0.subjects.0.is_next', false)
                ->where('examRoutine.1.subjects.0.status', 'upcoming')
                ->where('examRoutine.1.subjects.0.is_next', true)
                ->where('examRoutine.2.subjects.0.status', 'upcoming')
                ->where('examRoutine.2.subjects.0.is_next', false));
    } finally {
        Carbon::setTestNow();
    }
});

test('student dashboard shows the class routine and lets students track assigned homework', function () {
    $fixture = createStudentPortalFixture();
    $subject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Mathematics',
        'code' => 'ST-CLASS-ROUTINE-MATH',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);

    $routine = ClassRoutine::create([
        'institute_id' => $fixture['institute']->id,
        'academic_year_id' => $fixture['year']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'institute_subject_id' => $subject->id,
        'day_of_week' => 6,
        'start_time' => '09:00:00',
        'end_time' => '09:45:00',
        'classroom' => '101',
    ]);
    $homework = Homework::create([
        'institute_id' => $fixture['institute']->id,
        'academic_year_id' => $fixture['year']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'institute_subject_id' => $subject->id,
        'created_by' => $fixture['owner']->id,
        'title' => 'Chapter exercise',
        'description' => 'Complete exercise 1',
        'due_date' => now()->addDay()->toDateString(),
        'is_published' => true,
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('classRoutine.0.id', $routine->id)
            ->where('classRoutine.0.subject', 'Mathematics')
            ->where('homework.0.id', $homework->id)
            ->where('homework.0.is_completed', false));

    $this->post(route('student.homework.toggle', $homework))
        ->assertRedirect();
    $this->assertDatabaseHas('student_homework_completions', [
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
    ]);

    $this->post(route('student.homework.toggle', $homework))
        ->assertRedirect();
    $this->assertDatabaseMissing('student_homework_completions', [
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
    ]);
});

test('institutes can manage class routines without overlapping class periods', function () {
    $fixture = createStudentPortalFixture();
    $subject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Mathematics',
        'code' => 'ST-MANAGED-ROUTINE-MATH',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($subject->id, [
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $payload = [
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'institute_subject_id' => $subject->id,
        'day_of_week' => 6,
        'start_time' => '09:00',
        'end_time' => '09:45',
        'classroom' => '101',
    ];

    $this->actingAs($fixture['owner'])
        ->get(route('class-routines.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Institute/ClassRoutines/Index'));

    $this->post(route('class-routines.store'), $payload)
        ->assertRedirect(route('class-routines.index'));
    $this->assertDatabaseHas('class_routines', [
        'institute_id' => $fixture['institute']->id,
        'institute_class_id' => $fixture['class']->id,
        'institute_subject_id' => $subject->id,
        'start_time' => '09:00',
    ]);

    $this->post(route('class-routines.store'), [
        ...$payload,
        'start_time' => '09:30',
        'end_time' => '10:00',
    ])->assertSessionHasErrors('start_time');
});

test('inactive students cannot sign in', function () {
    $fixture = createStudentPortalFixture('inactive');

    $this->from(route('login'))
        ->post(route('login'), [
            'email' => $fixture['student']->student_id,
            'password' => 'StudentPass123!',
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('student accounts cannot access institute management pages', function () {
    $fixture = createStudentPortalFixture();

    $this->actingAs($fixture['user'])
        ->get(route('institute.dashboard'))
        ->assertForbidden();
});

test('creating a student provisions a student ID login account', function () {
    $owner = User::factory()->create();
    $institute = Institute::create(['user_id' => $owner->id, 'name' => 'Provisioning School']);
    $owner->update(['institute_id' => $institute->id]);
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
        'name' => 'Class 6',
        'numeric_value' => 6,
    ]);
    $section = Section::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);

    $this->actingAs($owner)
        ->post(route('students.store'), [
            'student_id' => 'ST-NEW',
            'name' => 'New Student',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2014-02-02',
            'gender' => 'other',
            'address' => 'Student address',
            'status' => 'active',
            'academic_year_id' => $year->id,
            'institute_class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '2',
            'password' => 'InitialPass123!',
            'password_confirmation' => 'InitialPass123!',
        ])
        ->assertRedirect(route('students.index'));

    $student = Student::query()->where('student_id', 'ST-NEW')->firstOrFail();
    expect($student->user)->not->toBeNull()
        ->and($student->user->username)->toBe('st-new')
        ->and(Hash::check('InitialPass123!', $student->user->password))->toBeTrue();
});
