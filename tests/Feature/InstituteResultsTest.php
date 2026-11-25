<?php

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
use App\Models\Grade;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Mark;
use App\Models\Result;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createInstituteResultsFixture(bool $unverified = false): array
{
    $user = User::factory()->when($unverified, fn ($factory) => $factory->unverified())->create();
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
    $student = Student::create([
        'institute_id' => $institute->id,
        'student_id' => 'STU-1',
        'roll_no' => '1',
        'name' => 'Test Student',
        'father_name' => 'Test Guardian',
        'mother_name' => 'Test Guardian',
        'date_of_birth' => '2019-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $academicYear->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '1',
        'group' => 'general',
    ]);
    $teacher = Teacher::create([
        'institute_id' => $institute->id,
        'employee_id' => 'TEA-1',
        'name' => 'Test Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'teacher@example.test',
        'gender' => 'other',
        'joining_date' => '2026-01-01',
    ]);
    $exam = Exam::create([
        'academic_year_id' => $academicYear->id,
        'institute_class_id' => $class->id,
        'name' => 'First Term',
        'type' => 'term',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);
    $subjects = collect(['Bangla', 'English'])->map(function ($name, $index) use ($institute, $exam, $class) {
        $subject = InstituteSubject::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => 'SUB-'.$index,
            'type' => 'compulsory',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        $class->subjects()->attach($subject->id, [
            'type' => 'compulsory',
            'full_marks' => 100,
            'pass_marks' => 33,
            'is_group_based' => false,
        ]);
        ExamInstituteSubjec::create([
            'exam_id' => $exam->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $class->id,
            'exam_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        return $subject;
    });

    foreach ([85, 72] as $index => $score) {
        Mark::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'institute_subject_id' => $subjects[$index]->id,
            'institute_class_id' => $class->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'total_marks' => $score,
            'grade_point' => $score >= 80 ? 5 : 4,
            'grade' => $score >= 80 ? 'A+' : 'A',
        ]);
    }

    Grade::create([
        'institute_id' => $institute->id,
        'name' => 'A+',
        'point' => 5,
        'marks_from' => 80,
        'marks_to' => 100,
    ]);
    Grade::create([
        'institute_id' => $institute->id,
        'name' => 'A',
        'point' => 4,
        'marks_from' => 70,
        'marks_to' => 79.99,
    ]);

    return compact('user', 'institute', 'academicYear', 'class', 'section', 'student', 'exam', 'subjects');
}

test('unverified school admins can view results and publish results pages', function () {
    $fixture = createInstituteResultsFixture(unverified: true);

    $this->actingAs($fixture['user'])
        ->get(route('results.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Results/Index')
            ->where('exams.0.academic_year', $fixture['academicYear']->session));

    $this->actingAs($fixture['user'])
        ->get(route('results.publish'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Results/Index')
            ->where('filters.status', 'unpublished'));
});

test('results can be generated from complete exam marks and published', function () {
    $fixture = createInstituteResultsFixture();

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect($result->total_subjects)->toBe(2)
        ->and((float) $result->total_marks)->toBe(157.0)
        ->and((float) $result->gpa)->toBe(4.5)
        ->and($result->final_grade)->toBe('A')
        ->and($result->is_published)->toBeFalse();

    $this->actingAs($fixture['user'])
        ->get(route('results.publish'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('results.0.id', $result->id)
            ->where('results.0.is_published', false));

    $this->actingAs($fixture['user'])
        ->post(route('results.toggle-publish', $result))
        ->assertRedirect();

    $this->assertDatabaseHas('results', [
        'id' => $result->id,
        'is_published' => true,
    ]);
});

test('combined paper groups receive one grade based on the summed marks', function () {
    $fixture = createInstituteResultsFixture();
    $firstPaper = $fixture['subjects'][0];
    $secondPaper = $fixture['subjects'][1];

    foreach ([$firstPaper, $secondPaper] as $paper) {
        $fixture['class']->subjects()->updateExistingPivot($paper->id, [
            'is_group_based' => true,
            'group_name' => 'Religious Studies',
        ]);
        ExamInstituteSubjec::query()
            ->where('exam_id', $fixture['exam']->id)
            ->where('institute_subject_id', $paper->id)
            ->update(['full_marks' => 50, 'pass_marks' => 17]);
    }

    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $firstPaper->id)
        ->update(['total_marks' => 20]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $secondPaper->id)
        ->update(['total_marks' => 50]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect($result->total_subjects)->toBe(1)
        ->and((float) $result->total_marks)->toBe(70.0)
        ->and((float) $result->total_full_marks)->toBe(100.0)
        ->and((float) $result->gpa)->toBe(4.0);

    $this->actingAs($fixture['user'])
        ->get(route('results.index', [
            'exam_id' => $fixture['exam']->id,
            'subject_id' => $firstPaper->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subjects', 1)
            ->where('subjects.0.name', 'Religious Studies')
            ->where('tabulationRows.0.mark.total_marks', '70.00')
            ->where('tabulationRows.0.mark.grade', 'A'));
});

test('optional bonus and additional subject follow board-standard gpa calculation', function () {
    $fixture = createInstituteResultsFixture();
    $optional = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Optional Subject',
        'code' => 'OPT-1',
        'type' => 'optional',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $additional = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Additional Subject',
        'code' => 'ADD-1',
        'type' => 'additional',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);

    foreach ([$optional, $additional] as $subject) {
        $type = $subject->type;
        $fixture['class']->subjects()->attach($subject->id, [
            'type' => $type,
            'full_marks' => 100,
            'pass_marks' => 33,
            'is_group_based' => false,
        ]);
        ExamInstituteSubjec::create([
            'exam_id' => $fixture['exam']->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $fixture['class']->id,
            'exam_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        Mark::create([
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'teacher_id' => Teacher::query()->where('institute_id', $fixture['institute']->id)->value('id'),
            'total_marks' => 90,
            'grade_point' => 5,
            'grade' => 'A+',
        ]);
    }
    StudentAcademicInfo::query()
        ->where('student_id', $fixture['student']->id)
        ->update([
            'optional_subject_id' => $optional->id,
            'additional_subject_id' => $additional->id,
        ]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect($result->total_subjects)->toBe(4)
        ->and((float) $result->total_point)->toBe(17.0)
        ->and((float) $result->gpa)->toBe(5.0)
        ->and($result->final_grade)->toBe('A+');
});

test('failing optional subject does not fail the overall result', function () {
    $fixture = createInstituteResultsFixture();
    $optional = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Optional Subject',
        'code' => 'OPT-FAIL',
        'type' => 'optional',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($optional->id, [
        'type' => 'optional',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    ExamInstituteSubjec::create([
        'exam_id' => $fixture['exam']->id,
        'institute_subject_id' => $optional->id,
        'institute_class_id' => $fixture['class']->id,
        'exam_date' => '2026-06-01',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    StudentAcademicInfo::query()
        ->where('student_id', $fixture['student']->id)
        ->update(['optional_subject_id' => $optional->id]);
    Mark::create([
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['student']->id,
        'institute_subject_id' => $optional->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => Teacher::query()->where('institute_id', $fixture['institute']->id)->value('id'),
        'total_marks' => 20,
        'grade_point' => 0,
        'grade' => 'F',
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect($result->total_subjects)->toBe(3)
        ->and((float) $result->gpa)->toBe(4.5)
        ->and($result->final_grade)->toBe('A');
});

test('mark entry loads students for a secondary class assigned to a multi-class exam', function () {
    $fixture = createInstituteResultsFixture();
    $class = InstituteClass::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 2',
        'numeric_value' => 2,
    ]);
    $section = Section::create([
        'institute_id' => $fixture['institute']->id,
        'institute_class_id' => $class->id,
        'name' => 'B',
    ]);
    $student = Student::create([
        'institute_id' => $fixture['institute']->id,
        'student_id' => 'STU-2',
        'roll_no' => '2',
        'name' => 'Secondary Student',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2019-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $fixture['academicYear']->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '2',
        'group' => 'general',
    ]);
    $class->subjects()->attach($fixture['subjects']->pluck('id')->all(), [
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    foreach ($fixture['subjects'] as $subject) {
        ExamInstituteSubjec::create([
            'exam_id' => $fixture['exam']->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $class->id,
            'exam_date' => '2026-06-02',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
    }
    $fixture['exam']->classes()->sync([$fixture['class']->id, $class->id]);

    $this->actingAs($fixture['user'])
        ->getJson(route('marks.get-students', [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'exam_id' => $fixture['exam']->id,
            'subject_id' => $fixture['subjects'][0]->id,
        ]))
        ->assertOk()
        ->assertJsonPath('students.0.name', $student->name);

    $this->actingAs($fixture['user'])
        ->get(route('marks.entry'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('exams.0.class_ids', [$fixture['class']->id, $class->id]));
});

test('exams can be created and updated with multiple classes', function () {
    $fixture = createInstituteResultsFixture();
    $secondClass = InstituteClass::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 2',
        'numeric_value' => 2,
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('exams.store'), [
            'academic_year_id' => $fixture['academicYear']->id,
            'class_ids' => [$fixture['class']->id, $secondClass->id],
            'name' => 'Shared Exam',
            'type' => 'term',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
        ])
        ->assertRedirect(route('exams.index'));

    $exam = Exam::query()->where('name', 'Shared Exam')->firstOrFail();
    $this->assertEqualsCanonicalizing(
        [$fixture['class']->id, $secondClass->id],
        $exam->classIds()
    );

    $this->actingAs($fixture['user'])
        ->put(route('exams.update', $exam), [
            'academic_year_id' => $fixture['academicYear']->id,
            'class_ids' => [$secondClass->id],
            'name' => 'Shared Exam',
            'type' => 'term',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
        ])
        ->assertRedirect(route('exams.index'));

    $this->assertSame([$secondClass->id], $exam->fresh()->classIds());

    $this->actingAs($fixture['user'])
        ->from(route('exams.index'))
        ->post(route('exams.store'), [
            'academic_year_id' => $fixture['academicYear']->id,
            'class_ids' => [$secondClass->id],
            'name' => 'Shared Exam',
            'type' => 'term',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
        ])
        ->assertRedirect(route('exams.index'))
        ->assertSessionHas('error');
});

test('result generation processes students in every class assigned to an exam', function () {
    $fixture = createInstituteResultsFixture();
    $class = InstituteClass::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 2',
        'numeric_value' => 2,
    ]);
    $section = Section::create([
        'institute_id' => $fixture['institute']->id,
        'institute_class_id' => $class->id,
        'name' => 'B',
    ]);
    $student = Student::create([
        'institute_id' => $fixture['institute']->id,
        'student_id' => 'STU-2',
        'roll_no' => '2',
        'name' => 'Secondary Student',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2019-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $fixture['academicYear']->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '2',
        'group' => 'general',
    ]);
    $class->subjects()->attach($fixture['subjects']->pluck('id')->all(), [
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    foreach ($fixture['subjects'] as $subject) {
        ExamInstituteSubjec::create([
            'exam_id' => $fixture['exam']->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $class->id,
            'exam_date' => '2026-06-02',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
    }
    $fixture['exam']->classes()->sync([$fixture['class']->id, $class->id]);
    $teacherId = Teacher::query()->where('institute_id', $fixture['institute']->id)->value('id');

    foreach ($fixture['subjects'] as $index => $subject) {
        Mark::create([
            'exam_id' => $fixture['exam']->id,
            'student_id' => $student->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $class->id,
            'section_id' => $section->id,
            'teacher_id' => $teacherId,
            'total_marks' => 70 + $index,
            'grade_point' => 4,
            'grade' => 'A',
        ]);
    }

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $this->assertDatabaseHas('results', [
        'exam_id' => $fixture['exam']->id,
        'student_id' => $student->id,
        'institute_class_id' => $class->id,
    ]);
});

test('theory and mcq marks must each reach 33 percent to pass the subject', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update([
        'theory_marks' => 60,
        'mcq_marks' => 40,
    ]);

    $mark = Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->firstOrFail();
    $mark->update([
        'theory_marks' => 19,
        'mcq_marks' => 40,
        'total_marks' => 59,
        'grade' => 'D',
        'grade_point' => 1,
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $mark->refresh();
    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect($mark->grade)->toBe('F')
        ->and((float) $mark->grade_point)->toBe(0.0)
        ->and($result->final_grade)->toBe('F')
        ->and((float) $result->gpa)->toBe(0.0);
});

test('mark entry saves F when theory is below 33 percent even if mcq passes', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update([
        'theory_marks' => 60,
        'mcq_marks' => 40,
    ]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $subject->id)
        ->delete();

    $teacher = Teacher::query()->where('institute_id', $fixture['institute']->id)->firstOrFail();
    $this->actingAs($fixture['user'])
        ->post(route('marks.store'), [
            'exam_id' => $fixture['exam']->id,
            'student_id' => $fixture['student']->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'teacher_id' => $teacher->id,
            'theory_marks' => 19,
            'mcq_marks' => 40,
        ])
        ->assertRedirect(route('marks.entry'));

    $mark = Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->firstOrFail();
    expect($mark->grade)->toBe('F')
        ->and((float) $mark->grade_point)->toBe(0.0);
});

test('monthly exams ignore larger subject component maxima beyond the exam full marks', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update([
        'theory_marks' => 10,
        'theory_pass_marks' => 5,
        'mcq_marks' => 20,
        'mcq_pass_marks' => 7,
    ]);

    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->update([
            'full_marks' => 10,
            'pass_marks' => 5,
        ]);

    $service = app(App\Services\MarkGradingService::class);

    expect($service->failsMarkComponents($subject, [
        'theory_marks' => 10,
        'mcq_marks' => 0,
        'practical_marks' => 0,
    ], 10))->toBeFalse()
        ->and($service->gradeForExam(10, 10, 5, $service->gradeRangesForInstitute($fixture['institute']->id), false)['grade'])->toBe('A+');
});

test('mark entry uses exam-specific full and pass marks for component limits and grades', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->update(['full_marks' => 50, 'pass_marks' => 17]);
    $teacher = Teacher::query()->where('institute_id', $fixture['institute']->id)->firstOrFail();
    $payload = [
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['student']->id,
        'institute_subject_id' => $subject->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => $teacher->id,
    ];

    $this->actingAs($fixture['user'])
        ->getJson(route('marks.get-students', [
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'exam_id' => $fixture['exam']->id,
            'subject_id' => $subject->id,
        ]))
        ->assertOk()
        ->assertJsonPath('full_marks', 50)
        ->assertJsonPath('pass_marks', 17);

    $this->actingAs($fixture['user'])
        ->postJson(route('marks.store'), $payload + ['theory_marks' => 40])
        ->assertOk()
        ->assertJsonPath('mark.grade', 'A+')
        ->assertJsonPath('mark.grade_point', '5.00');

    $this->actingAs($fixture['user'])
        ->postJson(route('marks.store'), $payload + ['theory_marks' => 15])
        ->assertOk()
        ->assertJsonPath('mark.grade', 'F')
        ->assertJsonPath('mark.grade_point', '0.00');

    $this->actingAs($fixture['user'])
        ->postJson(route('marks.store'), $payload + ['theory_marks' => 51])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('theory_marks');
});

test('generated results grade against each exam subject and show the total maximum', function () {
    $fixture = createInstituteResultsFixture();
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][0]->id)
        ->update(['full_marks' => 50, 'pass_marks' => 17]);
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->update(['full_marks' => 150, 'pass_marks' => 50]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $fixture['subjects'][0]->id)
        ->update(['total_marks' => 40]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->update(['total_marks' => 105]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect((float) $result->total_marks)->toBe(145.0)
        ->and((float) $result->total_full_marks)->toBe(200.0)
        ->and((float) $result->gpa)->toBe(4.5)
        ->and($result->final_grade)->toBe('A');

    expect(Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][0]->id)
        ->value('grade'))->toBe('A+')
        ->and(Mark::query()
            ->where('exam_id', $fixture['exam']->id)
            ->where('institute_subject_id', $fixture['subjects'][1]->id)
            ->value('grade'))->toBe('A');

    $result->update(['is_published' => true]);
    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.marksheet', [$fixture['exam']->id, $fixture['student']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.total_marks', '145.00')
            ->where('result.total_full_marks', '200.00')
            ->where('subjects.0.full_marks', 50)
            ->where('subjects.0.pass_marks', 17));
});

test('result generation fails a subject below its exam-specific pass mark', function () {
    $fixture = createInstituteResultsFixture();
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][0]->id)
        ->update(['full_marks' => 50, 'pass_marks' => 17]);
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->update(['full_marks' => 150, 'pass_marks' => 65]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $fixture['subjects'][0]->id)
        ->update(['total_marks' => 40]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->update(['total_marks' => 60]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    expect(Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->value('grade'))->toBe('F');
    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect((float) $result->gpa)->toBe(0.0)
        ->and($result->final_grade)->toBe('F');
});

test('mark entry saves and updates one student mark through json without redirecting', function () {
    $fixture = createInstituteResultsFixture();
    $teacher = Teacher::query()->where('institute_id', $fixture['institute']->id)->firstOrFail();
    $payload = [
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['student']->id,
        'institute_subject_id' => $fixture['subjects'][0]->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => $teacher->id,
        'theory_marks' => 80,
    ];

    $this->actingAs($fixture['user'])
        ->postJson(route('marks.store'), $payload)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('mark.total_marks', '80.00');

    $payload['theory_marks'] = 90;
    $this->actingAs($fixture['user'])
        ->postJson(route('marks.store'), $payload)
        ->assertOk()
        ->assertJsonPath('mark.total_marks', '90.00');

    $this->assertDatabaseCount('marks', 2);
    $this->assertDatabaseHas('marks', [
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['student']->id,
        'institute_subject_id' => $fixture['subjects'][0]->id,
        'total_marks' => 90,
    ]);
});

test('exactly 33 percent in both theory and mcq meets the component pass thresholds', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update([
        'theory_marks' => 60,
        'mcq_marks' => 40,
    ]);

    $grading = app(\App\Services\MarkGradingService::class);
    expect($grading->failsTheoryOrMcq($subject, 19.8, 13.2))->toBeFalse()
        ->and($grading->failsTheoryOrMcq($subject, 19.79, 13.2))->toBeTrue()
        ->and($grading->failsTheoryOrMcq($subject, 19.8, 13.19))->toBeTrue();
});

test('subject stores component pass marks and mark entry loads them', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $this->actingAs($fixture['user'])
        ->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'bn_name' => $subject->bn_name,
            'code' => $subject->code,
            'type' => $subject->type,
            'full_marks' => 120,
            'pass_marks' => $subject->pass_marks,
            'theory_marks' => 60,
            'theory_pass_marks' => 25,
            'mcq_marks' => 40,
            'mcq_pass_marks' => 10,
            'practical_marks' => 20,
            'practical_pass_marks' => 8,
            'class_ids' => [$fixture['class']->id],
        ])
        ->assertRedirect(route('subjects.index'));
    $subject->refresh();
    expect((float) $subject->theory_pass_marks)->toBe(25.0)
        ->and((float) $subject->mcq_pass_marks)->toBe(10.0)
        ->and((float) $subject->practical_pass_marks)->toBe(8.0)
        ->and($subject->classes()->pluck('institute_classes.id')->all())->toContain($fixture['class']->id)
        ->and(ExamInstituteSubjec::query()
            ->where('exam_id', $fixture['exam']->id)
            ->where('institute_subject_id', $subject->id)
            ->exists())->toBeTrue();

    $this->actingAs($fixture['user'])
        ->get(route('subjects.edit', $subject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Subjects/Edit')
            ->where('subject.theory_pass_marks', '25.00')
            ->where('subject.mcq_pass_marks', '10.00')
            ->where('subject.practical_pass_marks', '8.00'));

    $this->actingAs($fixture['user'])
        ->get(route('marks.entry'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subjects.0.class_ids', [$fixture['class']->id])
            ->where('exams.0.class_id', $fixture['class']->id)
            ->where('exams.0.subject_ids', $fixture['subjects']->pluck('id')->all()));

    $this->actingAs($fixture['user'])
        ->getJson(route('marks.get-students', [
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'exam_id' => $fixture['exam']->id,
            'subject_id' => $subject->id,
        ]))
        ->assertOk()
        ->assertJsonPath('students.0.name', $fixture['student']->name)
        ->assertJsonPath('students.0.roll_no', '1')
        ->assertJsonPath('component_pass_marks.theory_pass_marks', '25.00')
        ->assertJsonPath('component_pass_marks.mcq_pass_marks', '10.00')
        ->assertJsonPath('component_pass_marks.practical_pass_marks', '8.00');
});

test('subjects can be assigned and edited as combined paper groups per class', function () {
    $fixture = createInstituteResultsFixture();
    $classId = $fixture['class']->id;

    $this->actingAs($fixture['user'])
        ->post(route('subjects.store'), [
            'name' => 'Bangla II',
            'bn_name' => 'বাংলা দ্বিতীয় পত্র',
            'code' => 'BAN-2',
            'type' => 'compulsory',
            'full_marks' => 100,
            'pass_marks' => 33,
            'class_ids' => [$classId],
            'group_names' => [$classId => 'Bangla'],
        ])
        ->assertRedirect(route('subjects.index'));

    $subject = InstituteSubject::query()->where('code', 'BAN-2')->firstOrFail();
    $pivot = $subject->classes()->firstOrFail()->pivot;
    expect((bool) $pivot->is_group_based)->toBeTrue()
        ->and($pivot->group_name)->toBe('Bangla');

    $this->actingAs($fixture['user'])
        ->get(route('subjects.edit', $subject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Subjects/Edit')
            ->where("subject.group_names.{$classId}", 'Bangla'));

    $this->actingAs($fixture['user'])
        ->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'bn_name' => $subject->bn_name,
            'code' => $subject->code,
            'type' => $subject->type,
            'full_marks' => $subject->full_marks,
            'pass_marks' => $subject->pass_marks,
            'class_ids' => [$classId],
            'group_names' => [$classId => ''],
        ])
        ->assertRedirect(route('subjects.index'));

    $pivot = $subject->classes()->firstOrFail()->pivot;
    expect((bool) $pivot->is_group_based)->toBeFalse()
        ->and($pivot->group_name)->toBeNull();
});

test('paper groups can be created renamed and separated from the subjects page', function () {
    $fixture = createInstituteResultsFixture();
    $subjectIds = collect($fixture['subjects'])->pluck('id')->all();
    $classId = $fixture['class']->id;

    $this->actingAs($fixture['user'])
        ->post(route('subjects.combine-papers'), [
            'class_id' => $classId,
            'subject_ids' => $subjectIds,
            'group_name' => 'Religious Studies',
        ])
        ->assertRedirect(route('subjects.index'));

    foreach ($subjectIds as $subjectId) {
        $this->assertDatabaseHas('institute_class_institute_subjects', [
            'institute_class_id' => $classId,
            'institute_subject_id' => $subjectId,
            'is_group_based' => true,
            'group_name' => 'Religious Studies',
        ]);
    }

    $this->actingAs($fixture['user'])
        ->get(route('subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subjects.0.classes.0.pivot.group_name', 'Religious Studies'));

    $this->actingAs($fixture['user'])
        ->post(route('subjects.combine-papers'), [
            'class_id' => $classId,
            'subject_ids' => $subjectIds,
            'old_group_name' => 'Religious Studies',
            'group_name' => 'Quran and Hadith',
        ])
        ->assertRedirect(route('subjects.index'));

    foreach ($subjectIds as $subjectId) {
        $this->assertDatabaseHas('institute_class_institute_subjects', [
            'institute_class_id' => $classId,
            'institute_subject_id' => $subjectId,
            'is_group_based' => true,
            'group_name' => 'Quran and Hadith',
        ]);
    }

    $this->actingAs($fixture['user'])
        ->post(route('subjects.combine-papers'), [
            'class_id' => $classId,
            'subject_ids' => $subjectIds,
            'old_group_name' => 'Quran and Hadith',
            'group_name' => '',
        ])
        ->assertRedirect(route('subjects.index'));

    foreach ($subjectIds as $subjectId) {
        $this->assertDatabaseHas('institute_class_institute_subjects', [
            'institute_class_id' => $classId,
            'institute_subject_id' => $subjectId,
            'is_group_based' => false,
            'group_name' => null,
        ]);
    }
});

test('paper groups require at least two subjects', function () {
    $fixture = createInstituteResultsFixture();

    $this->actingAs($fixture['user'])
        ->from(route('subjects.index'))
        ->post(route('subjects.combine-papers'), [
            'class_id' => $fixture['class']->id,
            'subject_ids' => [$fixture['subjects'][0]->id],
            'group_name' => 'One paper',
        ])
        ->assertRedirect(route('subjects.index'))
        ->assertSessionHasErrors('subject_ids');
});

test('mark entry applies the practical pass mark configured on its subject', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update([
        'theory_marks' => 60,
        'mcq_marks' => 40,
        'practical_marks' => 20,
        'full_marks' => 120,
        'theory_pass_marks' => 25,
        'mcq_pass_marks' => 10,
        'practical_pass_marks' => 8,
    ]);
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->update(['full_marks' => 120]);
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['student']->id)
        ->where('institute_subject_id', $subject->id)
        ->delete();

    $teacher = Teacher::query()->where('institute_id', $fixture['institute']->id)->firstOrFail();
    $markData = [
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['student']->id,
        'institute_subject_id' => $subject->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => $teacher->id,
        'theory_marks' => 25,
        'mcq_marks' => 10,
        'practical_marks' => 7,
    ];
    $this->actingAs($fixture['user'])
        ->post(route('marks.store'), $markData)
        ->assertRedirect(route('marks.entry'));

    $mark = Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->firstOrFail();
    expect($mark->grade)->toBe('F');

    $markData['practical_marks'] = 8;
    $this->actingAs($fixture['user'])
        ->put(route('marks.update', $mark), $markData)
        ->assertRedirect(route('marks.index'));
    $mark->refresh();
    expect($mark->grade)->toBe('D');
});

test('result regeneration recalculates a previously failed mark after pass marks are lowered', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update(['theory_marks' => 60, 'mcq_marks' => 40]);
    $mark = Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $subject->id)
        ->firstOrFail();
    $mark->update([
        'theory_marks' => 30,
        'mcq_marks' => 40,
        'total_marks' => 70,
        'grade' => 'F',
        'grade_point' => 0,
    ]);
    $subject->update(['theory_pass_marks' => 31]);

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();
    expect($mark->fresh()->grade)->toBe('F');

    $subject->update(['theory_pass_marks' => 30]);
    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()->where('exam_id', $fixture['exam']->id)->firstOrFail();
    expect($mark->fresh()->grade)->toBe('A')
        ->and((float) $mark->fresh()->grade_point)->toBe(4.0)
        ->and($result->final_grade)->toBe('A');
});

test('subject component pass marks cannot exceed configured component full marks', function () {
    $fixture = createInstituteResultsFixture();
    $subject = $fixture['subjects'][0];
    $subject->update(['theory_marks' => 60, 'mcq_marks' => 40]);

    $this->actingAs($fixture['user'])
        ->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'bn_name' => $subject->bn_name,
            'code' => $subject->code,
            'type' => $subject->type,
            'full_marks' => $subject->full_marks,
            'pass_marks' => $subject->pass_marks,
            'theory_marks' => 60,
            'theory_pass_marks' => 61,
            'mcq_marks' => 40,
            'mcq_pass_marks' => 10,
            'practical_marks' => null,
            'practical_pass_marks' => null,
            'class_ids' => [],
        ])
        ->assertSessionHasErrors('theory_pass_marks');
});

test('a single-subject tabulation sheet shows its marks and the full exam subject list', function () {
    $fixture = createInstituteResultsFixture();

    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->delete();

    $this->actingAs($fixture['user'])
        ->get(route('results.index', [
            'exam_id' => $fixture['exam']->id,
            'subject_id' => $fixture['subjects'][0]->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/Results/Index')
            ->has('subjects', 2)
            ->has('tabulationRows', 1)
            ->where('tabulationRows.0.student.name', 'Test Student')
            ->where('tabulationRows.0.mark.total_marks', '85.00'));
});

test('results are not generated while any exam subject mark is missing', function () {
    $fixture = createInstituteResultsFixture();
    Mark::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subjects'][1]->id)
        ->delete();

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertSessionHasErrors('exam');

    $this->assertDatabaseCount('results', 0);
});

test('school admins cannot publish another institutes result', function () {
    $fixture = createInstituteResultsFixture();
    Result::create([
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['student']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'total_subjects' => 2,
        'total_marks' => 157,
        'total_point' => 9,
        'gpa' => 4.5,
        'final_grade' => 'A',
        'total_absent' => 0,
    ]);

    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);
    $otherUser->update(['institute_id' => $otherInstitute->id]);
    $otherClass = InstituteClass::create([
        'institute_id' => $otherInstitute->id,
        'name' => 'Class 1',
        'numeric_value' => 1,
    ]);
    $otherAcademicYear = AcademicYear::create([
        'institute_id' => $otherInstitute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $otherExam = Exam::create([
        'academic_year_id' => $otherAcademicYear->id,
        'institute_class_id' => $otherClass->id,
        'name' => 'Other exam',
        'type' => 'term',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);
    $otherStudent = Student::create([
        'institute_id' => $otherInstitute->id,
        'student_id' => 'OTHER-STU',
        'roll_no' => '1',
        'name' => 'Other Student',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2019-01-01',
        'gender' => 'other',
        'address' => 'Other address',
    ]);
    $otherResult = Result::create([
        'exam_id' => $otherExam->id,
        'student_id' => $otherStudent->id,
        'institute_class_id' => $otherClass->id,
        'section_id' => $fixture['section']->id,
        'total_subjects' => 1,
        'total_marks' => 80,
        'total_point' => 4,
        'gpa' => 4,
        'final_grade' => 'A',
        'total_absent' => 0,
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('results.toggle-publish', $otherResult))
        ->assertNotFound();
});
