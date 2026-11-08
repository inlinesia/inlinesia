<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventRaceGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventRaceFormatController extends Controller
{
    /**
     * Aturan perlombaan sekaligus tempat menyusun urutan lomba, satu kartu
     * per pasangan kategori dan mata lomba. Satu set aturan per kategori
     * tidak cukup: atlet yang memilih dua mata lomba butuh aturan babak yang
     * berbeda untuk tiap mata lombanya.
     *
     * Setelah aturan disimpan, panitia menyusun urutan lomba lalu menekan
     * "Rilis kategori ini" supaya peserta dan klub bisa melihat siapa
     * lawannya jauh sebelum hari lomba.
     */
    public function index(Request $request, Event $event): View
    {
        $isAdmin = $this->authorizeManager($request, $event);

        $rules = $event->category_rules ?: collect($event->categories ?: ['Kategori Umum'])
            ->map(fn (string $name): array => ['name' => $name])
            ->all();

        // Unik pada (kategori, race) berkat unique index, jadi aman untuk
        // di-keyBy; groupBy akan mengembalikan collection berisi collection.
        $settings = $event->raceSettings->keyBy(fn (EventCategoryRaceSetting $setting): string => $this->identity($setting->category, $setting->race));

        $groups = $event->raceGroups()
            ->with([
                'heats' => fn ($query) => $query->orderBy('number')
                    ->with('entries.registration:id,participant_name,club_name'),
            ])
            ->get()
            ->groupBy(fn (EventRaceGroup $group): string => $this->identity($group->category, $group->race));

        $cards = collect($rules)->flatMap(function (array $rule) use ($event, $settings, $groups): array {
            $category = trim((string) ($rule['name'] ?? ''));
            if ($category === '') {
                return [];
            }

            $races = $event->racesForCategory($category) ?: [''];

            return collect($races)->map(fn (string $race): array => $this->card($event, $settings, $groups, $category, $race))->all();
        })->values();

        return view('verified.events.race.format', [
            'isAdmin' => $isAdmin,
            'event' => $event,
            'cards' => $cards,
            'hasHeats' => $event->raceHeats()->exists(),
            'hideSidebar' => true,
        ]);
    }

    /**
     * @param  Collection<string, EventCategoryRaceSetting>  $settings
     * @param  Collection<string, Collection<int, EventRaceGroup>>  $groups
     * @return array<string, mixed>
     */
    private function card(Event $event, $settings, $groups, string $category, string $race): array
    {
        $setting = $settings->get($this->identity($category, $race)) ?? new EventCategoryRaceSetting([
            'event_id' => $event->id,
            'category' => $category,
            'race' => $race,
        ]);

        $entrants = $event->registrations()
            ->where('category', $category)
            ->when($race !== '', fn ($query) => $query->whereJsonContains('races', $race))
            ->where('status', 'approved')
            // Harus sama dengan syarat RaceHeatBuilder::entrantsFor(),
            // kalau tidak pratinjau jumlah grup dan babak akan menyesatkan.
            ->when(! $event->is_free, fn ($query) => $query->where('payment_status', 'paid'))
            ->count();

        $arranged = $this->arrangedGroups($groups->get($this->identity($category, $race), collect()), $setting);

        $perHeat = $setting->effectiveRunnersPerHeat($entrants);
        $perGroup = $setting->effectiveAthletesPerGroup($entrants);

        return [
            'category' => $category,
            'race' => $race,
            'setting' => $setting,
            'entrants' => $entrants,
            'heat_count' => $arranged->sum(fn (EventRaceGroup $group): int => $group->heats->count()),
            'group_count' => $arranged->filter(fn (EventRaceGroup $group): bool => $group->heats->isNotEmpty())->count(),
            'projected_heats' => $perHeat > 0 ? (int) ceil($entrants / $perHeat) : 0,
            'projected_groups' => $perGroup > 0 ? (int) ceil($entrants / $perGroup) : 0,
            'groups' => $arranged,
            // Pratinjau angka mode otomatis; aturannya sama dengan yang
            // dipakai RaceHeatBuilder saat babak benar-benar disusun.
            'auto' => $setting->autoGrouping($entrants),
        ];
    }

    /**
     * Grup yang benar-benar punya babak, diurutkan mengikuti tangga format
     * lalu nomor grup. Grup penanda untuk tahap berikutnya sengaja tidak
     * ditampilkan sebagai susunan karena belum berisi atlet mana pun.
     *
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

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $availableCategories = collect($event->category_rules ?: collect($event->categories ?: ['Kategori Umum'])
            ->map(fn (string $name): array => ['name' => $name])
            ->all())
            ->pluck('name')
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->all();

        $validated = $request->validate([
            'race_settings' => ['required', 'array', 'min:1', 'max:400'],
            'race_settings.*.category' => [
                'required',
                'string',
                'max:120',
                Rule::in($availableCategories),
            ],
            'race_settings.*.race' => ['required', 'string', 'max:120'],
            'race_settings.*.runners_per_heat' => ['required', 'integer', 'min:2', 'max:24'],
            // Nullable supaya formulir lama yang belum mengirim angka ini
            // tetap tersimpan dengan bawaan belasan atlet per grup.
            'race_settings.*.athletes_per_group' => ['nullable', 'integer', 'min:1', 'max:500'],
            'race_settings.*.scoring_system' => ['required', Rule::in(array_keys(EventCategoryRaceSetting::SCORING_SYSTEMS))],
            'race_settings.*.qualify_count' => ['required', 'integer', 'min:1', 'max:24'],
            'race_settings.*.stages' => ['required', Rule::in(array_keys(EventCategoryRaceSetting::STAGES))],
            'race_settings.*.semi_finalists' => ['nullable', 'integer', 'min:0', 'max:48'],
            'race_settings.*.finalists' => ['required', 'integer', 'min:2', 'max:48'],
            'race_settings.*.champion_count' => ['required', 'integer', 'min:1', 'max:12'],
            'race_settings.*.opponent_scope' => ['required', Rule::in(array_keys(EventCategoryRaceSetting::OPPONENT_SCOPES))],
            'race_settings.*.grouping_mode' => ['required', Rule::in(array_keys(EventCategoryRaceSetting::GROUPING_MODES))],
        ]);

        $seen = [];

        foreach ($validated['race_settings'] as $index => $row) {
            if (! in_array($row['race'], $event->racesForCategory($row['category']), true)) {
                throw ValidationException::withMessages([
                    "race_settings.{$index}.race" => 'Mata lomba ini tidak dipertandingkan pada kategori tersebut.',
                ]);
            }

            // Kategori dan mata lomba adalah satu pasangan; dobelnya membuat
            // baris terakhir menimpa yang pertama tanpa panitia sadar.
            $identity = $this->identity($row['category'], $row['race']);
            if (isset($seen[$identity])) {
                throw ValidationException::withMessages([
                    "race_settings.{$index}.race" => 'Pasangan kategori dan mata lomba ini sudah ada di formulir.',
                ]);
            }
            $seen[$identity] = true;

            if ($row['qualify_count'] > $row['runners_per_heat']) {
                throw ValidationException::withMessages([
                    "race_settings.{$index}.qualify_count" => 'Jumlah yang lolos tidak boleh lebih banyak dari jumlah orang per babak.',
                ]);
            }

            if ($row['champion_count'] > $row['finalists']) {
                throw ValidationException::withMessages([
                    "race_settings.{$index}.champion_count" => 'Jumlah juara tidak boleh lebih banyak dari jumlah peserta final.',
                ]);
            }

            // Satu grup harus memuat minimal satu babak penuh, kalau tidak
            // ada atlet yang tersisa di babak berikutnya dalam grup itu.
            $perGroup = (int) ($row['athletes_per_group'] ?? 15);
            if ($perGroup < (int) $row['runners_per_heat']) {
                throw ValidationException::withMessages([
                    "race_settings.{$index}.athletes_per_group" => 'Atlet per grup tidak boleh lebih sedikit dari orang per babak.',
                ]);
            }

            $usesSemiFinal = in_array($row['stages'], ['semi_final', 'qualification_round'], true);
            if ($usesSemiFinal && (int) ($row['semi_finalists'] ?? 0) < 2) {
                throw ValidationException::withMessages([
                    "race_settings.{$index}.semi_finalists" => 'Finalis semifinal minimal dua orang.',
                ]);
            }
        }

        // Menyimpan aturan tidak menyusun ulang babak yang sudah ada, tetapi
        // perubahan aturan membuat susunan lama tidak lagi mewakili aturan
        // baru sehingga rilisnya ditarik sampai panitia menyusun ulang.
        $event->raceSettings()->delete();

        foreach ($validated['race_settings'] as $row) {
            $usesSemiFinal = in_array($row['stages'], ['semi_final', 'qualification_round'], true);

            $event->raceSettings()->create([
                'category' => $row['category'],
                'race' => $row['race'],
                'runners_per_heat' => (int) $row['runners_per_heat'],
                'athletes_per_group' => (int) ($row['athletes_per_group'] ?? 15),
                'scoring_system' => $row['scoring_system'],
                'qualify_count' => (int) $row['qualify_count'],
                'stages' => $row['stages'],
                'semi_finalists' => $usesSemiFinal ? (int) $row['semi_finalists'] : 0,
                'finalists' => (int) $row['finalists'],
                'champion_count' => (int) $row['champion_count'],
                'opponent_scope' => $row['opponent_scope'],
                'grouping_mode' => $row['grouping_mode'],
            ]);
        }

        return back()->with('status', 'Pengaturan perlombaan tersimpan.');
    }

    /**
     * Rilis kategori ini: susunan grup dan babaknya mulai tampil di halaman
     * urutan lomba yang bisa dilihat seluruh peserta dan klub.
     */
    public function release(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $setting = $this->settingFor($request, $event);

        if (! $setting->exists) {
            return back()->with('status', 'Simpan pengaturan dulu sebelum merilis kategori ini.');
        }

        $hasArrangement = $event->raceGroups()
            ->where('category', $setting->category)
            ->where('race', $setting->race)
            ->whereHas('heats')
            ->exists();

        if (! $hasArrangement) {
            return back()->with('status', 'Belum ada urutan lomba untuk dirilis. Susun dulu lewat tombol “Susun urutan lomba”.');
        }

        $setting->update(['released_at' => now()]);

        return back()->with('status', $setting->category.' · '.$setting->race.' dirilis. Urutan lombanya sekarang terlihat di halaman urutan lomba.');
    }

    /** Tarik rilis supaya susunan tidak lagi terlihat oleh peserta. */
    public function unrelease(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $setting = $this->settingFor($request, $event);

        if ($setting->exists) {
            $setting->update(['released_at' => null]);
        }

        return back()->with('status', 'Rilis '.$setting->category.' · '.$setting->race.' ditarik.');
    }

    private function settingFor(Request $request, Event $event): EventCategoryRaceSetting
    {
        $category = (string) $request->input('kategori');
        $races = $event->racesForCategory($category);
        $race = (string) $request->input('mata_lomba', $races[0] ?? '');

        abort_unless($races !== [] && in_array($race, $races, true), 404, 'Mata lomba tidak dikenal.');

        return $event->raceSettingFor($category, $race);
    }

    private function identity(string $category, string $race): string
    {
        return $category.'|'.$race;
    }

    private function authorizeManager(Request $request, Event $event): bool
    {
        $isAdmin = $request->user()->role === 'admin';
        $canManageAsOwner = ! $isAdmin
            && $event->submitted_by === $request->user()->id
            && $event->status === 'approved';

        abort_unless($isAdmin || $canManageAsOwner, 403);

        return $isAdmin;
    }
}
