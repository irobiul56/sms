<?php

use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createStudentPrintFixture(): array
{
    $user = User::factory()->create();
    $institute = Institute::create([
        'user_id' => $user->id,
        'name' => 'Student Print School',
        'phone' => '01700000000',
        'address' => 'Print Street',
        'logo' => 'institute-logos/student-print-school.png',
    ]);
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

    foreach ([['ST-1', 'Alice', '1'], ['ST-2', 'Bob', '2']] as [$studentId, $name, $roll]) {
        $student = Student::create([
            'institute_id' => $institute->id,
            'student_id' => $studentId,
            'roll_no' => $roll,
            'name' => $name,
            'father_name' => 'Guardian',
            'mother_name' => 'Guardian',
            'date_of_birth' => '2018-01-01',
            'gender' => $name === 'Alice' ? 'female' : 'male',
            'address' => 'Student address',
            'status' => 'active',
        ]);
        StudentAcademicInfo::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'institute_class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => $roll,
            'group' => 'general',
        ]);
    }

    return compact('user', 'institute', 'class', 'section');
}

test('student print page uses the selected filters and institute student data', function () {
    $fixture = createStudentPrintFixture();

    $this->actingAs($fixture['user'])
        ->get(route('students.print', [
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'search' => 'alice',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Students/Print')
            ->where('institute.name', 'Student Print School')
            ->where('institute.logo_url', asset('storage/institute-logos/student-print-school.png'))
            ->where('filters.class_id', (string) $fixture['class']->id)
            ->where('filters.class_name', 'Class 1')
            ->where('filters.section_name', 'A')
            ->has('classes', 1)
            ->where('classes.0.id', $fixture['class']->id)
            ->has('students', 1)
            ->where('students.0.name', 'Alice')
            ->where('students.0.roll_no', '1'));
});

test('student print rejects a class belonging to another institute', function () {
    $fixture = createStudentPrintFixture();
    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create([
        'user_id' => $otherUser->id,
        'name' => 'Another School',
    ]);
    $foreignClass = InstituteClass::create([
        'institute_id' => $otherInstitute->id,
        'name' => 'Foreign Class',
        'numeric_value' => 1,
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('students.print', ['class_id' => $foreignClass->id]))
        ->assertNotFound();
});
