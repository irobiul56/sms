<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\Exam;
use App\Models\ExamInstituteSubjec;
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
use Illuminate\Support\Facades\URL;

function createExamDocumentsFixture(): array
{
    $user = User::factory()->create();
    $institute = Institute::create([
        'user_id' => $user->id,
        'name' => 'Test School',
        'logo' => 'institute-logos/test-school.png',
    ]);
    $user->update(['institute_id' => $institute->id]);
    $year = AcademicYear::create([
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
        'bn_name' => 'প্রথম শ্রেণি',
        'numeric_value' => 1,
    ]);
    $section = Section::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);
    $exam = Exam::create([
        'academic_year_id' => $year->id,
        'institute_class_id' => $class->id,
        'name' => 'First Term',
        'bn_name' => 'প্রথম সাময়িক পরীক্ষা',
        'type' => 'term',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);
    $subject = InstituteSubject::create([
        'institute_id' => $institute->id,
        'name' => 'Mathematics',
        'bn_name' => 'গণিত',
        'code' => 'DOC-MATH',
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
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
    $teacher = Teacher::create([
        'institute_id' => $institute->id,
        'employee_id' => 'DOC-TEA',
        'name' => 'Teacher',
        'designation' => 'Teacher',
        'phone' => '01700000000',
        'email' => 'doc-teacher@example.test',
        'gender' => 'other',
        'joining_date' => '2026-01-01',
    ]);

    $students = collect([['DOC-1', 'Alice', '2'], ['DOC-2', 'Bob', '1']])
        ->map(function (array $data) use ($institute, $year, $class, $section) {
            $student = Student::create([
                'institute_id' => $institute->id,
                'student_id' => $data[0],
                'roll_no' => $data[2],
                'name' => $data[1],
                'bn_name' => $data[1] === 'Alice' ? 'এলিস' : 'বব',
                'photo' => $data[1] === 'Alice' ? 'student-photos/alice.png' : null,
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
                'roll_number' => $data[2],
                'group' => 'general',
            ]);

            return $student;
        });

    Mark::create([
        'exam_id' => $exam->id,
        'student_id' => $students[0]->id,
        'institute_subject_id' => $subject->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'teacher_id' => $teacher->id,
        'total_marks' => 85,
        'grade_point' => 5,
        'grade' => 'A+',
    ]);
    $result = Result::create([
        'exam_id' => $exam->id,
        'student_id' => $students[0]->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'total_subjects' => 1,
        'total_marks' => 85,
        'total_point' => 5,
        'gpa' => 5,
        'final_grade' => 'A+',
        'total_absent' => 0,
        'position' => 1,
        'is_published' => false,
    ]);

    return compact('user', 'institute', 'year', 'class', 'section', 'exam', 'subject', 'teacher', 'students', 'result');
}

test('exam document selector lists only institute exams and enrolled students', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.index', ['exam_id' => $fixture['exam']->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Index')
            ->where('classes.0.id', $fixture['class']->id)
            ->where('selectedExam.id', $fixture['exam']->id)
            ->where('classStudentCount', 2)
            ->where('classHasAllResults', false)
            ->has('students', 2)
            ->where('students.0.name', 'Bob')
            ->where('students.1.name', 'Alice')
            ->where('students.1.has_result', true)
            ->where('students.1.has_published_result', false));
});

test('exam document filters search students by institute class section and roll', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.index', [
            'class_id' => $fixture['class']->id,
            'exam_id' => $fixture['exam']->id,
            'section_id' => $fixture['section']->id,
            'roll_number' => '1',
            'document_type' => 'admit-card',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Index')
            ->where('filters.class_id', (string) $fixture['class']->id)
            ->where('filters.section_id', (string) $fixture['section']->id)
            ->where('filters.roll_number', '1')
            ->where('filters.document_type', 'admit-card')
            ->has('sections', 1)
            ->has('students', 1)
            ->where('students.0.roll_number', '1')
            ->where('students.0.name', 'Bob'));
});

test('teacher class permissions control exam documents and result publishing by assignment scope', function () {
    $fixture = createExamDocumentsFixture();
    $teacherUser = User::create([
        'name' => 'Document Teacher',
        'email' => 'document-teacher-login@example.test',
        'password' => bcrypt('TeacherPass123!'),
        'institute_id' => $fixture['institute']->id,
        'usertype' => 'teacher',
    ]);
    $fixture['teacher']->update(['user_id' => $teacherUser->id]);
    $assignment = ClassTeacher::create([
        'teacher_id' => $fixture['teacher']->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'academic_year_id' => $fixture['year']->id,
        'permissions' => [
            'admit_cards.print',
            'seat_plan.print',
            'results.publish',
            'marksheets.print',
            'tabulation_sheet.print',
        ],
    ]);
    $fixture['result']->update(['is_published' => true]);

    $this->actingAs($teacherUser)
        ->get(route('exam-documents.index', [
            'class_id' => $fixture['class']->id,
            'exam_id' => $fixture['exam']->id,
            'section_id' => $fixture['section']->id,
            'document_type' => 'admit-card',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentPermissions', [
                'admit_cards.print' => true,
                'seat_plan.print' => true,
                'marksheets.print' => true,
                'tabulation_sheet.print' => true,
            ]));

    $this->get(route('exam-documents.class-admit-cards', [
        'exam' => $fixture['exam']->id,
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
    ]))->assertOk();

    $this->get(route('exam-documents.marksheet', [
        'exam' => $fixture['exam']->id,
        'student' => $fixture['students'][0]->id,
        'class_id' => $fixture['class']->id,
    ]))->assertOk();

    $this->get(route('exam-documents.class-tabulation-sheet', [
        'exam' => $fixture['exam']->id,
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
    ]))->assertOk();

    $this->get(route('exam-documents.seat-plan', [
        'exam' => $fixture['exam']->id,
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'class_ids' => [$fixture['class']->id],
        'rooms' => [[
            'room_name' => 'Room 1',
            'bench_rows' => 1,
            'seats_per_bench' => 2,
        ]],
    ]))->assertOk();

    $this->get(route('results.publish', [
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
    ]))->assertOk();
    $this->post(route('results.toggle-publish', $fixture['result']->id))->assertRedirect();

    $assignment->update(['permissions' => ['admit_cards.print']]);
    $this->get(route('exam-documents.class-tabulation-sheet', [
        'exam' => $fixture['exam']->id,
        'class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
    ]))->assertForbidden();
    $this->get(route('results.publish'))->assertForbidden();
});

test('exam documents support a secondary class assigned to a multi-class exam', function () {
    $fixture = createExamDocumentsFixture();
    $class = InstituteClass::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 2',
        'bn_name' => 'দ্বিতীয় শ্রেণি',
        'numeric_value' => 2,
    ]);
    $section = Section::create([
        'institute_id' => $fixture['institute']->id,
        'institute_class_id' => $class->id,
        'name' => 'B',
    ]);
    $student = Student::create([
        'institute_id' => $fixture['institute']->id,
        'student_id' => 'DOC-3',
        'roll_no' => '3',
        'name' => 'Charlie',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2018-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $fixture['year']->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '3',
        'group' => 'general',
    ]);
    $fixture['exam']->classes()->sync([$fixture['class']->id, $class->id]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.index', [
            'class_id' => $class->id,
            'exam_id' => $fixture['exam']->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedExam.class_id', $class->id)
            ->where('selectedExam.class_name', $class->name)
            ->where('seatPlanClasses.1.name', $class->name)
            ->where('seatPlanClasses.1.student_count', 1)
            ->where('students.0.name', $student->name));

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.admit-card', [
            'exam' => $fixture['exam']->id,
            'student' => $student->id,
            'class_id' => $class->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('exam.class_name', $class->name)
            ->where('student.name', $student->name));

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', [
            'exam' => $fixture['exam']->id,
            'class_id' => $class->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('exam.class_name', $class->name)
            ->where('seatPlan.students.0.student.name', $student->name));
});

test('marksheet uses saved result and subject marks for the selected student', function () {
    $fixture = createExamDocumentsFixture();
    $fixture['result']->update(['is_published' => true]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.marksheet', [$fixture['exam']->id, $fixture['students'][0]->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Print')
            ->where('documentType', 'marksheet')
            ->where('student.name', 'Alice')
            ->where('student.bn_name', 'এলিস')
            ->where('student.father_name', 'Guardian')
            ->where('student.mother_name', 'Guardian')
            ->where('exam.bn_name', 'প্রথম সাময়িক পরীক্ষা')
            ->where('exam.class_bn_name', 'প্রথম শ্রেণি')
            ->where('subjects.0.name', 'Mathematics')
            ->where('subjects.0.bn_name', 'গণিত')
            ->where('subjects.0.mark.total_marks', '85.00')
            ->where('result.final_grade', 'A+'));
});

test('marksheets recalculate grade and point from exam full and pass marks', function () {
    $fixture = createExamDocumentsFixture();
    $fixture['subject']->update([
        'theory_marks' => 10,
        'theory_pass_marks' => 5,
        'mcq_marks' => 20,
        'mcq_pass_marks' => 7,
    ]);
    ExamInstituteSubjec::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('institute_subject_id', $fixture['subject']->id)
        ->update([
            'full_marks' => 10,
            'pass_marks' => 5,
        ]);
    Mark::query()
        ->where('student_id', $fixture['students'][0]->id)
        ->update([
            'total_marks' => 10,
            'theory_marks' => 10,
            'mcq_marks' => 0,
            'grade' => 'F',
            'grade_point' => 0,
        ]);
    $this->actingAs($fixture['user'])
        ->post(route('results.generate', $fixture['exam']))
        ->assertRedirect();

    $result = Result::query()
        ->where('exam_id', $fixture['exam']->id)
        ->where('student_id', $fixture['students'][0]->id)
        ->firstOrFail();
    $result->update(['is_published' => true]);
    Mark::query()
        ->where('student_id', $fixture['students'][0]->id)
        ->update(['grade' => 'F', 'grade_point' => 0]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.marksheet', [$fixture['exam']->id, $fixture['students'][0]->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subjects.0.mark.grade', 'A+')
            ->where('subjects.0.mark.grade_point', '5.00')
            ->where('subjects.0.full_marks', 10)
            ->where('subjects.0.pass_marks', 5)
            ->where('result.gpa', '5.00')
            ->where('result.final_grade', 'A+'));
});

test('individual marksheet cannot be printed before its result is published', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.marksheet', [$fixture['exam']->id, $fixture['students'][0]->id]))
        ->assertNotFound();
});

test('admit card includes the exam subject schedule', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.admit-card', [$fixture['exam']->id, $fixture['students'][0]->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Print')
            ->where('documentType', 'admit-card')
            ->where('subjects.0.name', 'Mathematics')
            ->where('subjects.0.exam_date', '01 Jun 2026')
            ->where('subjects.0.start_time', '09:00 AM'));
});

test('class marksheets include every enrolled student when all results exist', function () {
    $fixture = createExamDocumentsFixture();
    $fixture['subject']->update([
        'theory_marks' => 50,
        'mcq_marks' => 30,
        'practical_marks' => 20,
    ]);
    Mark::query()
        ->where('student_id', $fixture['students'][0]->id)
        ->update([
            'theory_marks' => 45,
            'mcq_marks' => 25,
            'practical_marks' => 15,
        ]);
    $fixture['result']->update(['is_published' => true]);
    Result::create([
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['students'][1]->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'total_subjects' => 1,
        'total_marks' => 95,
        'total_point' => 4,
        'gpa' => 4,
        'final_grade' => 'F',
        'total_absent' => 0,
        'position' => 2,
        'is_published' => true,
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-marksheets', $fixture['exam']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Print')
            ->where('documentType', 'marksheet')
            ->where('isBulkMarksheets', true)
            ->has('documents', 2)
            ->where('documents.0.student.name', 'Alice')
            ->where('documents.0.result.position', 1)
            ->where('documents.0.subjects.0.mcq_full_marks', 30)
            ->where('documents.0.subjects.0.practical_full_marks', 20)
            ->where('documents.0.subjects.0.theory_full_marks', 50)
            ->where('documents.0.subjects.0.mark.theory_marks', '45.00')
            ->where('documents.0.subjects.0.mark.mcq_marks', '25.00')
            ->where('documents.0.subjects.0.mark.practical_marks', '15.00')
            ->where('documents.0.student.father_name', 'Guardian')
            ->where('documents.0.student.mother_name', 'Guardian')
            ->where('documents.0.publicMarksheetUrl', URL::signedRoute('public.marksheet', [
                'exam' => $fixture['exam']->id,
                'student' => $fixture['students'][0]->id,
            ]))
            ->where('documents.1.student.name', 'Bob')
            ->where('documents.1.result.position', 2));
});

test('class marksheets require a generated result for every enrolled student', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-marksheets', $fixture['exam']->id))
        ->assertUnprocessable();
});

test('class marksheets require every result to be published', function () {
    $fixture = createExamDocumentsFixture();
    $fixture['result']->update(['is_published' => true]);
    Result::create([
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['students'][1]->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'total_subjects' => 1,
        'total_marks' => 72,
        'total_point' => 4,
        'gpa' => 4,
        'final_grade' => 'A',
        'total_absent' => 0,
        'position' => 2,
        'is_published' => false,
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-marksheets', $fixture['exam']->id))
        ->assertUnprocessable();
});

test('class admit cards include every enrolled student and the exam schedule', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-admit-cards', $fixture['exam']->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Print')
            ->where('documentType', 'admit-card')
            ->has('documents', 2)
            ->where('documents.0.student.name', 'Bob')
            ->where('documents.1.student.name', 'Alice')
            ->where('documents.1.student.photo', asset('storage/student-photos/alice.png'))
            ->where('documents.0.subjects.0.name', 'Mathematics')
            ->where('institute.logo_url', asset('storage/institute-logos/test-school.png')));
});

test('published marksheets have signed public QR links that stop working when unpublished', function () {
    $fixture = createExamDocumentsFixture();
    $fixture['result']->update(['is_published' => true]);
    $publicUrl = URL::signedRoute('public.marksheet', [
        'exam' => $fixture['exam']->id,
        'student' => $fixture['students'][0]->id,
    ]);

    $this->get(route('public.marksheet', [
        'exam' => $fixture['exam']->id,
        'student' => $fixture['students'][0]->id,
    ]))->assertForbidden();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.marksheet', [
            'exam' => $fixture['exam']->id,
            'student' => $fixture['students'][0]->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('publicMarksheetUrl', $publicUrl)
            ->where('institute.logo_url', asset('storage/institute-logos/test-school.png')));

    auth()->logout();
    $this->get($publicUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isPublicMarksheet', true)
            ->where('student.name', 'Alice')
            ->where('result.total_marks', '85.00'));

    $fixture['result']->update(['is_published' => false]);
    $this->get($publicUrl)->assertNotFound();
});


test('class print honors the loaded section and roll filters', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-admit-cards', [
            'exam' => $fixture['exam']->id,
            'section_id' => $fixture['section']->id,
            'roll_number' => '1',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', 1)
            ->where('documents.0.student.name', 'Bob'));
});

test('class tabulation sheet shows all exam subjects and filtered student marks', function () {
    $fixture = createExamDocumentsFixture();
    $fixture['result']->update([
        'gpa' => 0,
        'final_grade' => 'F',
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-tabulation-sheet', [
            'exam' => $fixture['exam']->id,
            'section_id' => $fixture['section']->id,
            'roll_number' => '2',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Print')
            ->where('documentType', 'tabulation')
            ->where('institute.logo_url', asset('storage/institute-logos/test-school.png'))
            ->where('exam.class_name', 'Class 1')
            ->where('tabulation.section_name', 'A')
            ->has('tabulation.subjects', 1)
            ->where('tabulation.subjects.0.name', 'Mathematics')
            ->has('tabulation.rows', 1)
            ->where('tabulation.rows.0.student.name', 'Alice')
            ->where('tabulation.rows.0.subjects.0.mark.total_marks', '85.00')
            ->where('tabulation.rows.0.result.gpa', '5.00')
            ->where('tabulation.rows.0.result.final_grade', 'A+'));
});

test('tabulation subject cells stay aligned with all subject headers for elective choices', function () {
    $fixture = createExamDocumentsFixture();
    $studentInfo = StudentAcademicInfo::query()
        ->where('student_id', $fixture['students'][0]->id)
        ->firstOrFail();
    $choiceSubjects = collect([
        ['name' => 'Elective One', 'code' => 'DOC-EL-1', 'date' => '2026-06-02'],
        ['name' => 'Elective Two', 'code' => 'DOC-EL-2', 'date' => '2026-06-03'],
    ])->map(function (array $data) use ($fixture) {
        $subject = InstituteSubject::create([
            'institute_id' => $fixture['institute']->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'type' => 'elective',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        $fixture['class']->subjects()->attach($subject->id, [
            'type' => 'elective',
            'full_marks' => 100,
            'pass_marks' => 33,
            'is_group_based' => false,
        ]);
        ExamInstituteSubjec::create([
            'exam_id' => $fixture['exam']->id,
            'institute_subject_id' => $subject->id,
            'institute_class_id' => $fixture['class']->id,
            'exam_date' => $data['date'],
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        return $subject;
    });
    $fixture['class']->subjects()->attach($fixture['subject']->id, [
        'type' => 'compulsory',
        'full_marks' => 100,
        'pass_marks' => 33,
        'is_group_based' => false,
    ]);
    $studentInfo->update(['elective_subject_id' => $choiceSubjects[0]->id]);
    Mark::create([
        'exam_id' => $fixture['exam']->id,
        'student_id' => $fixture['students'][0]->id,
        'institute_subject_id' => $choiceSubjects[0]->id,
        'institute_class_id' => $fixture['class']->id,
        'section_id' => $fixture['section']->id,
        'teacher_id' => Teacher::query()->where('institute_id', $fixture['institute']->id)->value('id'),
        'total_marks' => 75,
        'grade_point' => 4,
        'grade' => 'A',
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-tabulation-sheet', [
            'exam' => $fixture['exam']->id,
            'section_id' => $fixture['section']->id,
            'roll_number' => '2',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tabulation.subjects', 3)
            ->has('tabulation.rows.0.subjects', 3)
            ->where('tabulation.subjects.1.name', 'Elective One')
            ->where('tabulation.rows.0.subjects.1.mark.total_marks', '75.00')
            ->where('tabulation.subjects.2.name', 'Elective Two')
            ->where('tabulation.rows.0.subjects.2.mark', null));
});

test('seat plan assigns seats in roll order and limits seats per row', function () {
    $fixture = createExamDocumentsFixture();
    $secondSection = Section::create([
        'institute_id' => $fixture['institute']->id,
        'institute_class_id' => $fixture['class']->id,
        'name' => 'B',
    ]);
    StudentAcademicInfo::query()
        ->where('student_id', $fixture['students'][0]->id)
        ->update(['section_id' => $secondSection->id]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', [
            'exam' => $fixture['exam']->id,
            'seats_per_row' => 1,
            'room_name' => 'Room 2',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Institute/ExamDocuments/Print')
            ->where('documentType', 'seat-plan')
            ->where('institute.logo_url', asset('storage/institute-logos/test-school.png'))
            ->where('seatPlan.room_name', 'Room 2')
            ->where('seatPlan.students.0.student.name', 'Bob')
            ->where('seatPlan.students.0.row', 1)
            ->where('seatPlan.students.0.seat', 1)
            ->where('seatPlan.students.0.student.section_name', 'A')
            ->where('seatPlan.students.1.student.name', 'Alice')
            ->where('seatPlan.students.1.student.section_name', 'B')
            ->where('seatPlan.students.1.row', 2));
});

test('seat plan distributes the class across multiple room bench-row grids', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', [
            'exam' => $fixture['exam']->id,
            'rooms' => [
                ['room_name' => 'Room A', 'bench_rows' => 1, 'seats_per_bench' => 1],
                ['room_name' => 'Room B', 'bench_rows' => 1, 'seats_per_bench' => 1],
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seatPlan.rooms.0.room_name', 'Room A')
            ->where('seatPlan.rooms.0.bench_rows', 1)
            ->where('seatPlan.rooms.0.seats_per_bench', 1)
            ->where('seatPlan.rooms.0.students.0.student.name', 'Bob')
            ->where('seatPlan.rooms.0.students.0.student.roll_number', '1')
            ->where('seatPlan.rooms.1.room_name', 'Room B')
            ->where('seatPlan.rooms.1.students.0.student.name', 'Alice')
            ->where('seatPlan.rooms.1.students.0.row', 1)
            ->has('seatPlan.students', 2)
            ->where('seatPlan.students.0.room_name', 'Room A')
            ->where('seatPlan.students.1.room_name', 'Room B'));
});

test('seat plan can mix students from multiple exam classes in the same room', function () {
    $fixture = createExamDocumentsFixture();
    $class = InstituteClass::create([
        'institute_id' => $fixture['institute']->id,
        'name' => 'Class 2',
        'bn_name' => 'দ্বিতীয় শ্রেণি',
        'numeric_value' => 2,
    ]);
    $section = Section::create([
        'institute_id' => $fixture['institute']->id,
        'institute_class_id' => $class->id,
        'name' => 'B',
    ]);
    $student = Student::create([
        'institute_id' => $fixture['institute']->id,
        'student_id' => 'DOC-3',
        'roll_no' => '1',
        'name' => 'Charlie',
        'father_name' => 'Guardian',
        'mother_name' => 'Guardian',
        'date_of_birth' => '2018-01-01',
        'gender' => 'other',
        'address' => 'Test address',
        'status' => 'active',
    ]);
    StudentAcademicInfo::create([
        'student_id' => $student->id,
        'academic_year_id' => $fixture['year']->id,
        'institute_class_id' => $class->id,
        'section_id' => $section->id,
        'roll_number' => '1',
        'group' => 'general',
    ]);
    $fixture['exam']->classes()->sync([$fixture['class']->id, $class->id]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', [
            'exam' => $fixture['exam']->id,
            'class_id' => $fixture['class']->id,
            'class_ids' => [$fixture['class']->id, $class->id],
            'rooms' => [
                ['room_name' => 'Mixed Room', 'bench_rows' => 2, 'seats_per_bench' => 2],
            ],
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seatPlan.rooms.0.room_name', 'Mixed Room')
            ->has('seatPlan.rooms.0.students', 3)
            ->where('seatPlan.rooms.0.students.0.class_name', $fixture['class']->name)
            ->where('seatPlan.rooms.0.students.0.student.name', 'Bob')
            ->where('seatPlan.rooms.0.students.1.class_name', $class->name)
            ->where('seatPlan.rooms.0.students.1.student.name', 'Charlie')
            ->where('seatPlan.rooms.0.students.2.class_name', $fixture['class']->name)
            ->where('seatPlan.rooms.0.students.2.student.name', 'Alice'));
});

test('seat plan rejects room layouts without enough capacity for all loaded students', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', [
            'exam' => $fixture['exam']->id,
            'rooms' => [
                ['room_name' => 'Small Room', 'bench_rows' => 1, 'seats_per_bench' => 1],
            ],
        ]))
        ->assertSessionHasErrors('rooms');
});

test('individual seat plan prints only that students label with the class assigned seat', function () {
    $fixture = createExamDocumentsFixture();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', [
            'exam' => $fixture['exam']->id,
            'student_id' => $fixture['students'][0]->id,
            'seats_per_row' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seatPlan.single_student', true)
            ->has('seatPlan.students', 1)
            ->where('seatPlan.students.0.student.name', 'Alice')
            ->where('seatPlan.students.0.row', 2)
            ->where('seatPlan.students.0.seat', 1));
});

test('exam documents cannot be generated for another institutes exam', function () {
    $fixture = createExamDocumentsFixture();
    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);
    $otherYear = AcademicYear::create([
        'institute_id' => $otherInstitute->id,
        'name' => '2026',
        'year' => 2026,
        'session' => '2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $otherClass = InstituteClass::create([
        'institute_id' => $otherInstitute->id,
        'name' => 'Other Class',
        'numeric_value' => 1,
    ]);
    $otherExam = Exam::create([
        'academic_year_id' => $otherYear->id,
        'institute_class_id' => $otherClass->id,
        'name' => 'Other Exam',
        'type' => 'term',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-05',
    ]);

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.seat-plan', $otherExam))
        ->assertNotFound();

    $this->actingAs($fixture['user'])
        ->get(route('exam-documents.class-tabulation-sheet', $otherExam))
        ->assertNotFound();
});
