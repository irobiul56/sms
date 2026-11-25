<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\CombinedResult;
use App\Models\Exam;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Student;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('school dashboard displays exams using the institute class relationship', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);

    $academicYear = AcademicYear::create([
        'institute_id' => $institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);

    $schoolClass = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Class 6',
        'numeric_value' => 6,
    ]);

    Exam::create([
        'academic_year_id' => $academicYear->id,
        'institute_class_id' => $schoolClass->id,
        'name' => 'Half-yearly exam',
        'type' => 'half-yearly',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);
    CombinedResult::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $schoolClass->id,
        'academic_year_id' => $academicYear->id,
        'selection_key' => hash('sha256', 'dashboard-combined'),
        'report' => [
            'class_name' => 'Class 6',
            'class_bn_name' => 'ষষ্ঠ শ্রেণি',
            'academic_year' => '2026-2027',
            'section_name' => 'সকল সেকশন',
            'mode' => 'percentage',
            'exams' => [['name' => 'Half-yearly exam', 'bn_name' => null]],
            'rows' => [['incomplete' => false]],
        ],
        'finalized_by' => $user->id,
        'finalized_at' => now(),
    ]);

    InstituteSubject::create([
        'institute_id' => $institute->id,
        'name' => 'Mathematics',
        'code' => 'MATH-6',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);

    $this->actingAs($user)
        ->get(route('institute.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Dashboard')
            ->where('upcomingExams.0.class_name', 'Class 6')
            ->where('recentActivities.0.description', 'Half-yearly exam for Class Class 6')
            ->where('publishedCombinedStats.classes', 1)
            ->where('publishedCombinedStats.results', 1)
            ->where('publishedCombinedResults.0.class_name', 'ষষ্ঠ শ্রেণি')
            ->where('publishedCombinedResults.0.exam_names.0', 'Half-yearly exam')
            ->where('subjectPerformance.0.avg_marks', 0));
});

test('school dashboard and school data lists never include another institutes records', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);
    $schoolClass = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Own Class',
        'numeric_value' => 1,
    ]);

    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);
    InstituteClass::create([
        'institute_id' => $otherInstitute->id,
        'name' => 'Other Class',
        'numeric_value' => 2,
    ]);

    foreach ([
        [$institute, 'OWN-STU', 'Own Student', 'OWN-TEA', 'Own Teacher', 'OWN-SUB', 'Own Subject'],
        [$otherInstitute, 'OTHER-STU', 'Other Student', 'OTHER-TEA', 'Other Teacher', 'OTHER-SUB', 'Other Subject'],
    ] as [$school, $studentId, $studentName, $employeeId, $teacherName, $subjectCode, $subjectName]) {
        Student::create([
            'institute_id' => $school->id,
            'student_id' => $studentId,
            'roll_no' => '1',
            'name' => $studentName,
            'father_name' => 'Guardian',
            'mother_name' => 'Guardian',
            'date_of_birth' => '2018-01-01',
            'gender' => 'other',
            'address' => 'Test address',
            'status' => 'active',
        ]);
        Teacher::create([
            'institute_id' => $school->id,
            'employee_id' => $employeeId,
            'name' => $teacherName,
            'designation' => 'Teacher',
            'phone' => '01700000000',
            'email' => strtolower($employeeId).'@example.test',
            'gender' => 'other',
            'joining_date' => '2026-01-01',
        ]);
        InstituteSubject::create([
            'institute_id' => $school->id,
            'name' => $subjectName,
            'code' => $subjectCode,
            'type' => 'compulsory',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
    }

    $this->actingAs($user)
        ->get(route('institute.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_students', 1)
            ->where('stats.total_teachers', 1)
            ->where('stats.total_classes', 1)
            ->where('stats.total_subjects', 1)
            ->where('subjectPerformance.0.name', 'Own Subject')
            ->where('chartData.subject_performance.labels.0', 'Own Subject')
            ->has('recentActivities', 1)
            ->where('recentActivities.0.description', 'Own Student has been registered.'));

    $this->actingAs($user)
        ->get(route('students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('students', 1)
            ->where('students.0.name', 'Own Student'));

    $this->actingAs($user)
        ->get(route('teachers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teachers', 1)
            ->where('teachers.0.name', 'Own Teacher'));

    $this->actingAs($user)
        ->get(route('classes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('classes', 1)
            ->where('classes.0.id', $schoolClass->id)
            ->where('classes.0.name', 'Own Class'));
});

test('school dashboard formats aggregated attendance dates', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);
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
        'employee_id' => 'DASH-ATT-1',
        'name' => 'Dashboard Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'dashboard-teacher@example.test',
        'gender' => 'other',
        'joining_date' => now()->toDateString(),
    ]);
    $student = Student::create([
        'institute_id' => $institute->id,
        'student_id' => 'DASH-ST-1',
        'roll_no' => '1',
        'name' => 'Dashboard Student',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2018-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    Attendance::unguarded(fn () => Attendance::create([
        'student_id' => $student->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'date' => now()->subDay()->toDateString(),
        'status' => 'present',
        'marked_by' => $teacher->id,
    ]));

    $this->actingAs($user)
        ->get(route('institute.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendanceSummary.weekly_data.0.date', now()->subDay()->format('D'))
            ->where('attendanceSummary.weekly_data.0.present', 1));
});
