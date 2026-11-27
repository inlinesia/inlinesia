<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventDecisionNotification;
use App\Services\CategoryFeeCalculator;
use App\Services\ResolveEventRegistrationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GuestDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $registrations = EventRegistration::query()
            ->with(['event', 'financialEntries'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return view('verified.dashboard', [
            'user' => $user,
            'isAdmin' => $isAdmin,
            'events' => Event::query()
                ->where('status', 'approved')
                ->whereDate('end_date', '>=', today())
                ->orderBy('start_date')
                ->get(),
            'registrations' => $registrations,
            'ownedEvents' => Event::query()
                ->with('submitter:id,name,email,role')
                ->withCount(['registrations' => fn ($query) => $query
                    ->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', '!=', 'approved')])
                ->when(! $isAdmin, fn ($query) => $query->where('submitted_by', $user->id))
                ->orderBy('start_date')
                ->get(),
            'transferableEventOwners' => $isAdmin
                ? User::query()
                    ->where('id', '!=', $user->id)
                    ->where(function ($query): void {
                        $query->whereNotNull('email_verified_at')
                            ->orWhere('role', 'admin');
                    })
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'role'])
                : collect(),
            'submissions' => Event::query()
                ->when(! $isAdmin, fn ($query) => $query->where('submitted_by', $user->id))
                ->where('status', '!=', 'approved')
                ->latest()
                ->get(),
            'decisionNotifications' => $request->user()->notifications()
                ->where('type', EventDecisionNotification::class)
                ->latest()
                ->limit(10)
                ->get(),
            'cancellationRequests' => $this->pendingCancellations($request),
            'runningWaves' => $this->runningWaveInfo($registrations),
        ]);
    }

    /**
     * Permintaan pembatalan yang masih menunggu keputusan pemilik event.
     * Admin melihat semuanya; pengelola hanya event miliknya.
     *
     * @return Collection<int, array{registration: EventRegistration, event: Event, reason: string}>
     */
    private function pendingCancellations(Request $request): Collection
    {
        $user = $request->user();

        return EventRegistration::query()
            ->with(['event', 'user'])
            ->where('cancellation_status', 'pending')
            ->whereHas('event', fn ($query) => $query->when(
                $user->role !== 'admin',
                fn ($eventQuery) => $eventQuery->where('submitted_by', $user->id),
            ))
            ->latest('cancellation_requested_at')
            ->limit(20)
            ->get()
            ->map(fn (EventRegistration $registration): array => [
                'registration' => $registration,
                'event' => $registration->event,
                'reason' => (string) $registration->cancellation_reason,
            ]);
    }

    /**
     * Gelombang tarif yang sedang berjalan untuk tiap pendaftaran milik peserta.
     *
     * Aturannya diambil dari kategori yang sudah ditetapkan. Kalau kategori belum
     * ditetapkan, dipakai kategori pertama yang sesuai tahun lahir dan gender
     * atlet supaya peserta tetap melihat harga yang sedang berlaku.
     *
     * @param  Collection<int, EventRegistration>  $registrations
     * @return array<int, array{category: string, label: ?string, range: ?string, price: int}>
     */
    private function runningWaveInfo($registrations): array
    {
        $calculator = app(CategoryFeeCalculator::class);
        $resolver = app(ResolveEventRegistrationCategory::class);
        $today = now();
        $info = [];

        foreach ($registrations as $registration) {
            $event = $registration->event;

            if (! $event) {
                continue;
            }

            $rule = $calculator->ruleByName($event, $registration->category);

            if (! $rule && $registration->date_of_birth && $registration->gender) {
                $rule = $resolver->eligibleCategories(
                    $event,
                    $registration->date_of_birth->format('Y-m-d'),
                    $registration->gender,
                )->first();
            }

            if (! $rule) {
                continue;
            }

            $pricing = $calculator->pricing($rule, $today);
            $wave = $pricing['pricing_wave'] ?? null;

            $info[$registration->id] = [
                'category' => (string) ($rule['name'] ?? ''),
                'label' => $wave ? ($wave['label'] ?: null) : null,
                'range' => $wave ? $calculator->waveRangeLabel($wave) : null,
                'price' => (int) $pricing['package_fee'],
            ];
        }

        return $info;
    }
}
