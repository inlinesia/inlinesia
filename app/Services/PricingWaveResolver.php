<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Menentukan gelombang tarif (pricing wave) yang berlaku pada tanggal tertentu.
 *
 * Pengelola kejuaraan dapat membuka beberapa gelombang pendaftaran dengan tarif
 * berbeda, misalnya:
 * - Gelombang 1 (early bird), 1-31 Agustus, paket Rp 300.000
 * - Gelombang 2 (normal), 1-30 September, paket Rp 350.000
 * - Gelombang 3 (late), setelah 1 Oktober, paket Rp 400.000
 *
 * Gelombang hanya menimpa nilai tarif; aturan lain seperti required_races dan
 * daftar mata lomba tetap dipakai bersama. Bila tidak ada gelombang yang cocok
 * dengan tanggal pendaftaran, tarif dasar kategori yang dipakai.
 *
 * Tarif dasar bisa punya rentang tanggal sendiri lewat base_starts_on dan
 * base_ends_on. Bila keduanya diisi, tarif dasar hanya berlaku di antara kedua
 * tanggal itu; di luar rentang tersebut tarif dasar tidak dipakai sama sekali
 * supaya peserta tidak diam-diam mendapat harga yang sudah kedaluwarsa.
 */
class PricingWaveResolver
{
    /**
     * Gelombang yang berlaku pada tanggal yang diberikan, atau null bila tidak
     * ada. Gelombang pertama yang cocok (menurut urutan) yang dipakai, sehingga
     * pengelola tidak perlu khawatir soal gelombang yang saling bertumpuk.
     *
     * @param  array<string, mixed>  $rule
     */
    public function activeFor(array $rule, ?CarbonInterface $date = null): ?array
    {
        $date ??= now();
        $day = $date->format('Y-m-d');

        foreach ($this->waves($rule) as $wave) {
            if ($this->dateMatches($day, $wave['starts_on'], $wave['ends_on'])) {
                return $wave;
            }
        }

        return null;
    }

    /**
     * Apakah tarif dasar kategori masih berlaku pada tanggal yang diberikan.
     *
     * Kategori tanpa tanggal tarif dasar dianggap berlaku setiap saat, sehingga
     * seluruh kategori lama tetap memakai tarif dasar seperti sebelumnya.
     *
     * @param  array<string, mixed>  $rule
     */
    public function basePricingAppliesOn(array $rule, ?CarbonInterface $date = null): bool
    {
        $date ??= now();
        $day = $date->format('Y-m-d');

        $startsOn = $this->nullableDate($rule['base_starts_on'] ?? null);
        $endsOn = $this->nullableDate($rule['base_ends_on'] ?? null);

        return $this->dateMatches($day, $startsOn, $endsOn);
    }

    /**
     * Rentang tanggal tarif utama, sudah dirapikan untuk ditampilkan.
     *
     * @param  array<string, mixed>  $rule
     * @return array{starts_on: ?string, ends_on: ?string, is_bounded: bool}
     */
    public function baseRange(array $rule): array
    {
        $startsOn = $this->nullableDate($rule['base_starts_on'] ?? null);
        $endsOn = $this->nullableDate($rule['base_ends_on'] ?? null);

        return [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_bounded' => $startsOn !== null || $endsOn !== null,
        ];
    }

    private function dateMatches(string $day, ?string $startsOn, ?string $endsOn): bool
    {
        if ($startsOn !== null && $day < $startsOn) {
            return false;
        }

        if ($endsOn !== null && $day > $endsOn) {
            return false;
        }

        return true;
    }

    /**
     * Seluruh gelombang yang tersimpan pada satu aturan kategori, sudah
     * dirapikan (label, tanggal, dan tarif).
     *
     * @param  array<string, mixed>  $rule
     * @return array<int, array<string, mixed>>
     */
    public function waves(array $rule): array
    {
        $waves = $rule['pricing_waves'] ?? [];

        if (! is_array($waves)) {
            return [];
        }

        return collect($waves)
            ->filter(fn ($wave): bool => is_array($wave))
            ->map(fn (array $wave): array => [
                'label' => trim((string) ($wave['label'] ?? '')) ?: 'Gelombang',
                'starts_on' => $this->nullableDate($wave['starts_on'] ?? null),
                'ends_on' => $this->nullableDate($wave['ends_on'] ?? null),
                'package_fee' => $this->nullableAmount($wave['package_fee'] ?? null),
                'extra_race_fee' => $this->nullableAmount($wave['extra_race_fee'] ?? null),
                'outside_race_fee' => $this->nullableAmount($wave['outside_race_fee'] ?? null),
                'discount_percent' => $this->nullableAmount($wave['discount_percent'] ?? null),
                'discount_amount' => $this->nullableAmount($wave['discount_amount'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * Apakah kategori ini memakai tarif bertingkat.
     *
     * @param  array<string, mixed>  $rule
     */
    public function hasWaves(array $rule): bool
    {
        return $this->waves($rule) !== [];
    }

    /**
     * Menimpa nilai tarif dasar dengan nilai dari gelombang yang aktif. Hanya
     * kolom yang benar-benar diisi pada gelombang yang menimpa, sehingga
     * pengelola bisa mengosongkan satu kolom untuk mengikuti tarif dasar.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $wave
     * @return array<string, mixed>
     */
    public function apply(array $base, array $wave): array
    {
        foreach (['package_fee', 'extra_race_fee', 'outside_race_fee', 'discount_percent', 'discount_amount'] as $key) {
            if (array_key_exists($key, $wave) && $wave[$key] !== null) {
                $base[$key] = $wave[$key];
            }
        }

        return $base;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableAmount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
