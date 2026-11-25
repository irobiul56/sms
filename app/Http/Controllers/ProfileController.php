<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Institute;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $institute = $request->user()->institute;

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'institute' => [
                'type' => $institute?->type,
                'eiin' => $institute?->eiin,
                'name' => $institute?->name,
                'address' => $institute?->address,
                'logo_url' => $institute?->logo
                    ? Storage::disk('public')->url($institute->logo)
                    : null,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $logo = $request->file('logo');
        unset($validated['logo']);

        $user->fill([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'username' => $validated['username'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $institute = Institute::firstOrNew(['user_id' => $user->id]);
        $oldLogo = $institute->logo;
        $institute->fill([
            'type' => $validated['institute_type'],
            'eiin' => $validated['eiin'],
            'name' => $validated['institute_name'],
            'phone' => $validated['mobile'],
            'address' => $validated['address'],
        ]);

        if ($logo) {
            $logoPath = $logo->store('institute-logos', 'public');
            if (! $logoPath) {
                throw new \RuntimeException('The institute logo could not be saved.');
            }
            $institute->logo = $logoPath;
        }

        $institute->save();

        if ($logo && $oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
