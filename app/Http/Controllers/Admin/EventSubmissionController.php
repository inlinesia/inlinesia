<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Notifications\EventDecisionNotification;
use App\Notifications\EventSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventSubmissionController extends Controller
{
    public function index(): View
    {
        $admin = request()->user();
        $admin->unreadNotifications()
            ->where('type', EventSubmittedNotification::class)
            ->get()
            ->each
            ->markAsRead();

        return view('admin.event-submissions.index', [
            'events' => Event::query()
                ->with('submitter')
                ->where('status', 'pending')
                ->whereNotNull('submitted_by')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function approve(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->status === 'pending' && $event->submitted_by !== null, 404);

        $query = Event::query()
            ->whereKey($event->id)
            ->where('status', 'pending')
            ->whereNotNull('submitted_by');

        $updated = $query->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_note' => null,
            'updated_at' => now(),
        ]);

        abort_unless($updated === 1, 409, 'Pengajuan sudah diproses admin lain.');

        $event->refresh()->submitter->notify(new EventDecisionNotification($event));

        return redirect()->route('admin.event-submissions.index')
            ->with('status', "Event “{$event->name}” disetujui dan ditampilkan pada daftar lomba.");
    }

    public function reject(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->status === 'pending' && $event->submitted_by !== null, 404);

        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:2000'],
        ]);

        $updated = Event::query()
            ->whereKey($event->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'decision_note' => $validated['decision_note'],
                'updated_at' => now(),
            ]);

        abort_unless($updated === 1, 409, 'Pengajuan sudah ditinjau admin lain.');
        $event->refresh()->submitter->notify(new EventDecisionNotification($event));

        return redirect()->route('admin.event-submissions.index')
            ->with('status', "Pengajuan “{$event->name}” ditolak. Pengaju akan menerima catatan Admin.");
    }
}
