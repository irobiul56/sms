<?php

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createCombinedResultsFixture(): array
{
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id, 'name' => 'Combined School']);
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
    $subject = InstituteSubject::create([
        'institute_id' => $institute->id,
        'name' => 'Mathematics',
        'code' => 'COM-MATH',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $teacher = Teacher::create([
        'institute_id' => $institute->id,
        'employee_id' => 'COM-TEA',
        'name' => 'Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'combined-teacher@example.test',
        'gender' => 'other',
        'joining_date' => '2026-01-01',
    ]);
    $student = Student::create([
        'institute_id' => $institute->id,
        'student_id' => 'COM-1',
        'roll_no' => '1',
        'name' => 'Student One',
        'bn_name' => 'শিক্ষার্থী',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2018-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '1',
        'group' => 'general',
    ]);

    $exams = collect([
        ['name' => 'Monthly', 'mark' => 80],
        ['name' => 'Half-yearly', 'mark' => 60],
    ])->map(function (array $data) use ($year, $class, $section, $subject, $teacher, $student) {
        $exam = Exam::create([
            'academic_year_id' => $year->id,
            'institute_class_id' => $class->id,
            'name' => $data['name'],
            'type' => 'term',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
        ]);
        ExamInstituteSubjec::create([
            'exam_id' => $exam->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $class->id,
            'exam_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        Mark::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $class->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'total_marks' => $data['mark'],
        ]);

        return $exam;
    });

    return compact('user', 'year', 'class', 'section', 'subject', 'student', 'exams');
}

test('combined results apply percentage weights to selected exams', function () {
    $fixture = createCombinedResultsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('combined-results.index', [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'section_id' => $fixture['section']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'percentage',
            'allocations' => [
                $fixture['exams'][0]->id => 25,
                $fixture['exams'][1]->id => 75,
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/CombinedResults/Index')
            ->where('report.rows.0.subjects.0.obtained', 65)
            ->where('report.rows.0.subjects.0.full_marks', 100)
            ->where('report.rows.0.total_marks', 65)
            ->where('report.rows.0.grade', 'A-')
            ->where('report.rows.0.incomplete', false)
            ->where('report.rows.0.student.father_name', 'Guardian')
            ->where('report.rows.0.student.mother_name', 'Guardian')
            ->where('report.rows.0.merit_rank', 1));
});

test('combined marksheets group paper subjects before converting and grading', function () {
    $fixture = createCombinedResultsFixture();
    $firstPaper = $fixture['subject'];
    $secondPaper = InstituteSubject::create([
        'institute_id' => $fixture['user']->institute_id,
        'name' => 'Hadith II',
        'bn_name' => 'হাদিস দ্বিতীয় পত্র',
        'code' => 'COM-HAD-2',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);

    foreach ([$firstPaper, $secondPaper] as $paper) {
        $fixture['class']->subjects()->syncWithoutDetaching([
            $paper->id => [
                'type' => 'compulsory',
                'full_marks' => 100,
                'pass_marks' => 33,
                'is_group_based' => true,
                'group_name' => 'Hadith',
            ],
        ]);
    }

    $teacher = Teacher::query()->where('employee_id', 'COM-TEA')->firstOrFail();
    foreach ($fixture['exams'] as $index => $exam) {
        ExamInstituteSubjec::create([
            'exam_id' => $exam->id,
            'institute_subject_id' => $secondPaper->id,
            'institute_class_id' => $fixture['class']->id,
            'exam_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        Mark::create([
            'exam_id' => $exam->id,
            'student_id' => $fixture['student']->id,
            'institute_subject_id' => $secondPaper->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'teacher_id' => $teacher->id,
            'total_marks' => 40,
        ]);
    }

    $this->actingAs($fixture['user'])
        ->get(route('combined-results.index', [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'percentage',
            'allocations' => [
                $fixture['exams'][0]->id => 25,
                $fixture['exams'][1]->id => 75,
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.subjects', 1)
            ->where('report.subjects.0.name', 'Hadith')
            ->where('report.rows.0.subjects.0.obtained', 52.5)
            ->where('report.rows.0.subjects.0.full_marks', 100)
            ->where('report.rows.0.subjects.0.grade', 'B')
            ->where('report.rows.0.subjects.0.components.0.original_marks', 120)
            ->where('report.rows.0.total_marks', 52.5));
});

test('combined result merit rank follows the highest complete total', function () {
    $fixture = createCombinedResultsFixture();
    $student = Student::create([
        'institute_id' => $fixture['user']->institute_id,
        'student_id' => 'COM-2',
        'roll_no' => '2',
        'name' => 'Student Two',
        'father_name' => 'Second Father',
        'mother_name' => 'Second Mother',
        'date_of_birth' => '2018-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $fixture['year']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'roll_number' => '2',
        'group' => 'general',
    ]);
    $teacher = Teacher::query()->where('employee_id', 'COM-TEA')->firstOrFail();
    foreach ($fixture['exams'] as $exam) {
        Mark::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'institute_subject_id' => $fixture['subject']->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'teacher_id' => $teacher->id,
            'total_marks' => 90,
        ]);
    }

    $this->actingAs($fixture['user'])
        ->get(route('combined-results.index', [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'percentage',
            'allocations' => [
                $fixture['exams'][0]->id => 50,
                $fixture['exams'][1]->id => 50,
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.merit_rank', 2)
            ->where('report.rows.1.merit_rank', 1)
            ->where('report.rows.1.student.father_name', 'Second Father')
            ->where('report.rows.1.student.mother_name', 'Second Mother'));
});

test('combined results can convert exams to separately configured full marks', function () {
    $fixture = createCombinedResultsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('combined-results.index', [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'conversion',
            'allocations' => [
                $fixture['exams'][0]->id => 20,
                $fixture['exams'][1]->id => 30,
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.subjects.0.obtained', 34)
            ->where('report.rows.0.subjects.0.full_marks', 50)
            ->where('report.rows.0.total_full_marks', 50)
            ->where('report.exams.0.allocation', 20));
});

test('combined result form submits and redirects to its calculated report', function () {
    $fixture = createCombinedResultsFixture();

    $this->actingAs($fixture['user'])
        ->followingRedirects()
        ->post(route('combined-results.generate'), [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'percentage',
            'allocations' => [
                $fixture['exams'][0]->id => 25,
                $fixture['exams'][1]->id => 75,
            ],
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/CombinedResults/Index')
            ->where('report.rows.0.subjects.0.obtained', 65));
});

test('finalized combined results are visible only as the signed-in student result', function () {
    $fixture = createCombinedResultsFixture();
    $studentUser = User::factory()->create([
        'username' => strtolower($fixture['student']->student_id),
        'usertype' => 'student',
        'institute_id' => $fixture['user']->institute_id,
    ]);
    $fixture['student']->update(['user_id' => $studentUser->id]);
    $selection = [
        'class_id' => $fixture['class']->id,
        'academic_year_id' => $fixture['year']->id,
        'section_id' => $fixture['section']->id,
        'exam_ids' => $fixture['exams']->pluck('id')->all(),
        'mode' => 'percentage',
        'allocations' => [
            $fixture['exams'][0]->id => 25,
            $fixture['exams'][1]->id => 75,
        ],
    ];

    $this->actingAs($fixture['user'])
        ->post(route('combined-results.finalize'), $selection)
        ->assertRedirect();

    expect(\App\Models\CombinedResult::query()->count())->toBe(1);

    $this->actingAs($studentUser)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Dashboard')
            ->has('combinedResults', 1)
            ->where('combinedResults.0.total_marks', 65)
            ->where('combinedResults.0.position', 1)
            ->where('combinedResults.0.mode', 'percentage')
            ->where('combinedResults.0.exams.0.allocation', 25)
            ->where('combinedResults.0.marks.0.score', 65)
            ->where('combinedResults.0.marks.0.components.0.allocation', 25)
            ->where('combinedResults.0.marks.0.components.0.original_marks', 80)
            ->missing('combinedResults.0.student'));
});

test('combined results require percentage weights to total one hundred', function () {
    $fixture = createCombinedResultsFixture();

    $this->from(route('combined-results.index'))
        ->actingAs($fixture['user'])
        ->post(route('combined-results.generate'), [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'percentage',
            'allocations' => [
                $fixture['exams'][0]->id => 20,
                $fixture['exams'][1]->id => 30,
            ],
        ])
        ->assertRedirect(route('combined-results.index'))
        ->assertSessionHasErrors('allocations');
});

test('combined results identify missing marks as incomplete instead of treating them as zero', function () {
    $fixture = createCombinedResultsFixture();
    Mark::query()
        ->where('exam_id', $fixture['exams'][1]->id)
        ->delete();

    $this->actingAs($fixture['user'])
        ->get(route('combined-results.index', [
            'class_id' => $fixture['class']->id,
            'academic_year_id' => $fixture['year']->id,
            'exam_ids' => $fixture['exams']->pluck('id')->all(),
            'mode' => 'percentage',
            'allocations' => [
                $fixture['exams'][0]->id => 50,
                $fixture['exams'][1]->id => 50,
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.subjects.0.obtained', 40)
            ->where('report.rows.0.subjects.0.incomplete', true)
            ->where('report.rows.0.grade', 'অসম্পূর্ণ')
            ->where('report.rows.0.merit_rank', null));
});
