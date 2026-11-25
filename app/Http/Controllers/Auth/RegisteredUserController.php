<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\User;
use App\Services\InstituteRegistrationSetup;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'institute_type' => ['required', 'string', 'in:school,college,madrasa,technical,university,other'],
            'eiin' => ['required', 'digits:6', 'unique:institutes,eiin'],
            'institute_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash:ascii', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'mobile' => $validated['mobile'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'usertype' => 'user',
            ]);

            $institute = Institute::create([
                'user_id' => $user->id,
                'type' => $validated['institute_type'],
                'eiin' => $validated['eiin'],
                'name' => $validated['institute_name'],
                'phone' => $validated['mobile'],
                'address' => $validated['address'],
            ]);

            app(InstituteRegistrationSetup::class)->createDefaults($institute);

            $user->update(['institute_id' => $institute->id]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('institute.dashboard');
    }
}
