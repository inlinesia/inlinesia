<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventRaceEntry;
use App\Models\EventRaceHeat;
use App\Models\EventRegistration;

/**
 * Papan hasil satu kategori.
 *
 * Dua mode penilaian menghasilkan cara mengurutkan yang berbeda:
 * - points: jumlah poin dari setiap finis; makin besar makin baik.
 * - qualification: jumlah poin posisi; makin kecil makin baik, jadi atlet
 *   yang selalu finis di depan menang meski tidak pernah menang babak.
 */
class RaceStandings
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forCategory(Event $event, EventCategoryRaceSetting $setting): array
    {
        $entries = $event->raceEntries()
            ->where('category', $setting->category)
            ->where('race', $setting->race)
            ->whereHas('registration', function ($query): void {
                $query->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', '!=', 'approved');
            })
            ->with(['registration:id,event_id,participant_name,nickname,club_name,category', 'heat:id,stage,number,label'])
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $grouped = $entries->groupBy('event_registration_id');

        $decidingStage = $setting->decidingStage();
        $ladder = $setting->stageLadder();

        $rows = [];

        foreach ($grouped as $registrationId => $registrationEntries) {
            /** @var EventRaceEntry|null $first */
            $first = $registrationEntries->first();
            $registration = $first?->registration;

            if (! $registration instanceof EventRegistration) {
                continue;
            }

            $finished = $registrationEntries->filter(
                fn (EventRaceEntry $entry): bool => $entry->status === EventRaceEntry::STATUS_FINISHED
            );

            $places = $finished->pluck('place')->filter(fn ($place): bool => $place !== null)->map(fn ($place): int => (int) $place);

            $rows[] = [
                'registration' => $registration,
                'name' => $registration->participant_name,
                'club' => trim((string) $registration->club_name) ?: 'Tanpa klub',
                'heats_entered' => $registrationEntries->count(),
                'heats_finished' => $finished->count(),
                'wins' => $places->filter(fn (int $place): bool => $place === 1)->count(),
                'podiums' => $places->filter(fn (int $place): bool => $place <= 3)->count(),
                'best_place' => $places->min(),
                'place_points' => (int) $places->sum(),
                'points' => (int) $registrationEntries->sum('points'),
                'no_shows' => $registrationEntries->where('status', EventRaceEntry::STATUS_NO_SHOW)->count(),
                'dnfs' => $registrationEntries->whereIn('status', [EventRaceEntry::STATUS_DNF, EventRaceEntry::STATUS_DSQ])->count(),
                'qualified' => $registrationEntries->contains('is_qualified', true),
                // Pada format "langsung menang tanpa final" juaranya diumumkan
                // per babak, jadi babak grup-lah yang menentukan.
                'is_champion' => $registrationEntries->contains(
                    fn (EventRaceEntry $entry): bool => $entry->heat?->stage === $decidingStage
                        && $entry->place !== null
                        && $entry->place <= $setting->champion_count
                ),
                'entries' => $registrationEntries->sortBy(fn (EventRaceEntry $entry): array => [
                    array_search($entry->heat?->stage, $ladder, true) ?: 0,
                    (int) $entry->heat?->number,
                ])->values(),
            ];
        }

        return $this->rank($rows, $setting);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rank(array $rows, EventCategoryRaceSetting $setting): array
    {
        usort($rows, function (array $a, array $b) use ($setting): int {
            if ($setting->usesPoints()) {
                $byPoints = $b['points'] <=> $a['points'];
                if ($byPoints !== 0) {
                    return $byPoints;
                }
            }

            $byPlaces = $a['place_points'] <=> $b['place_points'];
            if ($byPlaces !== 0) {
                return $byPlaces;
            }

            $byWins = $b['wins'] <=> $a['wins'];
            if ($byWins !== 0) {
                return $byWins;
            }

            $byBest = ($a['best_place'] ?? 99) <=> ($b['best_place'] ?? 99);
            if ($byBest !== 0) {
                return $byBest;
            }

            return $a['place_points'] <=> $b['place_points'];
        });

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
            $row['score'] = $setting->usesPoints()
                ? $row['points']
                : $row['place_points'];
        }

        return $rows;
    }

    /**
     * Berapa babak pada satu tahap yang sudah selesai diisi.
     * Tanpa ini, tombol "lanjut ke tahap berikutnya" bisa muncul padahal
     * masih ada babak yang belum diisi hasil.
     *
     * @return array{total:int, done:int, complete:bool}
     */
    public function stageProgress(Event $event, string $category, string $race, string $stage): array
    {
        $heats = $event->raceHeats()
            ->where('category', $category)
            ->where('race', $race)
            ->where('stage', $stage)
            ->with('entries')
            ->get();

        $done = $heats->filter(function (EventRaceHeat $heat): bool {
            $entries = $heat->entries;
            if ($entries->isEmpty()) {
                return false;
            }

            // Babak dianggap selesai kalau tidak ada satu pun lane yang
            // masih menunggu hasil, termasuk atlet yang ditunda panitia.
            return $entries->every(fn (EventRaceEntry $entry): bool => ! $entry->isAwaitingRace());
        })->count();

        return [
            'total' => $heats->count(),
            'done' => $done,
            'complete' => $heats->isNotEmpty() && $done === $heats->count(),
        ];
    }
}
