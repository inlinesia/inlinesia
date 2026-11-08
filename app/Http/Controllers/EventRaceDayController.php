<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCategoryRaceSetting;
use App\Models\EventRaceEntry;
use App\Models\EventRaceGroup;
use App\Models\EventRaceHeat;
use App\Services\RaceHeatBuilder;
use App\Services\RaceStandings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventRaceDayController extends Controller
{
    public function __construct(
        private readonly RaceHeatBuilder $heats,
        private readonly RaceStandings $standings,
    ) {}

    /**
     * Halaman hari lomba: daftar grup yang sudah disusun di halaman atur
     * perlombaan, tombol "Siapkan peserta" per grup, dan tombol hasil per
     * lane. Tombol hasil hanya muncul setelah grup disiapkan, supaya
     * tampilan umum bisa menandai grup mana yang sedang berjalan.
     *
     * Grup dibagi per kategori sekaligus mata lomba, karena atlet yang
     * memilih dua mata lomba tidak mungkin balapan bersamaan.
     */
    public function show(Request $request, Event $event): View
    {
        $isAdmin = $this->authorizeManager($request, $event);

        $categories = $this->categories($event);
        abort_if($categories === [], 404, 'Belum ada kategori yang bisa dilombakan.');

        $category = $request->query('kategori');
        $category = is_string($category) && in_array($category, $categories, true) ? $category : $categories[0];

        $races = $event->racesForCategory($category);
        abort_if($races === [], 404, 'Kategori ini belum punya mata lomba.');

        $race = $request->query('mata_lomba');
        $race = is_string($race) && in_array($race, $races, true) ? $race : $races[0];

        $setting = $event->raceSettingFor($category, $race);

        $groups = $this->groupsFor($event, $setting);

        $entrants = $this->heats->entrantsFor($event, $category, $race)->count();

        return view('verified.events.race.day', [
            'isAdmin' => $isAdmin,
            'event' => $event,
            'categories' => $categories,
            'races' => $races,
            'category' => $category,
            'race' => $race,
            'setting' => $setting,
            'groups' => $groups,
            // Tahap setelah babak awal dihitung dari tangga format, jadi
            // halaman ini menyesuaikan sendiri saat panitia mengganti format.
            'stageMeta' => $this->stageMeta($event, $setting),
            'entrants' => $entrants,
            'standings' => $this->standings->forCategory($event, $setting),
            'hideSidebar' => true,
        ]);
    }

    /** Susun ulang urutan lomba satu kategori dan mata lomba. */
    public function buildHeats(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $setting = $this->settingFor($request, $event);
        $result = $this->heats->buildHeats($event, $setting);

        $status = $result['created'] > 0
            ? $result['groups'].' grup dan '.$result['heats'].' babak dibuat berisi '.$result['created'].' atlet untuk '.$setting->category.' · '.$setting->race.'.'
            : 'Urutan lomba belum bisa disusun.';

        return back()->with('status', $status)->with('warnings', $result['warnings']);
    }

    /** Hapus seluruh susunan dan hasil pada satu kategori + mata lomba. */
    public function resetHeats(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $setting = $this->settingFor($request, $event);
        $this->heats->resetArrangement($event, $setting);

        return back()->with('status', 'Seluruh susunan dan hasil untuk '.$setting->category.' · '.$setting->race.' dihapus.');
    }

    /**
     * Siapkan satu grup untuk dilombakan: setelah tombol ini ditekan,
     * tampilan umum menandai grup sedang berjalan dan tombol hasil
     * (Menang / Tidak muncul / DNF / DSQ / Ulangi / Tunda) muncul.
     */
    public function prepareGroup(Request $request, Event $event, EventRaceGroup $group): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        abort_unless($group->event_id === $event->id, 404);
        abort_if($group->heats->isEmpty(), 422, 'Grup ini belum punya babak untuk disiapkan.');

        $group->update(['started_at' => $group->started_at ?? now()]);

        return back()->with('status', $group->label().' '.$group->stageLabel().' disiapkan. Tombol hasil sekarang terbuka.');
    }

    /**
     * Tunda satu atlet: dia dipindahkan ke urutan paling akhir pada kategori
     * dan mata lomba yang sama, dan seluruh atlet di belakangnya maju.
     */
    public function postpone(Request $request, Event $event, EventRaceEntry $entry): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        abort_unless($entry->event_id === $event->id, 404);

        $result = $this->heats->postpone($event, $entry);

        return back()->with('status', $result['message']);
    }

    /**
     * Simpan hasil satu babak: siapa finis di urutan berapa, siapa yang tidak
     * muncul, tidak menyelesaikan, atau diskualifikasi.
     */
    public function updateHeat(Request $request, Event $event, EventRaceHeat $heat): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        abort_unless($heat->event_id === $event->id, 404);

        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.status' => ['required', Rule::in(array_keys(EventRaceEntry::STATUSES))],
            'entries.*.place' => ['nullable', 'integer', 'min:1', 'max:99'],
            'entries.*.time' => ['nullable', 'string', 'max:20'],
            'entries.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = $event->raceSettingFor($heat->category, $heat->race);
        $heat->load('entries');

        $entries = $heat->entries->keyBy('id');

        // Urutan finis harus unik dalam satu babak; kalau tidak, komite
        // tidak tahu siapa yang lebih dulu sampai garis.
        $places = [];
        foreach ($validated['entries'] as $entryId => $row) {
            if (! $entries->has((int) $entryId)) {
                continue;
            }

            if ($row['status'] === EventRaceEntry::STATUS_FINISHED && ($row['place'] ?? '') === '') {
                throw ValidationException::withMessages([
                    "entries.{$entryId}.place" => 'Isi urutan finis untuk atlet yang finis.',
                ]);
            }

            if ($row['status'] !== EventRaceEntry::STATUS_FINISHED) {
                continue;
            }

            $place = (int) $row['place'];
            if (isset($places[$place])) {
                throw ValidationException::withMessages([
                    "entries.{$entryId}.place" => 'Urutan finis sudah dipakai atlet lain di babak ini.',
                ]);
            }

            $places[$place] = true;
        }

        /*
         * Poin dihitung dari jumlah lane di babak, bukan dari jumlah yang
         * finis. Kalau memakai yang finis, nama yang finis pertama akan
         * mendapat jumlah poin berbeda tergantung siapa yang tidak muncul.
         */
        $fieldSize = max(1, $entries->count());
        $qualifiedQuota = $this->qualifiedQuota($heat, $setting);

        DB::transaction(function () use ($validated, $entries, $heat, $setting, $fieldSize, $qualifiedQuota): void {
            foreach ($validated['entries'] as $entryId => $row) {
                /** @var EventRaceEntry|null $entry */
                $entry = $entries->get((int) $entryId);
                if (! $entry instanceof EventRaceEntry) {
                    continue;
                }

                $finished = $row['status'] === EventRaceEntry::STATUS_FINISHED;
                $place = $finished ? (int) $row['place'] : null;

                $time = null;
                if ($finished && ($row['time'] ?? '') !== '') {
                    $time = EventRaceEntry::parseTime($row['time']);
                    if ($time === null) {
                        throw ValidationException::withMessages([
                            'entries.'.$entryId.'.time' => 'Waktu harus ditulis sebagai mm:ss.ddd, contoh 1:23.45.',
                        ]);
                    }
                }

                $entry->update([
                    'status' => $row['status'],
                    'place' => $place,
                    'time_ms' => $time,
                    'note' => $row['note'] ?? null,
                    'points' => $place === null ? 0 : ($setting->usesPoints() ? $fieldSize - $place + 1 : $place),
                    'is_qualified' => $place !== null
                        && $heat->stage !== $setting->decidingStage()
                        && $place <= $qualifiedQuota,
                ]);
            }

            $this->heats->syncHeatStatus($heat->refresh()->load('entries'));
        });

        return back()->with('status', 'Hasil '.$heat->label.' tersimpan.');
    }

    /** Lanjut ke tahap berikutnya pada tangga format: penyisihan, semifinal, atau final. */
    public function advance(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeManager($request, $event);

        $setting = $this->settingFor($request, $event);
        $stage = (string) $request->input('stage');

        abort_unless(in_array($stage, ['qualification', 'semi_final', 'final'], true), 422, 'Tahap tidak dikenal.');

        $ladder = $setting->stageLadder();
        if (! in_array($stage, $ladder, true)) {
            return back()->with('status', 'Format tahap yang dipilih tidak memakai '.(EventRaceHeat::STAGES[$stage] ?? $stage).'.');
        }

        $previous = $setting->stageBefore($stage);
        $progress = $this->standings->stageProgress($event, $setting->category, $setting->race, $previous);

        if (! $progress['complete']) {
            return back()->with('status', 'Masih ada babak '.($previous ?? '').' yang belum selesai diisi.');
        }

        $result = $this->heats->advance($event, $setting, $stage);
        $stageLabel = EventRaceHeat::STAGES[$stage] ?? $stage;

        $status = $result['created'] > 0
            ? $stageLabel.' dibuat berisi '.$result['created'].' atlet.'
            : $stageLabel.' belum bisa dibuat.';

        return back()->with('status', $status)->with('warnings', $result['warnings']);
    }

    /**
     * Grup satu kategori dan mata lomba, diurutkan mengikuti tangga format
     * lalu nomor grup, lengkap dengan babak dan baris pesertanya.
     *
     * @return Collection<int, EventRaceGroup>
     */
    private function groupsFor(Event $event, EventCategoryRaceSetting $setting): Collection
    {
        $ladder = $setting->stageLadder();

        return $event->raceGroups()
            ->where('category', $setting->category)
            ->where('race', $setting->race)
            ->with([
                'heats' => fn ($query) => $query->orderBy('number')
                    ->with('entries.registration:id,participant_name,club_name,category'),
            ])
            ->get()
            ->sortBy(fn (EventRaceGroup $group): array => [
                array_search($group->stage, $ladder, true) ?: count($ladder),
                (int) $group->number,
            ])
            ->values();
    }

    /**
     * Keterangan tiap tahap setelah babak awal, dipakai untuk tombol
     * "susun" pada grup yang belum punya babak.
     *
     * @return array<string, array{stage:string, label:string, seats:int, previous:?string, progress:array{total:int, done:int, complete:bool}}>
     */
    private function stageMeta(Event $event, EventCategoryRaceSetting $setting): array
    {
        $heatCount = max(1, $this->heatQuery($event, $setting->category, $setting->race)->where('stage', 'heat')->count());

        $meta = [];

        foreach (array_slice($setting->stageLadder(), 1) as $stage) {
            $meta[$stage] = [
                'stage' => $stage,
                'label' => EventRaceHeat::STAGES[$stage] ?? $stage,
                'seats' => match ($stage) {
                    'qualification' => (int) $setting->qualify_count * $heatCount,
                    'semi_final' => (int) $setting->semi_finalists,
                    default => (int) $setting->finalists,
                },
                'previous' => $setting->stageBefore($stage),
                'progress' => $this->standings->stageProgress($event, $setting->category, $setting->race, $stage),
            ];
        }

        return $meta;
    }

    /**
     * Berapa orang yang boleh lolos dari tahap ini, dipakai sebagai tanda
     * "Lolos" pada tiap lane.
     */
    private function qualifiedQuota(EventRaceHeat $heat, EventCategoryRaceSetting $setting): int
    {
        return match ($heat->stage) {
            'heat', 'qualification' => $setting->qualify_count,
            'semi_final' => $setting->finalists,
            default => 0,
        };
    }

    private function heatQuery(Event $event, string $category, string $race)
    {
        return $event->raceHeats()
            ->where('category', $category)
            ->where('race', $race);
    }

    private function settingFor(Request $request, Event $event): EventCategoryRaceSetting
    {
        $categories = $this->categories($event);
        $category = (string) $request->input('kategori');

        abort_unless($categories !== [] && in_array($category, $categories, true), 404, 'Kategori tidak dikenal.');

        $races = $event->racesForCategory($category);
        $race = (string) $request->input('mata_lomba', $races[0] ?? '');

        abort_unless($races !== [] && in_array($race, $races, true), 404, 'Mata lomba tidak dikenal.');

        return $event->raceSettingFor($category, $race);
    }

    /**
     * @return array<int, string>
     */
    private function categories(Event $event): array
    {
        return collect($event->category_rules ?: collect($event->categories ?: [])->map(fn ($name): array => ['name' => $name])->all())
            ->pluck('name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->values()
            ->all();
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
