<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventDetailsRequest;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicEventApplicationController extends Controller
{
    /**
     * Satu halaman untuk pengajuan event baru sekaligus penyuntingan event yang
     * sudah ada. Tanpa $event, form menampilkan mode pengajuan; dengan $event,
     * form menampilkan mode penyuntingan. Yang membedakan keduanya hanya aturan
     * peran: admin boleh menyunting event siapa pun, akun lain hanya miliknya.
     */
    public function form(Request $request, ?Event $event = null): View
    {
        if ($event !== null) {
            $this->authorizeOwnership($request, $event);
        }

        return view('verified.events.pengajuan.form', [
            'event' => $event,
            'isAdmin' => $request->user()->role === 'admin',
            // Form panjang dan fokus: sidebar admin disembunyikan agar isinya
            // mulai dari tepi kiri layar, sama seperti halaman pengaturan kategori.
            'hideSidebar' => true,
        ]);
    }

    public function store(EventDetailsRequest $request): RedirectResponse
    {
        $attributes = $request->eventAttributes();

        $duplicate = Event::query()
            ->where('submitted_by', $request->user()->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($attributes['name'])])
            ->whereRaw('LOWER(venue) = ?', [mb_strtolower($attributes['venue'])])
            ->whereDate('start_date', $attributes['start_date'])
            ->whereDate('end_date', $attributes['end_date'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($duplicate) {
            return redirect()->route('guest.dashboard')
                ->with('warning', 'Pengajuan dengan nama, tanggal, dan venue yang sama sudah tercatat. Tidak ada kejuaraan duplikat yang dibuat.');
        }

        $event = DB::transaction(fn () => Event::create([
            ...$attributes,
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
        ]));

        User::query()
            ->where('role', 'admin')
            ->where('id', '!=', $request->user()->id)
            ->each(fn (User $admin) => $admin->notify(new EventSubmittedNotification($event)));

        return redirect()->route('guest.dashboard')
            ->with('status', 'Pengajuan event berhasil dikirim dan menunggu persetujuan Admin.');
    }

    public function update(EventDetailsRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeOwnership($request, $event);

        $attributes = $request->eventAttributes();
        if ($event->status === 'rejected' && $request->user()->role !== 'admin') {
            $attributes += [
                'status' => 'pending',
                'decision_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
        }

        $event->update($attributes);

        if (! $event->wasChanged()) {
            return redirect()->route('guest.dashboard')
                ->with('status', 'Tidak ada perubahan yang perlu disimpan.');
        }

        if ($request->user()->role !== 'admin' && $event->status !== 'approved') {
            User::query()
                ->where('role', 'admin')
                ->each(fn (User $admin) => $admin->notify(new EventSubmittedNotification($event)));
        }

        $message = $request->user()->role === 'admin' || $event->status === 'approved'
            ? "Perubahan pada event “{$event->name}” berhasil disimpan."
            : "Perubahan pada pengajuan “{$event->name}” tersimpan dan dikirimkan ke Admin untuk ditinjau.";

        return redirect()->route('guest.dashboard')->with('status', $message);
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOwnership($request, $event);

        $eventName = $event->name;
        $event->delete();

        return redirect()->route('guest.dashboard')
            ->with('status', "Event “{$eventName}” dan seluruh data terkait berhasil dihapus.");
    }

    /**
     * Membuka atau menutup pendaftaran untuk seluruh event sekaligus.
     *
     * Penutupan di tingkat event berbeda dari penutupan satu kategori. Event
     * yang ditutup tidak bisa didaftarkan sama sekali, sedangkan kategori
     * yang hanya penuh tetap membolehkan pendaftar mengirim biodata supaya
     * data mereka tersimpan dan bisa dipakai pada event berikutnya.
     */
    public function toggleRegistration(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOwnership($request, $event);

        $open = $request->boolean('registration_open');
        $event->update(['registration_open' => $open]);

        return redirect()->route('guest.dashboard')->with('status', $open
            ? "Pendaftaran “{$event->name}” sudah dibuka kembali."
            : "Pendaftaran “{$event->name}” sudah ditutup.");
    }

    /**
     * Admins may manage all events; other accounts are limited to their submissions.
     */
    private function authorizeOwnership(Request $request, Event $event): void
    {
        abort_unless(
            $request->user()->role === 'admin' || $event->submitted_by === $request->user()->id,
            403,
            'Anda tidak memiliki akses untuk mengubah event ini.',
        );
    }
}
