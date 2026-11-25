<?php

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Result;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('institute sidebar destinations render their matching pages', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);

    $pages = [
        'institute.dashboard' => 'Institute/Dashboard',
        'students.index' => 'Institute/Students/Index',
        'teachers.index' => 'Institute/Teachers/Index',
        'teacher-permissions.index' => 'Institute/TeacherPermissions/Index',
        'subjects.index' => 'Institute/Subjects/Index',
        'teacher-subjects.index' => 'Institute/Teachers/AssignSubjects',
        'exams.index' => 'Institute/Exams/Index',
        'exam-subjects.index' => 'Institute/ExamSubjects/Index',
        'marks.entry' => 'Institute/Marks/Create',
        'homework.index' => 'Institute/Homework/Index',
        'exam-documents.index' => 'Institute/ExamDocuments/Index',
        'results.index' => 'Institute/Results/Index',
        'results.publish' => 'Institute/Results/Index',
        'grades.index' => 'Institute/Grades/Index',
        'classes.index' => 'Institute/Classes/Index',
        'sections.index' => 'Institute/Sections/Index',
        'academic-years.index' => 'AcademicYears/Index',
        'attendance.index' => 'Institute/Attendance/Index',
        'profile.edit' => 'Profile/Edit',
    ];

    foreach ($pages as $routeName => $component) {
        $this->actingAs($user)
            ->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('publish results filters reject values that are not institute options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('results.publish', [
            'exam_id' => 'invalid',
            'class_id' => 'invalid',
            'section_id' => 'invalid',
            'status' => 'invalid',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Results/Index')
            ->where('filters.exam_id', '')
            ->where('filters.class_id', '')
            ->where('filters.section_id', '')
            ->where('filters.status', 'all'));
});

test('institute navbar can load institute-scoped notifications', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);

    $this->actingAs($user)
        ->getJson(route('institute.dashboard.notifications'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data']);
});

test('institute sidebar statistics use only database records from the current institute', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $user->update(['institute_id' => $institute->id]);

    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);

    foreach ([[$institute, 'OWN'], [$otherInstitute, 'OTHER']] as [$school, $prefix]) {
        $class = InstituteClass::create([
            'institute_id' => $school->id,
            'name' => "{$prefix} Class",
            'numeric_value' => 1,
        ]);
        InstituteSubject::create([
            'institute_id' => $school->id,
            'name' => "{$prefix} Subject",
            'code' => "{$prefix}-SUB",
            'type' => 'compulsory',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        Student::create([
            'institute_id' => $school->id,
            'student_id' => "{$prefix}-STU",
            'roll_no' => '1',
            'name' => "{$prefix} Student",
            'father_name' => 'Guardian',
            'mother_name' => 'Guardian',
            'date_of_birth' => '2018-01-01',
            'gender' => 'other',
            'address' => 'Test address',
        ]);
        Teacher::create([
            'institute_id' => $school->id,
            'employee_id' => "{$prefix}-TEA",
            'name' => "{$prefix} Teacher",
            'designation' => 'Teacher',
            'phone' => '01700000000',
            'email' => strtolower($prefix).'@example.test',
            'gender' => 'other',
            'joining_date' => '2026-01-01',
        ]);
        $academicYear = AcademicYear::create([
            'institute_id' => $school->id,
            'name' => '2026-2027',
            'year' => 2026,
            'session' => '2026-2027',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
        ]);
        $pastExam = Exam::create([
            'academic_year_id' => $academicYear->id,
            'institute_class_id' => $class->id,
            'name' => "{$prefix} Past Exam",
            'type' => 'term',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        if ($school->is($institute)) {
            $section = Section::create([
                'institute_id' => $school->id,
                'institute_class_id' => $class->id,
                'name' => 'A',
            ]);
            $student = Student::where('institute_id', $school->id)->first();
            Result::create([
                'exam_id' => $pastExam->id,
                'student_id' => $student->id,
                'institute_class_id' => $class->id,
                'section_id' => $section->id,
                'total_subjects' => 1,
                'total_marks' => 75,
                'total_point' => 4,
                'gpa' => 4,
                'final_grade' => 'A',
            ]);
            Exam::create([
                'academic_year_id' => $academicYear->id,
                'institute_class_id' => $class->id,
                'name' => 'Upcoming Exam',
                'type' => 'term',
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
            ]);
        }
    }

    $this->actingAs($user)
        ->get(route('students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('instituteSidebarStats.students', 1)
            ->where('instituteSidebarStats.teachers', 1)
            ->where('instituteSidebarStats.subjects', 1)
            ->where('instituteSidebarStats.classes', 1)
            ->where('instituteSidebarStats.upcoming_exams', 1)
            ->where('instituteSidebarStats.pending_marks', 1)
            ->where('instituteSidebarStats.results', 1)
            ->where('instituteSidebarStats.pending_results', 1));
});
