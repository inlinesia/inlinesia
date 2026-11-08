<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\CategoryFeeCalculator;
use App\Services\PricingWaveResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventCategoryController extends Controller
{
    public function edit(Request $request, Event $event): View
    {
        $isAdmin = $this->authorizeManager($request, $event);
        $categoryNames = $this->categoryNames($event);

        $rules = collect($event->category_rules ?: collect($event->categories ?: ['Kategori Umum'])
            ->map(fn (string $name): array => [
                'name' => $name,
                'birth_date_from' => '',
                'birth_date_to' => '',
                'birth_years' => [],
                'minimum_age' => null,
                'maximum_age' => null,
                'genders' => [],
                'races' => $event->races,
                'required_races' => 1,
                'max_registrants' => null,
                'registration_override' => 'auto',
                'package_fee' => 0,
                'extra_race_fee' => 0,
                'outside_race_fee' => 0,
                'discount_percent' => null,
                'discount_amount' => null,
            ])
            ->values()
            ->all())
            ->map(function (array $rule) use ($categoryNames): array {
                $birthYears = $rule['birth_years'] ?? [];
                $eligibilityType = $rule['eligibility_type'] ?? $this->legacyEligibilityType($rule);

                if ($eligibilityType === 'birth_year' && (empty($rule['birth_date_from']) || empty($rule['birth_date_to'])) && ! empty($birthYears)) {
                    $rule['birth_date_from'] = min($birthYears).'-01-01';
                    $rule['birth_date_to'] = max($birthYears) === now()->year
                        ? today()->format('Y-m-d')
                        : max($birthYears).'-12-31';
                }

                return [
                    ...$rule,
                    'base_name' => $this->categoryBaseName((string) ($rule['name'] ?? ''), $categoryNames),
                    'gender_group' => $this->genderGroup($rule['genders'] ?? []),
                    'outside_obligation' => (bool) ($rule['outside_obligation'] ?? false),
                    'birth_years' => array_map('intval', $birthYears ?? []),
                    'eligibility_types' => [$eligibilityType],
                    'required_races' => max(1, (int) ($rule['required_races'] ?? 1)),
                    'max_registrants' => $this->normalizeQuota($rule['max_registrants'] ?? null),
                    'registration_override' => $this->normalizeOverride($rule['registration_override'] ?? null),
                    'package_fee' => max(0, (int) ($rule['package_fee'] ?? 0)),
                    'extra_race_fee' => max(0, (int) ($rule['extra_race_fee'] ?? 0)),
                    'outside_race_fee' => max(0, (int) ($rule['outside_race_fee'] ?? 0)),
                    'discount_percent' => $rule['discount_percent'] ?? null,
                    'discount_amount' => $rule['discount_amount'] ?? null,
                    'pricing_waves' => app(PricingWaveResolver::class)->waves($rule),
                ];
            })
            ->values()
            ->all();
        $birthYears = range(now()->year, 1900);

        return view('verified.events.categories', [
            ...compact('event', 'rules', 'isAdmin', 'birthYears', 'categoryNames'),
            // Status kuota ikut dikirim ke tampilan supaya pengelola langsung
            // tahu kategori mana yang sudah penuh tanpa harus membuka form.
            'categoryStatuses' => $this->categoryStatuses($event),
            // Halaman pengaturan kategori adalah continuation dari dasbor
            // bersama, jadi chrome panel admin (sidebar) tidak ditampilkan
            // meski dibuka oleh admin.
            'hideSidebar' => true,
        ]);
    }

    /**
     * Ringkasan batas peserta tiap kategori dalam bahasa pengelola.
     *
     * @return array<string, string>
     */
    private function categoryStatuses(Event $event): array
    {
        $statuses = [];

        foreach ($event->category_rules ?? [] as $rule) {
            $name = $rule['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $quota = $event->categoryQuota($name);
            $confirmed = $event->confirmedCategoryCount($name);
            $override = $this->normalizeOverride($rule['registration_override'] ?? null);
            $open = $event->isCategoryRegistrationOpen($name);

            $statuses[$name] = $quota === null
                ? sprintf('Tanpa batas peserta · %d peserta terkonfirmasi', $confirmed)
                : sprintf('%d/%d peserta terkonfirmasi · sisa %d', $confirmed, $quota, $event->categoryRemainingQuota($name));

            if ($override === 'closed') {
                $statuses[$name] .= ' · ditutup paksa oleh pengelola';
            } elseif ($override === 'open') {
                $statuses[$name] .= ' · dibuka paksa oleh pengelola';
            } elseif (! $open) {
                $statuses[$name] .= ' · penuh, pendaftaran ditutup otomatis';
            }
        }

        return $statuses;
    }

    /**
     * Batas peserta yang masuk akal, atau null bila kategori tanpa batas.
     */
    private function normalizeQuota(mixed $value): ?int
    {
        $quota = (int) $value;

        return $quota < 1 ? null : $quota;
    }

    /**
     * Keadaan buka/tutup kategori yang disimpan pengelola.
     */
    private function normalizeOverride(mixed $value): string
    {
        return in_array($value, ['auto', 'open', 'closed'], true) ? $value : 'auto';
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $isAdmin = $this->authorizeManager($request, $event);
        $availableBirthYears = range(1900, now()->year);
        $categoryRules = $request->input('category_rules');

        if (is_array($categoryRules)) {
            foreach ($categoryRules as $index => $rule) {
                if (! is_array($rule)
                    || ! is_string($rule['base_name'] ?? null)
                    || ! is_string($rule['gender_group'] ?? null)) {
                    continue;
                }

                $genderLabel = match ($rule['gender_group']) {
                    'male' => 'Putra',
                    'female' => 'Putri',
                    'mixed' => 'Campuran',
                    default => null,
                };

                if ($genderLabel === null) {
                    continue;
                }

                $outsideObligation = filter_var($rule['outside_obligation'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $categoryRules[$index]['name'] = trim($rule['base_name']).' '.$genderLabel
                    .($outsideObligation ? ' — di luar kewajiban' : '');
                $categoryRules[$index]['outside_obligation'] = $outsideObligation;
                $categoryRules[$index]['genders'] = match ($rule['gender_group']) {
                    'male' => ['male'],
                    'female' => ['female'],
                    'mixed' => ['male', 'female'],
                };
            }

            $request->merge(['category_rules' => $categoryRules]);
        }

        $validated = $request->validate([
            'category_rules' => ['required', 'array', 'min:1', 'max:30'],
            'category_rules.*.name' => ['required', 'string', 'max:120', 'distinct'],
            'category_rules.*.base_name' => ['sometimes', 'required', 'string', Rule::in($this->categoryNames($event))],
            'category_rules.*.gender_group' => ['sometimes', 'required', Rule::in(['male', 'female', 'mixed'])],
            'category_rules.*.outside_obligation' => ['sometimes', 'boolean'],
            'category_rules.*.eligibility_types' => ['required', 'array', 'size:1'],
            'category_rules.*.eligibility_types.*' => ['required', Rule::in(['birth_date', 'birth_year', 'age'])],
            'category_rules.*.birth_date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category_rules.*.birth_date_to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category_rules.*.birth_years' => ['sometimes', 'array'],
            'category_rules.*.birth_years.*' => ['required', 'integer', Rule::in($availableBirthYears)],
            'category_rules.*.minimum_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'category_rules.*.maximum_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'category_rules.*.genders' => ['required', 'array', 'min:1'],
            'category_rules.*.genders.*' => ['required', Rule::in(['male', 'female'])],
            'category_rules.*.races' => ['required', 'array', 'min:1'],
            'category_rules.*.races.*' => ['required', Rule::in($event->races)],
            'category_rules.*.required_races' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'category_rules.*.max_registrants' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'category_rules.*.registration_override' => ['sometimes', 'nullable', Rule::in(['auto', 'open', 'closed'])],
            'category_rules.*.package_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.extra_race_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.outside_race_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.discount_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'category_rules.*.discount_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.base_starts_on' => ['nullable', 'date_format:Y-m-d'],
            'category_rules.*.base_ends_on' => ['nullable', 'date_format:Y-m-d'],
            'category_rules.*.pricing_waves' => ['sometimes', 'array', 'max:10'],
            'category_rules.*.pricing_waves.*.label' => ['required', 'string', 'max:80'],
            'category_rules.*.pricing_waves.*.starts_on' => ['nullable', 'date_format:Y-m-d'],
            'category_rules.*.pricing_waves.*.ends_on' => ['nullable', 'date_format:Y-m-d'],
            'category_rules.*.pricing_waves.*.package_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.pricing_waves.*.extra_race_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.pricing_waves.*.outside_race_fee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_rules.*.pricing_waves.*.discount_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'category_rules.*.pricing_waves.*.discount_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'registration_open' => ['sometimes', 'boolean'],
        ]);

        $rules = collect($validated['category_rules'])
            ->map(function (array $rule, int $index) use ($event): array {
                $rule['birth_years'] ??= [];
                $eligibilityType = $rule['eligibility_types'][0];
                $minimumAge = $rule['minimum_age'] ?? null;
                $maximumAge = $rule['maximum_age'] ?? null;

                if ($eligibilityType === 'birth_date'
                    && (empty($rule['birth_date_from']) || empty($rule['birth_date_to']))) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.birth_date_from" => 'Rentang tanggal lahir wajib diisi untuk metode tanggal lahir.',
                    ]);
                }

                if ($eligibilityType === 'birth_year' && $rule['birth_years'] === []) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.birth_years" => 'Pilih minimal satu tahun lahir untuk metode tahun lahir.',
                    ]);
                }

                if ($eligibilityType === 'age' && $minimumAge === null && $maximumAge === null) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.minimum_age" => 'Isi usia minimal atau usia maksimal untuk metode usia.',
                    ]);
                }

                if (count($rule['birth_years']) !== count(array_unique($rule['birth_years']))) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.birth_years" => 'Tahun lahir yang sama tidak boleh dipilih lebih dari sekali.',
                    ]);
                }

                if (count($rule['genders']) !== count(array_unique($rule['genders']))) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.genders" => 'Gender yang sama tidak boleh dipilih lebih dari sekali.',
                    ]);
                }

                if (count($rule['races']) !== count(array_unique($rule['races']))) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.races" => 'Mata lomba yang sama tidak boleh dipilih lebih dari sekali.',
                    ]);
                }

                $requiredRaces = (int) ($rule['required_races'] ?? 1);
                if ($requiredRaces < 1) {
                    $requiredRaces = 1;
                }

                if ($requiredRaces > count($rule['races'])) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.required_races" => 'Jumlah mata lomba wajib tidak boleh melebihi mata lomba yang diizinkan pada kategori ini.',
                    ]);
                }

                if ($event->is_free) {
                    $packageFee = $extraRaceFee = $outsideRaceFee = 0;
                    $discountPercent = $discountAmount = null;
                    $baseStartsOn = $baseEndsOn = null;
                    $pricingWaves = [];
                } else {
                    $packageFee = (int) ($rule['package_fee'] ?? 0);
                    $extraRaceFee = (int) ($rule['extra_race_fee'] ?? 0);
                    $outsideRaceFee = (int) ($rule['outside_race_fee'] ?? 0);
                    $discountPercent = ($rule['discount_percent'] ?? null) === null || ($rule['discount_percent'] ?? '') === ''
                        ? null
                        : (int) $rule['discount_percent'];
                    $discountAmount = ($rule['discount_amount'] ?? null) === null || ($rule['discount_amount'] ?? '') === ''
                        ? null
                        : (int) $rule['discount_amount'];
                    $baseStartsOn = trim((string) ($rule['base_starts_on'] ?? '')) ?: null;
                    $baseEndsOn = trim((string) ($rule['base_ends_on'] ?? '')) ?: null;
                    $pricingWaves = $this->normalizePricingWaves($rule['pricing_waves'] ?? [], $index);
                }

                return [
                    'name' => trim($rule['name']),
                    'max_registrants' => $this->normalizeQuota($rule['max_registrants'] ?? null),
                    'registration_override' => $this->normalizeOverride($rule['registration_override'] ?? null),
                    'eligibility_type' => $eligibilityType,
                    'birth_date_from' => $eligibilityType === 'birth_date' ? $rule['birth_date_from'] : null,
                    'birth_date_to' => $eligibilityType === 'birth_date' ? $rule['birth_date_to'] : null,
                    'birth_years' => $eligibilityType === 'birth_year' ? array_values(array_map('intval', $rule['birth_years'])) : [],
                    'minimum_age' => $eligibilityType === 'age' && $minimumAge !== null && $minimumAge !== '' ? (int) $minimumAge : null,
                    'maximum_age' => $eligibilityType === 'age' && $maximumAge !== null && $maximumAge !== '' ? (int) $maximumAge : null,
                    'genders' => array_values($rule['genders']),
                    'races' => array_values($rule['races']),
                    'outside_obligation' => (bool) ($rule['outside_obligation'] ?? false),
                    'required_races' => $requiredRaces,
                    'package_fee' => $packageFee,
                    'extra_race_fee' => $extraRaceFee,
                    'outside_race_fee' => $outsideRaceFee,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'base_starts_on' => $baseStartsOn,
                    'base_ends_on' => $baseEndsOn,
                    'pricing_waves' => $pricingWaves,
                ];
            })
            ->values()
            ->all();

        $overlapErrors = [];

        foreach ($rules as $index => $rule) {
            if ($rule['eligibility_type'] === 'birth_date' && $rule['birth_date_from'] > $rule['birth_date_to']) {
                throw ValidationException::withMessages([
                    "category_rules.{$index}.birth_date_to" => 'Tanggal lahir sampai harus sama atau setelah tanggal lahir dari.',
                ]);
            }

            if ($rule['minimum_age'] !== null && $rule['maximum_age'] !== null
                && $rule['minimum_age'] > $rule['maximum_age']) {
                throw ValidationException::withMessages([
                    "category_rules.{$index}.maximum_age" => 'Usia maksimal harus sama atau lebih besar dari usia minimal.',
                ]);
            }

            if ($rule['base_starts_on'] !== null && $rule['base_ends_on'] !== null
                && $rule['base_starts_on'] > $rule['base_ends_on']) {
                throw ValidationException::withMessages([
                    "category_rules.{$index}.base_ends_on" => 'Tanggal berakhir tarif utama tidak boleh lebih awal dari tanggal mulainya.',
                ]);
            }

            // Rentang tarif utama tidak boleh tumpang-tindih dengan rentang
            // gelombang, karena satu tanggal hanya boleh punya satu harga.
            foreach ($rule['pricing_waves'] as $waveIndex => $wave) {
                if ($this->rangesOverlap($rule['base_starts_on'], $rule['base_ends_on'], $wave['starts_on'], $wave['ends_on'])) {
                    throw ValidationException::withMessages([
                        "category_rules.{$index}.pricing_waves.{$waveIndex}.starts_on" => 'Rentang tanggal gelombang "'.$wave['label'].'" tumpang-tindih dengan rentang tarif utama.',
                    ]);
                }
            }

            // Gelombang-j gelombang pada satu kategori juga tidak boleh saling
            // tumpang-tindih supaya tanggal pendaftaran selalu tidak ambigu.
            foreach ($rule['pricing_waves'] as $waveIndex => $wave) {
                foreach (array_slice($rule['pricing_waves'], $waveIndex + 1, null, true) as $otherIndex => $otherWave) {
                    if ($this->rangesOverlap($wave['starts_on'], $wave['ends_on'], $otherWave['starts_on'], $otherWave['ends_on'])) {
                        throw ValidationException::withMessages([
                            "category_rules.{$index}.pricing_waves.{$otherIndex}.starts_on" => 'Rentang tanggal gelombang "'.$otherWave['label'].'" tumpang-tindih dengan gelombang "'.$wave['label'].'".',
                        ]);
                    }
                }
            }

            foreach (array_slice($rules, $index + 1, null, true) as $otherIndex => $other) {
                $sharedGenders = array_values(array_intersect($rule['genders'], $other['genders']));
                $sharedRaces = array_values(array_intersect($rule['races'], $other['races']));
                $sharedBirthDateRanges = $this->sharedBirthDateRanges($rule, $other, $event);

                if ($sharedGenders !== [] && $sharedRaces !== [] && $sharedBirthDateRanges !== []) {
                    $genderLabels = array_map(
                        fn (string $gender): string => $gender === 'male' ? 'laki-laki' : 'perempuan',
                        $sharedGenders,
                    );
                    $birthDateLabels = array_map(
                        fn (array $range): string => $range['from'] === sprintf('%04d-01-01', (int) substr($range['from'], 0, 4))
                            && $range['to'] === sprintf('%04d-12-31', (int) substr($range['to'], 0, 4))
                                ? 'tahun lahir '.substr($range['from'], 0, 4)
                                : 'tanggal lahir '.$range['from'].' sampai '.$range['to'],
                        $sharedBirthDateRanges,
                    );

                    $overlapErrors["category_rules.{$otherIndex}.birth_date_from"][] = sprintf(
                        'Kategori "%s" bertumpang tindih dengan kategori "%s" untuk %s, gender %s, dan mata lomba %s.',
                        $rule['name'],
                        $other['name'],
                        implode(', ', $birthDateLabels),
                        implode(' dan ', $genderLabels),
                        implode(', ', $sharedRaces),
                    );
                }
            }
        }

        if ($overlapErrors !== []) {
            throw ValidationException::withMessages($overlapErrors);
        }

        $registrationOpen = (bool) ($validated['registration_open'] ?? $event->registration_open);
        if ($registrationOpen) {
            abort_if($event->status !== 'approved', 409, 'Pendaftaran baru dapat dibuka setelah kejuaraan disetujui Admin.');
            if ($event->registration_deadline && $event->registration_deadline->endOfDay()->isPast()) {
                throw ValidationException::withMessages([
                    'registration_open' => 'Batas pendaftaran sudah lewat sehingga pendaftaran tidak dapat dibuka.',
                ]);
            }
        }

        $previousPricing = $this->pricingSignature($event->category_rules ?: []);
        $event->update([
            'category_rules' => $rules,
            'categories' => array_column($rules, 'name'),
            ...($event->status === 'approved' ? ['registration_open' => $registrationOpen] : []),
        ]);

        $refunded = 0;
        if ($this->pricingSignature($rules) !== $previousPricing) {
            $refunded = app(CategoryFeeCalculator::class)->refreshEvent($event->fresh());
        }

        $status = "Aturan kategori untuk “{$event->name}” berhasil diperbarui.";

        if ($refunded > 0) {
            $status .= " Harga tiket {$refunded} peserta dihitung ulang mengikuti aturan terbaru.";
        }

        return redirect()->route('guest.dashboard')->with('status', $status);
    }

    /**
     * Apakah dua rentang tanggal saling tumpang-tindih.
     *
     * Rentang dengan tanggal kosong dibaca sebagai tanpa batas, sehingga tidak
     * pernah dianggap bertabrakan dengan rentang lain. Dengan begitu kategori
     * lama yang belum memakai tanggal tarif utama tetap bisa disimpan.
     */
    private function rangesOverlap(?string $aStart, ?string $aEnd, ?string $bStart, ?string $bEnd): bool
    {
        if ($aStart === null || $aEnd === null || $bStart === null || $bEnd === null) {
            return false;
        }

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    /**
     * Merapikan gelombang tarif satu kategori, sekaligus memvalidasi urutan
     * tanggalnya agar peserta selalu masuk ke gelombang yang jelas.
     *
     * Setiap nilai kosong disimpan sebagai null supaya kolom tersebut
     * mengikuti tarif dasar kategori, bukan dipaksa jadi nol.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizePricingWaves(mixed $waves, int $ruleIndex): array
    {
        if (! is_array($waves)) {
            return [];
        }

        $normalized = [];

        foreach ($waves as $waveIndex => $wave) {
            if (! is_array($wave)) {
                continue;
            }

            $startsOn = trim((string) ($wave['starts_on'] ?? '')) ?: null;
            $endsOn = trim((string) ($wave['ends_on'] ?? '')) ?: null;

            if ($startsOn !== null && $endsOn !== null && $startsOn > $endsOn) {
                throw ValidationException::withMessages([
                    "category_rules.{$ruleIndex}.pricing_waves.{$waveIndex}.ends_on" => 'Tanggal berakhir gelombang tidak boleh lebih awal dari tanggal mulainya.',
                ]);
            }

            $amount = function (mixed $value): ?int {
                return $value === null || $value === '' ? null : max(0, (int) $value);
            };

            $normalized[] = [
                'label' => trim((string) ($wave['label'] ?? '')) ?: 'Gelombang '.(count($normalized) + 1),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'package_fee' => $amount($wave['package_fee'] ?? null),
                'extra_race_fee' => $amount($wave['extra_race_fee'] ?? null),
                'outside_race_fee' => $amount($wave['outside_race_fee'] ?? null),
                'discount_percent' => $amount($wave['discount_percent'] ?? null),
                'discount_amount' => $amount($wave['discount_amount'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * Sidik jari aturan harga seluruh kategori, dipakai untuk mendeteksi
     * apakah harga peserta perlu dihitung ulang setelah Kategori disimpan.
     *
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function pricingSignature(array $rules): string
    {
        return (string) json_encode(collect($rules)->map(fn (array $rule): array => [
            'name' => $rule['name'] ?? null,
            'required_races' => $rule['required_races'] ?? null,
            'package_fee' => $rule['package_fee'] ?? null,
            'extra_race_fee' => $rule['extra_race_fee'] ?? null,
            'outside_race_fee' => $rule['outside_race_fee'] ?? null,
            'outside_obligation' => $rule['outside_obligation'] ?? false,
            'discount_percent' => $rule['discount_percent'] ?? null,
            'discount_amount' => $rule['discount_amount'] ?? null,
            'base_starts_on' => $rule['base_starts_on'] ?? null,
            'base_ends_on' => $rule['base_ends_on'] ?? null,
            'pricing_waves' => $rule['pricing_waves'] ?? [],
            'races' => $rule['races'] ?? [],
        ])->all());
    }

    private function authorizeManager(Request $request, Event $event): bool
    {
        $isAdmin = $request->user()->role === 'admin';
        $canManageAsAdmin = $isAdmin;
        $canManageAsOwner = ! $isAdmin
            && $event->submitted_by === $request->user()->id
            && $event->status === 'approved';

        abort_unless($canManageAsAdmin || $canManageAsOwner, 403);

        return $isAdmin;
    }

    /**
     * @return array<int, array{from: string, to: string}>
     */
    private function sharedBirthDateRanges(array $first, array $second, Event $event): array
    {
        $eventDate = $event->start_date->copy();
        $ranges = collect([$first, $second])->map(function (array $rule) use ($eventDate): array {
            $eligibilityType = $rule['eligibility_type'];
            if ($eligibilityType === 'birth_year' && $rule['birth_years'] === []) {
                return [
                    'from' => '9999-12-31',
                    'to' => '0001-01-01',
                    'birth_years' => [],
                ];
            }

            $from = match ($eligibilityType) {
                'birth_date' => $rule['birth_date_from'],
                'birth_year' => sprintf('%04d-01-01', min($rule['birth_years'])),
                'age' => ($rule['maximum_age'] ?? null) === null
                    ? '0001-01-01'
                    : $eventDate->copy()->subYears($rule['maximum_age'] + 1)->addDay()->format('Y-m-d'),
            };
            $to = match ($eligibilityType) {
                'birth_date' => $rule['birth_date_to'],
                'birth_year' => sprintf('%04d-12-31', max($rule['birth_years'])),
                'age' => ($rule['minimum_age'] ?? null) === null
                    ? $eventDate->format('Y-m-d')
                    : $eventDate->copy()->subYears($rule['minimum_age'])->format('Y-m-d'),
            };

            return [
                'from' => $from,
                'to' => $to,
                'birth_years' => $eligibilityType === 'birth_year' ? $rule['birth_years'] : [],
            ];
        });
        $from = max($ranges[0]['from'], $ranges[1]['from']);
        $to = min($ranges[0]['to'], $ranges[1]['to']);

        if ($from > $to) {
            return [];
        }

        $overlaps = [];

        for ($year = (int) substr($from, 0, 4); $year <= (int) substr($to, 0, 4); $year++) {
            $firstAllowsYear = $ranges[0]['birth_years'] === [] || in_array($year, $ranges[0]['birth_years'], true);
            $secondAllowsYear = $ranges[1]['birth_years'] === [] || in_array($year, $ranges[1]['birth_years'], true);

            if (! $firstAllowsYear || ! $secondAllowsYear) {
                continue;
            }

            $yearFrom = sprintf('%04d-01-01', $year);
            $yearTo = sprintf('%04d-12-31', $year);
            $overlapFrom = max($from, $yearFrom);
            $overlapTo = min($to, $yearTo);

            if ($overlapFrom <= $overlapTo) {
                $overlaps[] = [
                    'from' => $overlapFrom,
                    'to' => $overlapTo,
                ];
            }
        }

        return $overlaps;
    }

    private function legacyEligibilityType(array $rule): string
    {
        if (($rule['minimum_age'] ?? null) !== null || ($rule['maximum_age'] ?? null) !== null) {
            return 'age';
        }

        if (! empty($rule['birth_years'])) {
            return 'birth_year';
        }

        return 'birth_date';
    }

    /**
     * @return array<int, string>
     */
    private function categoryNames(Event $event): array
    {
        $names = collect($event->categories ?? [])
            ->map(fn (string $name): string => trim($name))
            ->map(fn (string $name): string => trim((string) preg_replace('/\s+— di luar kewajiban$/iu', '', $name)))
            ->map(fn (string $name): string => trim((string) preg_replace('/\s+(Putra|Putri|Campuran)$/iu', '', $name)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $names !== [] ? $names : ['Kategori Umum'];
    }

    /**
     * @param  array<int, string>  $categoryNames
     */
    private function categoryBaseName(string $name, array $categoryNames): string
    {
        $baseName = trim((string) preg_replace('/\s+— di luar kewajiban$/iu', '', trim($name)));
        $baseName = trim((string) preg_replace('/\s+(Putra|Putri|Campuran)$/iu', '', $baseName));

        return in_array($baseName, $categoryNames, true) ? $baseName : $categoryNames[0];
    }

    /**
     * @param  array<int, string>  $genders
     */
    private function genderGroup(array $genders): string
    {
        if (count($genders) === 1 && $genders[0] === 'male') {
            return 'male';
        }

        if (count($genders) === 1 && $genders[0] === 'female') {
            return 'female';
        }

        return 'mixed';
    }
}
