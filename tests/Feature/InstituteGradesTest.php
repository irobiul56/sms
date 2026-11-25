<?php

use App\Models\Grade;
use App\Models\Institute;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->institute = Institute::create(['user_id' => $this->user->id]);
    $this->user->update(['institute_id' => $this->institute->id]);
});

test('institutes can create update and delete their grade ranges', function () {
    $this->actingAs($this->user)
        ->post(route('grades.store'), [
            'name' => 'A+',
            'point' => 5,
            'marks_from' => 80,
            'marks_to' => 100,
            'remarks' => 'Excellent',
        ])
        ->assertRedirect(route('grades.index'));

    $grade = Grade::query()->where('institute_id', $this->institute->id)->firstOrFail();

    $this->actingAs($this->user)
        ->put(route('grades.update', $grade), [
            'name' => 'A',
            'point' => 4,
            'marks_from' => 75,
            'marks_to' => 79,
            'remarks' => 'Very good',
        ])
        ->assertRedirect(route('grades.index'));

    $this->assertDatabaseHas('grades', [
        'id' => $grade->id,
        'name' => 'A',
        'marks_from' => 75,
        'marks_to' => 79,
    ]);

    $this->actingAs($this->user)
        ->delete(route('grades.destroy', $grade))
        ->assertRedirect(route('grades.index'));

    $this->assertDatabaseMissing('grades', ['id' => $grade->id]);
});

test('grade ranges cannot overlap within the same institute', function () {
    Grade::create([
        'institute_id' => $this->institute->id,
        'name' => 'A+',
        'point' => 5,
        'marks_from' => 80,
        'marks_to' => 100,
    ]);

    $this->actingAs($this->user)
        ->post(route('grades.store'), [
            'name' => 'A',
            'point' => 4,
            'marks_from' => 70,
            'marks_to' => 85,
        ])
        ->assertSessionHasErrors('marks_from');

    $this->assertDatabaseCount('grades', 1);
});

test('institutes can add and calculate the standard grading scale', function () {
    $this->actingAs($this->user)
        ->post(route('grades.defaults'))
        ->assertRedirect(route('grades.index'));

    $this->assertDatabaseCount('grades', 7);
    $this->assertSame('A+', Grade::getGradeByMarks(85, $this->institute->id)?->name);
    $this->assertSame('5.00', Grade::getGpaByMarks(85, $this->institute->id));
});

test('institutes cannot edit another institute grade', function () {
    $otherUser = User::factory()->create();
    $otherInstitute = Institute::create(['user_id' => $otherUser->id]);
    $grade = Grade::create([
        'institute_id' => $otherInstitute->id,
        'name' => 'A+',
        'point' => 5,
        'marks_from' => 80,
        'marks_to' => 100,
    ]);

    $this->actingAs($this->user)
        ->put(route('grades.update', $grade), [
            'name' => 'Changed',
            'point' => 1,
            'marks_from' => 0,
            'marks_to' => 50,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('grades', ['id' => $grade->id, 'name' => 'A+']);
});
