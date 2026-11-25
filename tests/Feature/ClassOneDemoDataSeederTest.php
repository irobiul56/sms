<?php

use App\Models\AcademicYear;
use App\Models\ClassTeacher;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\StudentAcademicInfo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('class one demo seeder adds five students teachers and required subjects idempotently', function () {
    $user = \App\Models\User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $class = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Class 1',
        'numeric_value' => 1,
    ]);
    $year = AcademicYear::create([
        'institute_id' => $institute->id,
        'name' => '2026-2027',
        'year' => 2026,
        'session' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);
    $section = Section::create([
        'institute_id' => $institute->id,
        'institute_class_id' => $class->id,
        'name' => 'A',
    ]);

    Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\ClassOneDemoDataSeeder',
        '--no-interaction' => true,
    ]);

    expect(StudentAcademicInfo::query()
        ->where('institute_class_id', $class->id)
        ->where('academic_year_id', $year->id)
        ->count())->toBe(5);
    expect(ClassTeacher::query()
        ->where('institute_class_id', $class->id)
        ->where('academic_year_id', $year->id)
        ->distinct('teacher_id')
        ->count('teacher_id'))->toBe(5);
    expect(DB::table('institute_class_institute_subjects')
        ->where('institute_class_id', $class->id)
        ->count())->toBe(3);

    Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\ClassOneDemoDataSeeder',
        '--no-interaction' => true,
    ]);

    expect(StudentAcademicInfo::query()
        ->where('institute_class_id', $class->id)
        ->where('academic_year_id', $year->id)
        ->count())->toBe(5);
    expect(ClassTeacher::query()
        ->where('institute_class_id', $class->id)
        ->where('academic_year_id', $year->id)
        ->distinct('teacher_id')
        ->count('teacher_id'))->toBe(5);
    expect($section->refresh())->not->toBeNull();
});
