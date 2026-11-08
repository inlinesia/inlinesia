<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventOwnershipController extends Controller
{
    public function update(Request $request, Event $event): RedirectResponse
    {
        $isUnassignedEvent = $event->submitted_by === null;
        $isAdminOwnedEvent = $event->submitter()
            ->where('role', 'admin')
            ->exists();

        abort_unless($isUnassignedEvent || $isAdminOwnedEvent, 404);

        $validated = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newOwner = User::query()->findOrFail($validated['owner_id']);

        if ($newOwner->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'owner_id' => 'Pilih akun lain untuk menerima kepemilikan event.',
            ]);
        }

        if ($newOwner->role !== 'admin' && ! $newOwner->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'owner_id' => 'Kepemilikan hanya dapat dialihkan kepada akun yang sudah memverifikasi email.',
            ]);
        }

        $event->update(['submitted_by' => $newOwner->id]);

        return redirect()->route('guest.dashboard')
            ->with('status', "Kepemilikan event “{$event->name}” dialihkan kepada {$newOwner->name}.");
    }
}
