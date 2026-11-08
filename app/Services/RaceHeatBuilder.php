<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventRaceEntry;
use App\Models\EventRaceGroup;
use App\Models\EventRaceHeat;
use App\Models\EventRegistration;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penyusun urutan lomba.
 *
 * Susunannya berlapis: tahap (grup awal → penyisihan → semifinal → final),
 * lalu grup di dalam tiap tahap, lalu babak (race) di dalam tiap grup.
 * Grup adalah satuan yang disiapkan panitia pada hari lomba dan yang
 * dilihat peserta sebelum hari lomba.
 */
class RaceHeatBuilder
{
    /**
     * Peserta yang layak masuk babak heats: sudah diterima panitia, sudah
     * membayar (kecuali event gratis), dan masuk kategori sekaligus mata
     * lomba yang diminta.
     *
     * @return Collection<int, EventRegistration>
     */
    public function entrantsFor(Event $event, string $category, string $race): Collection
    {
        return $event->registrations()
            ->where('category', $category)
            ->whereJsonContains('races', $race)
            ->where('status', 'approved')
            // Pendaftaran yang belum lunas tidak masuk babak, kecuali
            // eventnya gratis sehingga tidak ada kewajiban bayar sama sekali.
            ->when(! $event->is_free, fn ($query) => $query->where('payment_status', 'paid'))
            ->where(function ($query): void {
                $query->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', '!=', 'approved');
            })
            ->orderByRaw("COALESCE(NULLIF(TRIM(club_name), ''), 'zzz')")
            ->orderBy('participant_name')
            ->get();
    }

    /**
     * Susun urutan lomba seluruh tahap untuk satu kategori dan mata lomba.
     *
     * Tahap pertama langsung diisi nama peserta. Tahap berikutnya dibuat
     * sebagai grup kosong sebagai penanda rencana, isinya baru diisi setelah
     * hasil tahap sebelumnya diketahui.
     *
     * Aturan lawan:
     * - same_club: setiap babak diisi satu klub saja, jadi klub yang sama
     *   tidak saling bertanding di babak awal.
     * - mixed: klub sebanyak mungkin tersebar merata, tanpa syarat lain.
     * - cross_club: seperti mixed, tetapi setiap babak harus punya minimal
     *   dua klub; kalau tidak bisa dipenuhi, dikembalikan apa adanya
     *   beserta catatan untuk panitia.
     *
     * @return array{created:int, heats:int, groups:int, warnings:string[]}
     */
    public function buildHeats(Event $event, EventCategoryRaceSetting $setting): array
    {
        $entrants = $this->entrantsFor($event, $setting->category, $setting->race);

        if ($entrants->isEmpty()) {
            return ['created' => 0, 'heats' => 0, 'groups' => 0, 'warnings' => ['Belum ada peserta yang diterima pada kategori dan mata lomba ini.']];
        }

        $perHeat = max(1, $setting->effectiveRunnersPerHeat($entrants->count()));
        $perGroup = max($perHeat, $setting->effectiveAthletesPerGroup($entrants->count()));

        return DB::transaction(function () use ($event, $setting, $entrants, $perHeat, $perGroup): array {
            if ($this->heatsQuery($event, $setting)->with('entries')->get()->contains(fn (EventRaceHeat $heat): bool => $heat->hasResults())) {
                return ['created' => 0, 'heats' => 0, 'groups' => 0, 'warnings' => ['Ada hasil yang sudah tersimpan. Reset susunan dulu sebelum menyusun ulang.']];
            }

            $this->resetArrangement($event, $setting);

            $warnings = [];
            $batches = $setting->opponent_scope === 'same_club'
                ? $this->byClub($entrants, $warnings, $perHeat)
                : $this->balanced($entrants, $perHeat, $setting->opponent_scope === 'cross_club', $warnings);

            $created = 0;
            $heatNumber = 0;
            $groupNumber = 0;

            foreach ($this->chunkRacesIntoGroups($batches, $perGroup) as $heatBatch) {
                $group = EventRaceGroup::create([
                    'event_id' => $event->id,
                    'category' => $setting->category,
                    'race' => $setting->race,
                    'stage' => 'heat',
                    'number' => ++$groupNumber,
                ]);

                foreach ($heatBatch as $rows) {
                    $heat = EventRaceHeat::create([
                        'event_id' => $event->id,
                        'event_race_group_id' => $group->id,
                        'category' => $setting->category,
                        'race' => $setting->race,
                        'stage' => 'heat',
                        'number' => ++$heatNumber,
                        'label' => 'Babak '.$heatNumber,
                        'status' => 'draft',
                        'lanes_count' => count($rows),
                    ]);

                    foreach (array_values($rows) as $index => $registration) {
                        EventRaceEntry::create([
                            'event_race_heat_id' => $heat->id,
                            'event_id' => $event->id,
                            'event_registration_id' => $registration->id,
                            'category' => $setting->category,
                            'race' => $setting->race,
                            'lane' => $index + 1,
                            'status' => EventRaceEntry::STATUS_PENDING,
                        ]);
                        $created++;
                    }
                }
            }

            // Tahap berikutnya belum bisa diisi nama karena belum tahu siapa
            // yang lolos. Grup kosongnya tetap dibuat supaya peserta bisa
            // melihat rencana tangga babaknya sebelum hari lomba.
            foreach (array_slice($setting->stageLadder(), 1) as $stage) {
                EventRaceGroup::create([
                    'event_id' => $event->id,
                    'category' => $setting->category,
                    'race' => $setting->race,
                    'stage' => $stage,
                    'number' => 1,
                ]);
            }

            return ['created' => $created, 'heats' => $heatNumber, 'groups' => $groupNumber, 'warnings' => $warnings];
        });
    }

    /**
     * Hapus seluruh susunan (grup, babak, dan baris peserta) satu kategori
     * sekaligus mata lomba beserta hasilnya.
     */
    public function resetArrangement(Event $event, EventCategoryRaceSetting $setting): void
    {
        $heatIds = $this->heatsQuery($event, $setting)->pluck('id');

        EventRaceEntry::whereIn('event_race_heat_id', $heatIds)->delete();
        EventRaceHeat::whereIn('id', $heatIds)->delete();
        $this->groupsQuery($event, $setting)->delete();
    }

    public function hasResults(Event $event, EventCategoryRaceSetting $setting): bool
    {
        return $this->heatsQuery($event, $setting)->with('entries')->get()
            ->contains(fn (EventRaceHeat $heat): bool => $heat->hasResults());
    }

    /**
     * Bentuk babak berikutnya pada tangga format: babak penyisihan,
     * semifinal, atau final.
     *
     * Peserta yang lolos diambil dari entry berstatus finished pada tahap
     * sebelumnya, diurutkan memakai aturan penilaian kategori. Kalau kurang
     * dari dua orang, tahap tidak dibuat.
     *
     * @return array{created:int, heats:int, warnings:string[]}
     */
    public function advance(Event $event, EventCategoryRaceSetting $setting, string $targetStage): array
    {
        $previousStage = $setting->stageBefore($targetStage);

        if ($previousStage === null) {
            return ['created' => 0, 'heats' => 0, 'warnings' => ['Format tahap yang dipilih tidak punya babak sebelum '.$targetStage.'.']];
        }

        $finishers = $this->finishers($event, $setting->category, $setting->race, $previousStage);

        if (count($finishers) < 2) {
            return ['created' => 0, 'heats' => 0, 'warnings' => ['Belum cukup peserta yang finis untuk menyusun tahap berikutnya.']];
        }

        $fieldSize = $this->fieldSizeFor($event, $setting, $targetStage);

        if ($fieldSize < 2) {
            return ['created' => 0, 'heats' => 0, 'warnings' => ['Jumlah peserta untuk tahap ini minimal dua orang. Atur jumlahnya di pengaturan perlombaan.']];
        }

        $qualified = array_slice(array_values($finishers), 0, $fieldSize);
        $perHeat = max(1, $setting->effectiveRunnersPerHeat(count($qualified)));

        if ($setting->opponent_scope === 'same_club') {
            // Satu klub per babak: pecah berdasarkan klub supaya tidak ada
            // dua orang dari klub yang sama berada di satu babak.
            $byClub = [];
            foreach ($qualified as $registration) {
                $byClub[$this->clubKey($registration)][] = $registration;
            }
            $groups = array_values(array_map(fn ($rows) => array_slice($rows, 0, $perHeat), array_values($byClub)));
        } else {
            $groups = array_chunk($qualified, max(1, min($perHeat, $fieldSize)));
        }

        $created = 0;
        $number = 0;

        DB::transaction(function () use ($event, $setting, $targetStage, $groups, &$created, &$number): void {
            $this->heatsQuery($event, $setting)
                ->where('stage', $targetStage)
                ->delete();

            // Grup penanda untuk tahap ini sudah dibuat saat urutan lomba
            // disusun; kalau belum ada (data lama), buat sekarang.
            $group = $this->groupsQuery($event, $setting)
                ->where('stage', $targetStage)
                ->orderBy('number')
                ->first()
                ?? EventRaceGroup::create([
                    'event_id' => $event->id,
                    'category' => $setting->category,
                    'race' => $setting->race,
                    'stage' => $targetStage,
                    'number' => 1,
                ]);

            $stageLabel = EventRaceHeat::STAGES[$targetStage] ?? ucfirst($targetStage);

            foreach ($groups as $rows) {
                $number++;

                $heat = EventRaceHeat::create([
                    'event_id' => $event->id,
                    'event_race_group_id' => $group->id,
                    'category' => $setting->category,
                    'race' => $setting->race,
                    'stage' => $targetStage,
                    'number' => $number,
                    'label' => $stageLabel.' '.$number,
                    'status' => 'draft',
                    'lanes_count' => count($rows),
                ]);

                foreach (array_values($rows) as $index => $registration) {
                    EventRaceEntry::create([
                        'event_race_heat_id' => $heat->id,
                        'event_id' => $event->id,
                        'event_registration_id' => $registration->id,
                        'category' => $setting->category,
                        'race' => $setting->race,
                        'lane' => $index + 1,
                        'status' => EventRaceEntry::STATUS_PENDING,
                    ]);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'heats' => $number, 'warnings' => []];
    }

    /**
     * Tunda satu atlet: dia keluar dari gilirannya dan dipindahkan ke slot
     * paling akhir, sementara seluruh atlet di belakangnya maju satu per satu
     * sampai slot yang kosong tadi tertutup.
     *
     * Antreannya hanya sebatas satu kategori dan satu mata lomba, jadi tidak
     * pernah melewati kategori atau mata lomba lain. Karena urutan sudah
     * diputar, atlet yang didorong ke belakang selalu mendarat di grup
     * paling akhir yang masih punya antrean.
     *
     * @return array{moved:bool, message:string}
     */
    public function postpone(Event $event, EventRaceEntry $entry): array
    {
        $heat = $entry->heat;

        if (! $heat instanceof EventRaceHeat) {
            return ['moved' => false, 'message' => 'Atlet ini tidak terikat pada babak mana pun.'];
        }

        return DB::transaction(function () use ($event, $entry, $heat): array {
            $queue = $event->raceEntries()
                ->where('category', $entry->category)
                ->where('race', $entry->race)
                ->whereIn('status', [EventRaceEntry::STATUS_PENDING, EventRaceEntry::STATUS_POSTPONED])
                ->whereHas('heat', fn ($query) => $query->where('stage', $heat->stage))
                ->with(['heat:id,event_race_group_id,stage,number', 'heat.group:id,number'])
                ->get()
                ->sortBy(fn (EventRaceEntry $row): array => [
                    (int) ($row->heat?->group?->number ?? 0),
                    (int) ($row->heat?->number ?? 0),
                    (int) ($row->lane ?? 0),
                ])
                ->values();

            $index = $queue->search(fn (EventRaceEntry $row): bool => $row->id === $entry->id);

            if ($index === false) {
                return ['moved' => false, 'message' => 'Atlet ini sudah memperoleh keputusan sehingga tidak bisa didunda.'];
            }

            $slots = $queue->map(fn (EventRaceEntry $row): array => [
                'heat_id' => $row->event_race_heat_id,
                'lane' => (int) $row->lane,
            ])->all();

            // Sisipkan kembali atlet yang didunda di posisi paling akhir;
            // seluruh atlet sesudah dia bergeser satu slot ke depan.
            $order = $queue->values();
            $order->splice((int) $index, 1);
            $order->push($entry);

            $touched = [];
            $moves = [];

            // Kunci unik (babak, lane) membuat perpindahan tidak boleh
            // menabrak slot yang masih ditempati. Semua atlet yang berpindah
            // diparkir dulu ke lane cadangan, baru setelah itu menempati
            // lane barunya masing-masing.
            foreach ($order as $position => $row) {
                $slot = $slots[$position];
                $originalHeatId = $row->event_race_heat_id;
                $changed = $originalHeatId !== $slot['heat_id'] || (int) $row->lane !== $slot['lane'];

                $touched[$originalHeatId] = true;
                $touched[$slot['heat_id']] = true;

                if (! $changed) {
                    continue;
                }

                $moves[] = ['row' => $row, 'lane' => $slot['lane']];
                $row->event_race_heat_id = $slot['heat_id'];
                $row->lane = 1000 + $position;
                $row->save();
            }

            foreach ($moves as $move) {
                $move['row']->lane = $move['lane'];
                $move['row']->save();
            }

            $entry->status = EventRaceEntry::STATUS_POSTPONED;
            $entry->place = null;
            $entry->time_ms = null;
            $entry->points = 0;
            $entry->is_qualified = false;
            $entry->note = $entry->note ?: 'Ditunda panitia';
            $entry->save();

            foreach (array_keys($touched) as $heatId) {
                $this->syncHeatStatus(EventRaceHeat::find($heatId));
            }

            return [
                'moved' => true,
                'message' => 'Atlet dipindahkan ke urutan paling akhir; atlet lain di antrean maju satu per satu.',
            ];
        });
    }

    /**
     * Perbarui status babak dari sisa lane yang belum menghasilkan keputusan.
     * Lane berstatus ditunda masih dihitung belum selesai karena atletnya
     * tetap harus balapan.
     */
    public function syncHeatStatus(?EventRaceHeat $heat): void
    {
        if (! $heat instanceof EventRaceHeat) {
            return;
        }

        $entries = $heat->entries;
        if ($entries->isEmpty()) {
            return;
        }

        $awaiting = $entries->filter(fn (EventRaceEntry $entry): bool => $entry->isAwaitingRace())->count();
        $decided = $entries->count() - $awaiting;

        $heat->update([
            'status' => $awaiting === 0 ? 'done' : ($decided > 0 ? 'running' : 'draft'),
            'finished_at' => $awaiting === 0 ? ($heat->finished_at ?? now()) : null,
            'started_at' => $heat->started_at ?? ($decided > 0 ? now() : null),
        ]);
    }

    /**
     * Pecah daftar babak menjadi batch berukuran satu grup. Satu grup
     * berisi beberapa babak berurutan sehingga menjadi satu blok jadwal
     * yang disiapkan panitia sekaligus.
     *
     * @param  array<int, array<int, EventRegistration>>  $races
     * @return array<int, array<int, array<int, EventRegistration>>>
     */
    private function chunkRacesIntoGroups(array $races, int $perGroup): array
    {
        $groups = [];
        $batch = [];
        $size = 0;

        foreach ($races as $rows) {
            $batch[] = $rows;
            $size += count($rows);

            if ($size >= $perGroup) {
                $groups[] = $batch;
                $batch = [];
                $size = 0;
            }
        }

        if ($batch !== []) {
            $groups[] = $batch;
        }

        return $groups;
    }

    /**
     * Berapa orang yang diangkut ke satu tahap.
     *
     * Babak penyisihan menampung seluruh yang lolos dari babak grup, sedangkan
     * semifinal dan final dibatasi jumlah kursinya masing-masing.
     */
    private function fieldSizeFor(Event $event, EventCategoryRaceSetting $setting, string $targetStage): int
    {
        return match ($targetStage) {
            'qualification' => (int) $setting->qualify_count
                * max(1, $this->stageHeatCount($event, $setting, 'heat')),
            'semi_final' => (int) $setting->semi_finalists,
            default => (int) $setting->finalists,
        };
    }

    private function stageHeatCount(Event $event, EventCategoryRaceSetting $setting, string $stage): int
    {
        return $this->heatsQuery($event, $setting)
            ->where('stage', $stage)
            ->count();
    }

    private function heatsQuery(Event $event, EventCategoryRaceSetting $setting): HasMany
    {
        return $event->raceHeats()
            ->where('category', $setting->category)
            ->where('race', $setting->race);
    }

    private function groupsQuery(Event $event, EventCategoryRaceSetting $setting): HasMany
    {
        return $event->raceGroups()
            ->where('category', $setting->category)
            ->where('race', $setting->race);
    }

    /**
     * Semua entry yang finis pada satu tahap, diurutkan oleh aturan penilaian
     * kategori: untuk mode poin, jumlah poin turun; untuk mode kualifikasi,
     * jumlah poin posisi naik.
     *
     * @return array<int, EventRegistration>
     */
    public function finishers(Event $event, string $category, string $race, string $stage): array
    {
        $entries = $event->raceEntries()
            ->where('category', $category)
            ->where('race', $race)
            ->where('status', EventRaceEntry::STATUS_FINISHED)
            ->whereHas('heat', fn ($query) => $query->where('stage', $stage))
            ->whereHas('registration', function ($query): void {
                $query->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', '!=', 'approved');
            })
            ->get();

        $setting = $event->raceSettingFor($category, $race);

        return $entries
            ->sortBy([
                fn (EventRaceEntry $a, EventRaceEntry $b): int => $setting->usesPoints()
                    ? $b->points <=> $a->points
                    : ($a->points <=> $b->points),
                fn (EventRaceEntry $a, EventRaceEntry $b): int => ($a->place ?? 99) <=> ($b->place ?? 99),
                fn (EventRaceEntry $a, EventRaceEntry $b): int => ($a->time_ms ?? PHP_INT_MAX) <=> ($b->time_ms ?? PHP_INT_MAX),
            ])
            ->map(fn (EventRaceEntry $entry): EventRegistration => $entry->registration)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Pecah peserta menurut klub. Club dengan peserta terbanyak diletakkan
     * lebih dulu supaya pembagian slotnya konsisten.
     *
     * @param  Collection<int, EventRegistration>  $entrants
     * @return array<int, array<int, EventRegistration>>
     */
    private function byClub(Collection $entrants, array &$warnings, int $perHeat): array
    {
        $groups = [];

        foreach ($entrants->groupBy(fn (EventRegistration $r): string => $this->clubKey($r))->all() as $club => $rows) {
            $chunks = array_chunk($rows->values()->all(), $perHeat);
            foreach ($chunks as $chunk) {
                $groups[] = $chunk;
            }

            if (count($rows) > $perHeat * 4) {
                $warnings[] = 'Klub '.$club.' punya '.count($rows).' atlet sehingga butuh '.count($chunks).' babak.';
            }
        }

        return $groups;
    }

    /**
     * Susun babak dengan mengisi ulang: atlet berikutnya selalu masuk babak
     * yang paling sedikit memuat klubnya, lalu babak yang paling kosong.
     * Hasilnya klub tersebar merata dan, untuk cross_club, tiap babak cenderung
     * sudah lintas klub sejak awal.
     *
     * @param  Collection<int, EventRegistration>  $entrants
     * @return array<int, array<int, EventRegistration>>
     */
    private function balanced(Collection $entrants, int $perHeat, bool $requireCrossClub, array &$warnings): array
    {
        $total = $entrants->count();
        $heatCount = max(1, (int) ceil($total / $perHeat));

        // Klub dengan peserta terbanyak didahulukan agar tidak jadi penumpuk di babak terakhir.
        $ordered = $entrants
            ->groupBy(fn (EventRegistration $r): string => $this->clubKey($r))
            ->sortByDesc(fn ($rows) => $rows->count())
            ->flatMap(fn ($rows) => $rows->sortBy('participant_name')->values()->all())
            ->values()
            ->all();

        /** @var array<int, array<int, EventRegistration>> $heats */
        $heats = array_fill(0, $heatCount, []);

        foreach ($ordered as $registration) {
            $club = $this->clubKey($registration);

            $best = null;
            foreach ($heats as $index => $heat) {
                $sameClub = count(array_filter($heat, fn (EventRegistration $row): bool => $this->clubKey($row) === $club));

                $score = [$sameClub, count($heat), $index];
                if ($best === null || $score < $best[0]) {
                    $best = [$score, $index];
                }
            }

            $heats[$best[1]][] = $registration;
        }

        $heats = array_values(array_filter($heats, fn (array $heat): bool => $heat !== []));

        if ($requireCrossClub) {
            foreach ($heats as $index => $heat) {
                $clubs = array_unique(array_map(fn (EventRegistration $r): string => $this->clubKey($r), $heat));
                if (count($clubs) < 2 && count($heats) > 1) {
                    $warnings[] = 'Babak '.($index + 1).' hanya berisi satu klub ('.reset($clubs).') sehingga aturan lintas klub tidak bisa dipenuhi.';
                }
            }
        }

        return $heats;
    }

    private function clubKey(EventRegistration $registration): string
    {
        $club = trim((string) $registration->club_name);

        return $club === '' ? 'Tanpa klub' : $club;
    }
}
