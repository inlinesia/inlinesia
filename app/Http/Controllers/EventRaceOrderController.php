<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventRaceGroup;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman urutan lomba: susunan grup dan babak yang sudah dirilis panitia,
 * bisa dibuka seluruh peserta dan klub setelah masuk akun.
 *
 * Halaman ini hanya menampilkan urutan (siapa lawan siapa di babak mana)
 * beserta status grupnya — menunggu, sedang berlangsung, atau selesai.
 * Keputusan hasil lomba tetap ada di halaman hari lomba.
 */
class EventRaceOrderController extends Controller
{
    public function show(Event $event): View
    {
        $released = $event->raceSettings()
            ->whereNotNull('released_at')
            ->orderBy('category')
            ->orderBy('race')
            ->get();

        $groups = $event->raceGroups()
            ->with([
                'heats' => fn ($query) => $query->orderBy('number')
                    ->with('entries.registration:id,participant_name,club_name'),
            ])
            ->get()
            ->groupBy(fn (EventRaceGroup $group): string => $group->category.'|'.$group->race);

        $sections = $released
            ->map(function (EventCategoryRaceSetting $setting) use ($groups): ?array {
                $rows = $this->arrangedGroups(
                    $groups->get($setting->category.'|'.$setting->race, collect()),
                    $setting
                )->filter(fn (EventRaceGroup $group): bool => $group->heats->isNotEmpty());

                if ($rows->isEmpty()) {
                    return null;
                }

                return [
                    'setting' => $setting,
                    'category' => $setting->category,
                    'race' => $setting->race,
                    'groups' => $rows,
                    'running' => $rows->contains(fn (EventRaceGroup $group): bool => $group->status() === EventRaceGroup::RUNNING),
                ];
            })
            ->filter()
            ->values();

        return view('verified.events.race.order', [
            'event' => $event,
            'sections' => $sections,
        ]);
    }

    /**
     * @param  Collection<int, EventRaceGroup>  $groups
     * @return Collection<int, EventRaceGroup>
     */
    private function arrangedGroups(Collection $groups, EventCategoryRaceSetting $setting): Collection
    {
        $ladder = $setting->stageLadder();

        return $groups
            ->sortBy(fn (EventRaceGroup $group): array => [
                array_search($group->stage, $ladder, true) ?: count($ladder),
                (int) $group->number,
            ])
            ->values();
    }
}
