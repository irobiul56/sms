<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClassOneDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $classes = InstituteClass::query()->where('name', 'Class 1')->get();

        if ($classes->count() !== 1) {
            throw new RuntimeException('Demo data requires exactly one class named Class 1 in the database.');
        }

        $class = $classes->first();
        $instituteId = $class->institute_id;
        $academicYear = AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->where('is_current', true)
            ->first();
        $section = Section::query()
            ->where('institute_id', $instituteId)
            ->where('institute_class_id', $class->id)
            ->first();

        if (! $academicYear || ! $section) {
            throw new RuntimeException('Class 1 must have a current academic year and at least one section before adding demo data.');
        }

        DB::transaction(function () use ($class, $instituteId, $academicYear, $section) {
            $this->ensureSubjects($class, $instituteId);
            $this->ensureStudents($class, $instituteId, $academicYear->id, $section->id);
            $this->ensureTeachers($class, $instituteId, $academicYear->id, $section->id);
        });
    }

    private function ensureSubjects(InstituteClass $class, int $instituteId): void
    {
        $subjects = [
            ['names' => ['Bangla', 'বাংলা'], 'name' => 'বাংলা', 'code' => 'BAN'],
            ['names' => ['English', 'ইংরেজি'], 'name' => 'English', 'code' => 'ENG'],
            ['names' => ['Mathematics', 'Math', 'গণিত'], 'name' => 'গণিত', 'code' => 'MAT'],
        ];

        foreach ($subjects as $subjectData) {
            $subject = InstituteSubject::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->whereIn('name', $subjectData['names'])
                ->first();

            if (! $subject) {
                $subject = InstituteSubject::create([
                    'institute_id' => $instituteId,
                    'name' => $subjectData['name'],
                    'code' => 'DEMO-I'.$instituteId.'-C1-'.$subjectData['code'],
                    'type' => 'compulsory',
                    'full_marks' => 100,
                    'pass_marks' => 33,
                    'display_order' => count($subjects),
                    'has_practical' => false,
                ]);
            }

            $subject->classes()->syncWithoutDetaching([
                $class->id => [
                    'type' => 'compulsory',
                    'full_marks' => 100,
                    'pass_marks' => 33,
                    'is_group_based' => false,
                ],
            ]);
        }
    }

    private function ensureStudents(InstituteClass $class, int $instituteId, int $academicYearId, int $sectionId): void
    {
        $studentCount = StudentAcademicInfo::query()
            ->where('institute_class_id', $class->id)
            ->where('academic_year_id', $academicYearId)
            ->whereHas('student', fn ($query) => $query->where('institute_id', $instituteId))
            ->count();

        for ($number = 1; $studentCount < 5; $number++) {
            $studentId = sprintf('DEMO-C1-STU-%02d', $number);

            if (Student::query()->where('student_id', $studentId)->exists()) {
                continue;
            }

            $student = Student::create([
                'institute_id' => $instituteId,
                'student_id' => $studentId,
                'roll_no' => sprintf('D%02d', $number),
                'name' => sprintf('Demo Student %d', $number),
                'bn_name' => sprintf('ডেমো শিক্ষার্থী %d', $number),
                'father_name' => 'Demo Guardian',
                'mother_name' => 'Demo Guardian',
                'date_of_birth' => now()->subYears(7)->startOfYear()->addDays($number - 1)->toDateString(),
                'gender' => 'other',
                'address' => 'Dummy data for Class 1',
                'status' => 'active',
            ]);

            StudentAcademicInfo::create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
                'institute_class_id' => $class->id,
                'section_id' => $sectionId,
                'roll_number' => sprintf('D%02d', $number),
                'monthly_fee' => 0,
                'group' => 'general',
                'attendance_days' => 0,
                'is_promoted' => false,
            ]);

            $studentCount++;
        }
    }

    private function ensureTeachers(InstituteClass $class, int $instituteId, int $academicYearId, int $sectionId): void
    {
        $instituteTeacherIds = Teacher::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->pluck('id');
        $assignedTeacherIds = ClassTeacher::query()
            ->where('institute_class_id', $class->id)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('teacher_id', $instituteTeacherIds)
            ->pluck('teacher_id')
            ->unique();

        for ($number = 1; $assignedTeacherIds->count() < 5; $number++) {
            $employeeId = sprintf('DEMO-C1-TEA-%02d', $number);
            $teacher = Teacher::withoutGlobalScopes()
                ->where('institute_id', $instituteId)
                ->where('employee_id', $employeeId)
                ->first();

            if (! $teacher) {
                $teacher = Teacher::create([
                    'institute_id' => $instituteId,
                    'employee_id' => $employeeId,
                    'name' => sprintf('Demo Teacher %d', $number),
                    'designation' => 'Class 1 Teacher',
                    'phone' => '0000000000',
                    'email' => sprintf('demo.teacher.%d.i%d@example.test', $number, $instituteId),
                    'gender' => 'other',
                    'joining_date' => now()->toDateString(),
                    'status' => 'active',
                ]);
            }

            if ($assignedTeacherIds->contains($teacher->id)) {
                continue;
            }

            ClassTeacher::create([
                'teacher_id' => $teacher->id,
                'institute_class_id' => $class->id,
                'section_id' => $sectionId,
                'academic_year_id' => $academicYearId,
            ]);

            $assignedTeacherIds->push($teacher->id);
        }
    }
}
