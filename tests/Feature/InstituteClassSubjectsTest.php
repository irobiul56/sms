<?php

use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\User;

test('class subjects are queried through the class-subject pivot table', function () {
    $user = User::factory()->create();
    $institute = Institute::create(['user_id' => $user->id]);
    $class = InstituteClass::create([
        'institute_id' => $institute->id,
        'name' => 'Class 1',
        'numeric_value' => 1,
    ]);
    $subject = InstituteSubject::create([
        'institute_id' => $institute->id,
        'name' => 'Bangla',
        'code' => 'CLS-SUB-1',
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

    expect($class->subjects()->count())->toBe(1)
        ->and($class->subjects->first()->id)->toBe($subject->id)
        ->and($class->subjects->first()->pivot->full_marks)->toBe(100);
});
