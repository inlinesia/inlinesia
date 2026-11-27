<?php

namespace App\Http\Controllers;

use App\Http\Requests\SponsorEventRequest;
use App\Http\Requests\SponsorWebRequest;
use App\Models\Event;
use App\Models\Sponsor;
use App\Models\User;
use App\Notifications\NewSponsorRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class SponsorController extends Controller
{
    public function webForm(): View
    {
        return view('publik.sponsor.web');
    }

    public function webStore(SponsorWebRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $sponsor = new Sponsor([
            'type' => Sponsor::TYPE_WEB,
            'package' => $validated['package'],
            'brand_name' => $validated['brand_name'],
            'contact_name' => $validated['contact_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'website_url' => $validated['website_url'],
            'message' => $validated['message'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => Sponsor::STATUS_PENDING,
        ]);
        $sponsor->logo_path = $request->file('logo')->store('sponsors', 'public');
        $sponsor->amount = $this->webAmount($sponsor);
        $sponsor->save();

        $this->notifyReviewers($sponsor);

        return redirect()->route('sponsor.web.form')
            ->with('status', 'Pengajuan sponsor website diterima. Admin akan meninjau dan menghubungi Anda lewat WhatsApp.');
    }

    public function eventForm(): View
    {
        $requestedEventId = request()->has('event') ? (int) request()->query('event') : null;
        $events = Event::query()
            ->where('status', 'approved')
            ->orderBy('start_date')
            ->get(['id', 'name']);

        return view('publik.sponsor.event', [
            'events' => $events,
            'selectedEventId' => $requestedEventId,
        ]);
    }

    public function eventStore(SponsorEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $event = Event::query()->findOrFail($validated['event_id']);
        abort_unless($event->status === 'approved', 409, 'Event ini belum tersedia untuk sponsor.');

        $sponsor = new Sponsor([
            'type' => Sponsor::TYPE_EVENT,
            'package' => $validated['package'],
            'event_id' => $event->id,
            'brand_name' => $validated['brand_name'],
            'contact_name' => $validated['contact_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'website_url' => $validated['website_url'],
            'message' => $validated['message'] ?? null,
            'status' => Sponsor::STATUS_PENDING,
        ]);
        $sponsor->logo_path = $request->file('logo')->store('sponsors', 'public');
        $sponsor->amount = Sponsor::PRICES[$sponsor->package];
        $sponsor->save();

        $this->notifyReviewers($sponsor);

        return redirect()->route('sponsor.event.form', ['event' => $event->id])
            ->with('status', 'Pengajuan sponsor event diterima. Panitia akan meninjau dan menghubungi Anda.');
    }

    private function webAmount(Sponsor $sponsor): int
    {
        $months = max(1, (int) $sponsor->start_date->diffInMonths($sponsor->end_date) + 1);

        return (Sponsor::PRICES[$sponsor->package] ?? 0) * $months;
    }

    private function notifyReviewers(Sponsor $sponsor): void
    {
        /** @var Collection<int, User> $recipients */
        $recipients = User::query()->where('role', 'admin')->get();

        if ($sponsor->type === Sponsor::TYPE_EVENT && $sponsor->event?->submitted_by !== null) {
            $owner = User::query()->find($sponsor->event->submitted_by);
            if ($owner !== null) {
                $recipients = $recipients->push($owner)->unique('id');
            }
        }

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new NewSponsorRequestNotification($sponsor));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
