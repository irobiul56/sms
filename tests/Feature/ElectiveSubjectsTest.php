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

function createElectiveSubjectFixture(): array
{
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id, 'name' => 'Elective School']);
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
        'name' => 'Class 9',
        'numeric_value' => 9,
    ]);
    $otherClass = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Class 10',
        'numeric_value' => 10,
    ]);
    $section = Section::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);
    $elective = InstituteSubject::create([
        'institute_id' => $institute->id,
        'name' => 'Agriculture',
        'code' => 'EL-AG',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $class->subjects()->attach($elective->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    $exam = Exam::create([
        'academic_year_id' => $academicYear->id,
        'institute_class_id' => $class->id,
        'name' => 'First Term',
        'type' => 'term',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    return compact('user', 'institute', 'academicYear', 'class', 'otherClass', 'section', 'elective', 'exam');
}

function electiveStudentPayload(array $fixture): array
{
    return [
        'student_id' => 'EL-STU-1',
        'name' => 'Elective Student',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2010-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
        'password' => 'StudentPass123!',
        'password_confirmation' => 'StudentPass123!',
        'academic_year_id' => $fixture['academicYear']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'roll_number' => '1',
    ];
}

test('student enrollment requires and stores one class-assigned elective', function () {
    $fixture = createElectiveSubjectFixture();
    $url = route('students.store');

    $this->actingAs($fixture['user'])
        ->get(route('students.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('electiveSubjects.0.id', $fixture['elective']->id)
            ->where('electiveSubjects.0.class_id', $fixture['class']->id));

    $this->actingAs($fixture['user'])
        ->get(route('exam-subjects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subjects.0.id', $fixture['elective']->id)
            ->where('subjects.0.class_ids.0', $fixture['class']->id));

    $this->actingAs($fixture['user'])
        ->post($url, electiveStudentPayload($fixture))
        ->assertSessionHasErrors('elective_subject_id');

    $this->actingAs($fixture['user'])
        ->post($url, electiveStudentPayload($fixture) + [
            'elective_subject_id' => $fixture['elective']->id,
        ])
        ->assertRedirect(route('students.index'));

    expect(StudentAcademicInfo::query()->value('elective_subject_id'))
        ->toBe($fixture['elective']->id);
});

test('exam routine print page is scoped to the selected class and exam', function () {
    $fixture = createElectiveSubjectFixture();
    $fixture['institute']->update(['address' => 'Main Road']);
    $examSubject = ExamInstituteSubjec::create([
        'exam_id' => $fixture['exam']->id,
        'institute_subject_id' => $fixture['elective']->id,
        'institute_class_id' => $fixture['class']->id,
        'exam_date' => '2026-06-02',
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-subjects.routine-print', [
            'class_id' => $fixture['class']->id,
            'exam_id' => $fixture['exam']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamSubjects/RoutinePrint')
            ->where('institute.name', 'Elective School')
            ->where('institute.address', 'Main Road')
            ->where('schoolClass.name', 'Class 9')
            ->where('exam.name', 'First Term')
            ->where('subjects.0.id', $examSubject->id)
            ->where('subjects.0.subject_name', 'Agriculture'));

    $this->actingAs($fixture['user'])
        ->get(route('exam-subjects.routine-print', [
            'class_id' => $fixture['otherClass']->id,
            'exam_id' => $fixture['exam']->id,
        ]))
        ->assertNotFound();
});

test('student enrollment requires and stores optional and additional choices when configured', function () {
    $fixture = createElectiveSubjectFixture();
    $choices = [];

    foreach (['optional', 'additional'] as $type) {
        $subject = InstituteSubject::create([
            'institute_id' => $fixture['institute']->id,
            'name' => ucfirst($type).' Subject',
            'code' => strtoupper(substr($type, 0, 2)).'-1',
            'type' => $type,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        $fixture['class']->subjects()->attach($subject->id, [
            'type' => $type,
            'full_marks' => 100,
            'pass_marks' => 33,
            'is_group_based' => false,
        ]);
        $choices[$type] = $subject;
    }

    $this->actingAs($fixture['user'])
        ->get(route('students.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('optionalSubjects.0.id', $choices['optional']->id)
            ->where('additionalSubjects.0.id', $choices['additional']->id));

    $payload = electiveStudentPayload($fixture) + [
        'elective_subject_id' => $fixture['elective']->id,
    ];
    $this->actingAs($fixture['user'])
        ->post(route('students.store'), $payload)
        ->assertSessionHasErrors(['optional_subject_id', 'additional_subject_id']);

    $this->actingAs($fixture['user'])
        ->post(route('students.store'), $payload + [
            'optional_subject_id' => $choices['optional']->id,
            'additional_subject_id' => $choices['additional']->id,
        ])
        ->assertRedirect(route('students.index'));

    $academicInfo = StudentAcademicInfo::query()->firstOrFail();
    expect($academicInfo->optional_subject_id)->toBe($choices['optional']->id)
        ->and($academicInfo->additional_subject_id)->toBe($choices['additional']->id);
});

test('elective subjects can be assigned only to exams covering their class', function () {
    $fixture = createElectiveSubjectFixture();
    $payload = [
        'exam_id' => $fixture['exam']->id,
        'institute_subject_id' => $fixture['elective']->id,
        'exam_date' => '2026-06-01',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'full_marks' => 100,
        'pass_marks' => 33,
    ];

    $this->actingAs($fixture['user'])
        ->post(route('exam-subjects.store'), $payload)
        ->assertRedirect(route('exam-subjects.index'));

    expect(ExamInstituteSubjec::query()->where('exam_id', $fixture['exam']->id)->count())
        ->toBe(1);

    $otherSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Other Class Elective',
        'code' => 'EL-OTHER',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['otherClass']->subjects()->attach($otherSubject->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);

    $this->actingAs($fixture['user'])
        ->from(route('exam-subjects.index'))
        ->post(route('exam-subjects.store'), array_merge($payload, [
            'institute_subject_id' => $otherSubject->id,
        ]))
        ->assertSessionHasErrors('institute_subject_id');
});

test('exam subject assignment rejects a subject from a different selected class', function () {
    $fixture = createElectiveSubjectFixture();
    $otherSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 10 Subject',
        'code' => 'C10-1',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['otherClass']->subjects()->attach($otherSubject->id, [
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    $fixture['exam']->classes()->attach($fixture['otherClass']->id);

    $payload = [
        'exam_id' => $fixture['exam']->id,
        'class_id' => $fixture['otherClass']->id,
        'institute_subject_ids' => [$fixture['elective']->id],
        'exam_date' => '2026-06-01',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'full_marks' => 100,
        'pass_marks' => 33,
    ];

    $this->actingAs($fixture['user'])
        ->from(route('exam-subjects.index'))
        ->post(route('exam-subjects.store'), $payload)
        ->assertSessionHasErrors('institute_subject_ids.0');

    $this->actingAs($fixture['user'])
        ->post(route('exam-subjects.store'), array_merge($payload, [
            'institute_subject_ids' => [$otherSubject->id],
        ]))
        ->assertRedirect(route('exam-subjects.index'));

    $this->actingAs($fixture['user'])
        ->from(route('exam-subjects.index'))
        ->post(route('exam-subjects.store'), [
            'exam_id' => $fixture['exam']->id,
            'class_ids' => [$fixture['class']->id, $fixture['otherClass']->id],
            'institute_subject_id' => $fixture['elective']->id,
            'exam_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ])
        ->assertSessionHasErrors('class_ids');
});

test('monthly exam subject assignment accepts subjects from multiple selected classes', function () {
    $fixture = createElectiveSubjectFixture();
    $secondSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 10 Mathematics',
        'code' => 'C10-MATH',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['otherClass']->subjects()->attach($secondSubject->id, [
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    $fixture['exam']->update(['type' => 'monthly']);
    $fixture['exam']->classes()->attach($fixture['otherClass']->id);

    $this->actingAs($fixture['user'])
        ->post(route('exam-subjects.store'), [
            'exam_id' => $fixture['exam']->id,
            'class_ids' => [$fixture['class']->id, $fixture['otherClass']->id],
            'institute_subject_ids' => [$fixture['elective']->id, $secondSubject->id],
            'exam_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ])
        ->assertRedirect(route('exam-subjects.index'));

    expect(ExamInstituteSubjec::query()->where('exam_id', $fixture['exam']->id)->count())
        ->toBe(2);
});

test('the same subject can have a different schedule for each exam class', function () {
    $fixture = createElectiveSubjectFixture();
    $fixture['exam']->update(['type' => 'monthly']);
    $fixture['exam']->classes()->attach($fixture['otherClass']->id);
    $fixture['otherClass']->subjects()->attach($fixture['elective']->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);

    foreach ([
        [$fixture['class']->id, '2026-06-01'],
        [$fixture['otherClass']->id, '2026-06-02'],
    ] as [$classId, $date]) {
        $this->actingAs($fixture['user'])
            ->post(route('exam-subjects.store'), [
                'exam_id' => $fixture['exam']->id,
                'class_ids' => [$classId],
                'institute_subject_ids' => [$fixture['elective']->id],
                'exam_date' => $date,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'full_marks' => 100,
                'pass_marks' => 33,
            ])
            ->assertRedirect(route('exam-subjects.index'));
    }

    $this->actingAs($fixture['user'])
        ->get(route('exam-subjects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('examSubjects.0.class_id', $fixture['otherClass']->id)
            ->where('examSubjects.0.class_name', 'Class 10')
            ->where('examSubjects.0.formatted_date', '02 Jun, 2026')
            ->where('examSubjects.1.class_id', $fixture['class']->id)
            ->where('examSubjects.1.class_name', 'Class 9')
            ->where('examSubjects.1.formatted_date', '01 Jun, 2026'));
});

test('non-monthly exam subject assignment rejects multiple subjects', function () {
    $fixture = createElectiveSubjectFixture();
    $secondSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Computer Science',
        'code' => 'EL-CS',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($secondSubject->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);

    $this->actingAs($fixture['user'])
        ->from(route('exam-subjects.index'))
        ->post(route('exam-subjects.store'), [
            'exam_id' => $fixture['exam']->id,
            'class_ids' => [$fixture['class']->id],
            'institute_subject_ids' => [$fixture['elective']->id, $secondSubject->id],
            'exam_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ])
        ->assertSessionHasErrors('institute_subject_ids');

    expect(ExamInstituteSubjec::query()->where('exam_id', $fixture['exam']->id)->count())
        ->toBe(0);
});

test('exam subjects without marks can be deleted', function () {
    $fixture = createElectiveSubjectFixture();
    $examSubject = ExamInstituteSubjec::create([
        'exam_id' => $fixture['exam']->id,
        'institute_subject_id' => $fixture['elective']->id,
        'institute_class_id' => $fixture['class']->id,
        'exam_date' => '2026-06-01',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);

    $this->actingAs($fixture['user'])
        ->delete(route('exam-subjects.destroy', $examSubject))
        ->assertRedirect(route('exam-subjects.index'))
        ->assertSessionHas('flash.type', 'success');

    expect(ExamInstituteSubjec::query()->find($examSubject->id))->toBeNull();
});

test('multiple subjects can be assigned to an exam at once', function () {
    $fixture = createElectiveSubjectFixture();
    $fixture['exam']->update(['type' => 'monthly']);
    $secondSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Computer Science',
        'code' => 'EL-CS',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($secondSubject->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('exam-subjects.store'), [
            'exam_id' => $fixture['exam']->id,
            'institute_subject_ids' => [$fixture['elective']->id, $secondSubject->id],
            'exam_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ])
        ->assertRedirect(route('exam-subjects.index'));

    $assignedSubjects = ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->orderBy('institute_subject_id')
        ->get();

    expect($assignedSubjects)->toHaveCount(2)
        ->and($assignedSubjects->pluck('institute_subject_id')->all())
        ->toBe([$fixture['elective']->id, $secondSubject->id]);
});

test('multiple subjects can be assigned different exam full and pass marks', function () {
    $fixture = createElectiveSubjectFixture();
    $fixture['exam']->update(['type' => 'monthly']);
    $secondSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Computer Science',
        'code' => 'EL-CS',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($secondSubject->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('exam-subjects.store'), [
            'exam_id' => $fixture['exam']->id,
            'institute_subject_ids' => [$fixture['elective']->id, $secondSubject->id],
            'subject_marks' => [
                $fixture['elective']->id => ['full_marks' => 50, 'pass_marks' => 17],
                $secondSubject->id => ['full_marks' => 75, 'pass_marks' => 25],
            ],
            'exam_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])
        ->assertRedirect(route('exam-subjects.index'));

    expect(ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['elective']->id)
        ->value('full_marks'))->toBe(50)
        ->and(ExamInstituteSubjec::query()
            ->where('exam_id', $fixture['exam']->id)
            ->where('institute_subject_id', $fixture['elective']->id)
            ->value('pass_marks'))->toBe(17)
        ->and(ExamInstituteSubjec::query()
            ->where('exam_id', $fixture['exam']->id)
            ->where('institute_subject_id', $secondSubject->id)
            ->value('full_marks'))->toBe(75)
        ->and(ExamInstituteSubjec::query()
            ->where('exam_id', $fixture['exam']->id)
            ->where('institute_subject_id', $secondSubject->id)
            ->value('pass_marks'))->toBe(25);
});

test('multiple subject assignment rejects subjects outside the exam classes', function () {
    $fixture = createElectiveSubjectFixture();
    $fixture['exam']->update(['type' => 'monthly']);
    $otherSubject = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Other Class Elective',
        'code' => 'EL-OTHER',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['otherClass']->subjects()->attach($otherSubject->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);

    $this->actingAs($fixture['user'])
        ->from(route('exam-subjects.index'))
        ->post(route('exam-subjects.store'), [
            'exam_id' => $fixture['exam']->id,
            'institute_subject_ids' => [$fixture['elective']->id, $otherSubject->id],
            'exam_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ])
        ->assertSessionHasErrors('institute_subject_ids.1');

    expect(ExamInstituteSubjec::query()->where('exam_id', $fixture['exam']->id)->count())
        ->toBe(0);
});

test('mark entry lists only students who chose the selected elective', function () {
    $fixture = createElectiveSubjectFixture();
    $otherElective = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Computer Science',
        'code' => 'EL-CS',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($otherElective->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    $students = collect([
        ['EL-STU-AG', $fixture['elective']->id],
        ['EL-STU-CS', $otherElective->id],
    ])->map(function (array $data, int $index) use ($fixture) {
        $student = Student::create([
            'institute_id' => $fixture['institute']->id,
            'student_id' => $data[0],
            'roll_no' => (string) ($index + 1),
            'name' => $data[0],
            'father_name' => 'Guardian',
            'mother_name' => 'Guardian',
            'date_of_birth' => '2010-01-01',
            'gender' => 'other',
            'address' => 'Test address',
            'status' => 'active',
        ]);
        StudentAcademicInfo::create([
            'student_id' => $student->id,
            'academic_year_id' => $fixture['academicYear']->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'roll_number' => (string) ($index + 1),
            'elective_subject_id' => $data[1],
        ]);

        return $student;
    });
    $teacher = Teacher::create([
        'institute_id' => $fixture['institute']->id,
        'employee_id' => 'EL-TEA-MARK',
        'name' => 'Elective Mark Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'elective-mark-teacher@example.test',
        'gender' => 'other',
        'joining_date' => '2026-01-01',
    ]);

    foreach ([$fixture['elective'], $otherElective] as $subject) {
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
    }

    $this->actingAs($fixture['user'])
        ->get(route('marks.get-students', [
            'class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'exam_id' => $fixture['exam']->id,
            'subject_id' => $fixture['elective']->id,
        ]))
        ->assertOk()
        ->assertJsonPath('students.0.student_id', 'EL-STU-AG')
        ->assertJsonCount(1, 'students');

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.admit-card', [$fixture['exam']->id, $students[0]->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('subjects.0.name', 'Agriculture')
            ->has('subjects', 1));

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-admit-cards', [
            $fixture['exam']->id,
            'class_id' => $fixture['class']->id,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documents.0.subjects.0.name', 'Agriculture')
            ->where('documents.1.subjects.0.name', 'Computer Science')
            ->has('documents', 2));

    $invalidMark = [
        'exam_id' => $fixture['exam']->id,
        'student_id' => $students[1]->id,
        'institute_subject_id' => $fixture['elective']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => $teacher->id,
        'theory_marks' => 80,
    ];
    $this->actingAs($fixture['user'])
        ->postJson(route('marks.store'), $invalidMark)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('student_id');

    $mark = Mark::create([
        'exam_id' => $fixture['exam']->id,
        'student_id' => $students[0]->id,
        'institute_subject_id' => $fixture['elective']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => $teacher->id,
        'total_marks' => 80,
        'grade_point' => 5,
        'grade' => 'A+',
    ]);
    $this->actingAs($fixture['user'])
        ->put(route('marks.update', $mark), array_merge($invalidMark, [
            'student_id' => $students[1]->id,
        ]))
        ->assertSessionHasErrors('student_id');
});

test('results count each students elected exam subject instead of every elective option', function () {
    $fixture = createElectiveSubjectFixture();
    $otherElective = InstituteSubject::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Computer Science',
        'code' => 'EL-CS-RESULT',
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
    ]);
    $fixture['class']->subjects()->attach($otherElective->id, [
        'type' => 'elective',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    $teacher = Teacher::create([
        'institute_id' => $fixture['institute']->id,
        'employee_id' => 'EL-TEA',
        'name' => 'Elective Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'elective-teacher@example.test',
        'gender' => 'other',
        'joining_date' => '2026-01-01',
    ]);
    $students = collect([
        ['EL-RESULT-AG', $fixture['elective']],
        ['EL-RESULT-CS', $otherElective],
    ])->map(function (array $data, int $index) use ($fixture, $teacher) {
        $student = Student::create([
            'institute_id' => $fixture['institute']->id,
            'student_id' => $data[0],
            'roll_no' => (string) ($index + 1),
            'name' => $data[0],
            'father_name' => 'Guardian',
            'mother_name' => 'Guardian',
            'date_of_birth' => '2010-01-01',
            'gender' => 'other',
            'address' => 'Test address',
            'status' => 'active',
        ]);
        StudentAcademicInfo::create([
            'student_id' => $student->id,
            'academic_year_id' => $fixture['academicYear']->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'roll_number' => (string) ($index + 1),
            'elective_subject_id' => $data[1]->id,
        ]);
        ExamInstituteSubjec::create([
            'exam_id' => $fixture['exam']->id,
            'institute_subject_id' => $data[1]->id,
            'institute_class_id' => $fixture['class']->id,
            'exam_date' => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        Mark::create([
            'exam_id' => $fixture['exam']->id,
            'student_id' => $student->id,
            'institute_subject_id' => $data[1]->id,
            'institute_class_id' => $fixture['class']->id,
            'section_id' => $fixture['section']->id,
            'teacher_id' => $teacher->id,
            'total_marks' => 85,
            'grade_point' => 5,
            'grade' => 'A+',
        ]);

        return $student;
    });

    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    expect(\App\Models\Result::query()->whereIn('student_id', $students->pluck('id'))->count())
        ->toBe(2)
        ->and(\App\Models\Result::query()->whereIn('student_id', $students->pluck('id'))->value('total_subjects'))
        ->toBe(1);
});
