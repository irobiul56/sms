<?php

use App\Models\AcademicYear;
use App\Models\InstituteClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'institute_type' => 'school',
        'eiin' => '123456',
        'institute_name' => 'Test Institute',
        'address' => 'Dhaka, Bangladesh',
        'name' => 'Test User',
        'mobile' => '01700000000',
        'email' => 'test@example.com',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('institute.dashboard'));
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'username' => 'testuser',
        'mobile' => '01700000000',
        'usertype' => 'user',
    ]);
    $this->assertDatabaseHas('institutes', [
        'user_id' => User::where('email', 'test@example.com')->value('id'),
        'type' => 'school',
        'eiin' => '123456',
        'name' => 'Test Institute',
        'phone' => '01700000000',
        'address' => 'Dhaka, Bangladesh',
    ]);
    $instituteId = User::where('email', 'test@example.com')->value('institute_id');

    $classes = InstituteClass::where('institute_id', $instituteId)->orderBy('numeric_value')->get();

    expect($classes)->toHaveCount(10)
        ->and($classes->first()->numeric_value)->toBe(1)
        ->and($classes->last()->numeric_value)->toBe(10)
        ->and(Section::where('institute_id', $instituteId)->count())->toBe(10)
        ->and(Section::where('institute_id', $instituteId)
            ->whereIn('institute_class_id', $classes->pluck('id'))
            ->where('name', 'A')
            ->count())->toBe(10)
        ->and(AcademicYear::where('institute_id', $instituteId)->count())->toBe(1)
        ->and(AcademicYear::where('institute_id', $instituteId)->where('is_current', true)->count())->toBe(1)
        ->and(DB::table('institute_subjects')->where('institute_id', $instituteId)->count())->toBe(0);
    $this->assertDatabaseHas('sections', [
        'institute_id' => $instituteId,
        'name' => 'A',
    ]);
    expect(DB::table('institute_class_institute_subjects')
        ->whereIn('institute_class_id', $classes->pluck('id'))
        ->count())->toBe(0);
});

test('college and madrasa registrations create their specified class ranges', function (
    string $type,
    int $firstClass,
    int $lastClass
) {
    $this->post('/register', [
        'institute_type' => $type,
        'eiin' => $type === 'college' ? '123457' : '123458',
        'institute_name' => ucfirst($type).' Institute',
        'address' => 'Dhaka, Bangladesh',
        'name' => 'Test User',
        'mobile' => '01700000000',
        'email' => "{$type}@example.com",
        'username' => "{$type}user",
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('institute.dashboard'));

    $instituteId = User::where('email', "{$type}@example.com")->value('institute_id');
    $classes = InstituteClass::where('institute_id', $instituteId)->orderBy('numeric_value')->get();

    expect($classes)->toHaveCount($lastClass - $firstClass + 1)
        ->and($classes->first()->numeric_value)->toBe($firstClass)
        ->and($classes->last()->numeric_value)->toBe($lastClass)
        ->and(Section::where('institute_id', $instituteId)->where('name', 'A')->count())->toBe($lastClass - $firstClass + 1)
        ->and(AcademicYear::where('institute_id', $instituteId)->count())->toBe(1)
        ->and(DB::table('institute_subjects')->where('institute_id', $instituteId)->count())->toBe(0)
        ->and(DB::table('institute_class_institute_subjects')
        ->whereIn('institute_class_id', $classes->pluck('id'))
        ->count())->toBe(0);
})->with([
    'college' => ['college', 11, 12],
    'madrasa' => ['madrasa', 1, 12],
]);

test('registration requires complete institute and account details', function () {
    $this->from('/register')
        ->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect('/register')
        ->assertSessionHasErrors([
            'institute_type',
            'eiin',
            'institute_name',
            'address',
            'mobile',
            'username',
        ]);
});

test('users can update institute and account information from settings', function () {
    $user = User::factory()->create([
        'name' => 'Old User',
        'email' => 'old@example.com',
        'username' => 'olduser',
        'mobile' => '01700000000',
    ]);

    $institute = \App\Models\Institute::create([
        'user_id' => $user->id,
        'type' => 'school',
        'eiin' => '123456',
        'name' => 'Old Institute',
        'address' => 'Old address',
    ]);
    $user->update(['institute_id' => $institute->id]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Updated User',
            'mobile' => '01800000000',
            'username' => 'updateduser',
            'email' => 'updated@example.com',
            'institute_type' => 'college',
            'eiin' => '654321',
            'institute_name' => 'Updated Institute',
            'address' => 'Updated address',
        ])
        ->assertRedirect(route('profile.edit'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated User',
        'mobile' => '01800000000',
        'username' => 'updateduser',
        'email' => 'updated@example.com',
        'email_verified_at' => null,
    ]);
    $this->assertDatabaseHas('institutes', [
        'id' => $institute->id,
        'type' => 'college',
        'eiin' => '654321',
        'name' => 'Updated Institute',
        'phone' => '01800000000',
        'address' => 'Updated address',
    ]);
});

test('users can update their institute logo from settings', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $institute = \App\Models\Institute::create([
        'user_id' => $user->id,
        'type' => 'school',
        'eiin' => '123456',
        'name' => 'Test Institute',
        'address' => 'Dhaka',
    ]);
    $user->update(['institute_id' => $institute->id]);

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'patch',
            'name' => $user->name,
            'mobile' => '01700000000',
            'username' => 'testuser',
            'email' => $user->email,
            'institute_type' => 'school',
            'eiin' => '123456',
            'institute_name' => 'Test Institute',
            'address' => 'Dhaka',
            'logo' => UploadedFile::fake()->createWithContent(
                'logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r1sAAAAASUVORK5CYII=')
            ),
        ])
        ->assertRedirect(route('profile.edit'));

    $institute->refresh();
    expect($institute->logo)->not->toBeNull();
    Storage::disk('public')->assertExists($institute->logo);
});

test('registered users cannot access the super admin dashboard', function () {
    $user = User::factory()->create(['usertype' => 'user']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('institute.dashboard'));

    $this->actingAs($user)
        ->get('/settings/question')
        ->assertRedirect(route('institute.dashboard'));
});

test('users without a recognized role cannot access the super admin dashboard', function () {
    $user = User::factory()->create(['usertype' => '']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('super admins can access the super admin dashboard', function () {
    $admin = User::factory()->create(['usertype' => 'admin']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();
});
