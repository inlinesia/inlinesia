<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.profile', [
            'user' => $request->user(),
            'roles' => config('user-roles'),
            'submissions' => Event::query()->where('submitted_by', $request->user()->id)->latest()->get(),
            'notifications' => $request->user()->unreadNotifications()->latest()->limit(10)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'required_with:current_password', 'confirmed', Password::defaults()],
        ]);

        $attributes = collect($validated)->except('current_password');
        if (! $request->filled('password')) {
            $attributes->forget('password');
        }

        $request->user()->update($attributes->all());

        return redirect()->route('profile.show')->with('status', 'Profil berhasil diperbarui.');
    }
}
