<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use JsonException;
use RuntimeException;

/**
 * Membaca data diri dan wilayah dari Nomor Induk Kependudukan (NIK).
 *
 * Struktur 16 digit NIK:
 * - 2 digit pertama: kode provinsi
 * - 2 digit kedua: kode kabupaten/kota
 * - 2 digit ketiga: kode kecamatan
 * - 6 digit berikutnya: tanggal lahir (2) + bulan (2) + tahun (2)
 *   - Perempuan: tanggal lahir ditambah 40
 * - 4 digit terakhir: nomor urut pendaftaran
 *
 * Data kode wilayah dibaca dari database/seeders/data/wilayah.json yang
 * diunduh dari API kodewilayah.web.id dan disimpan sebagai cache statis.
 */
class NikParser
{
    /**
     * Data kode wilayah yang sudah dimuat, dibagi antar-instance.
     *
     * @var array{provinces: array<string, string>, regencies: array<string, string>, districts: array<string, string>}|null
     */
    private static ?array $regionData = null;

    /**
     * Membaca seluruh makna satu NIK.
     *
     * @return array{
     *     valid: bool,
     *     province_code: string,
     *     province_name: string|null,
     *     regency_code: string,
     *     regency_name: string|null,
     *     district_code: string,
     *     district_name: string|null,
     *     gender: string|null,
     *     birth_date: string|null,
     *     birth_year: int|null,
     *     serial_number: string,
     *     error: string|null
     * }
     */
    public function parse(string $nik): array
    {
        $nik = preg_replace('/\s+/', '', $nik) ?? '';

        $provinceCode = substr($nik, 0, 2);
        $regencyCode = substr($nik, 0, 4);
        $districtCode = substr($nik, 0, 6);
        $serialNumber = substr($nik, 12, 4);

        $blank = [
            'valid' => false,
            'province_code' => $provinceCode,
            'province_name' => null,
            'regency_code' => $regencyCode,
            'regency_name' => null,
            'district_code' => $districtCode,
            'district_name' => null,
            'gender' => null,
            'birth_date' => null,
            'birth_year' => null,
            'serial_number' => $serialNumber,
            'error' => null,
        ];

        if (! preg_match('/^\d{16}$/', $nik)) {
            return [...$blank, 'error' => 'NIK harus terdiri dari 16 digit angka.'];
        }

        $region = [
            'province_name' => $this->lookup('provinces', $provinceCode),
            'regency_name' => $this->lookup('regencies', $regencyCode),
            'district_name' => $this->lookup('districts', $districtCode),
        ];

        if ($region['province_name'] === null) {
            return [...$blank, ...$region, 'error' => 'Kode provinsi pada NIK tidak dikenali.'];
        }

        if ($region['regency_name'] === null) {
            return [...$blank, ...$region, 'error' => 'Kode kabupaten/kota pada NIK tidak dikenali.'];
        }

        if ($region['district_name'] === null) {
            return [...$blank, ...$region, 'error' => 'Kode kecamatan pada NIK tidak dikenali.'];
        }

        $birth = $this->extractBirthDate(substr($nik, 6, 6));

        if (! $birth['valid']) {
            return [
                ...$blank,
                ...$region,
                'gender' => $birth['gender'],
                'error' => $birth['error'],
            ];
        }

        return [
            'valid' => true,
            'province_code' => $provinceCode,
            'province_name' => $region['province_name'],
            'regency_code' => $regencyCode,
            'regency_name' => $region['regency_name'],
            'district_code' => $districtCode,
            'district_name' => $region['district_name'],
            'gender' => $birth['gender'],
            'birth_date' => $birth['birth_date'],
            'birth_year' => $birth['birth_year'],
            'serial_number' => $serialNumber,
            'error' => null,
        ];
    }

    /**
     * Mengekstrak gender, tanggal lahir, dan tahun lahir dari 6 digit tanggal NIK.
     *
     * @return array{valid: bool, gender: string|null, birth_date: string|null, birth_year: int|null, error: string|null}
     */
    private function extractBirthDate(string $segment): array
    {
        $day = (int) substr($segment, 0, 2);
        $month = (int) substr($segment, 2, 2);
        $shortYear = (int) substr($segment, 4, 2);

        if ($month < 1 || $month > 12) {
            return [
                'valid' => false,
                'gender' => null,
                'birth_date' => null,
                'birth_year' => null,
                'error' => 'Bulan lahir pada NIK tidak valid.',
            ];
        }

        // Tanggal di atas 40 menandakan pemilik NIK perempuan; tanggal aslinya
        // adalah angka tersebut dikurangi 40.
        $isFemale = $day > 40;
        $actualDay = $isFemale ? $day - 40 : $day;

        if ($actualDay < 1 || $actualDay > 31) {
            return [
                'valid' => false,
                'gender' => $isFemale ? 'female' : 'male',
                'birth_date' => null,
                'birth_year' => null,
                'error' => 'Tanggal lahir pada NIK tidak valid.',
            ];
        }

        $fullYear = $this->expandYear($shortYear);
        $date = Carbon::create($fullYear, $month, $actualDay);

        if ($date === null) {
            return [
                'valid' => false,
                'gender' => $isFemale ? 'female' : 'male',
                'birth_date' => null,
                'birth_year' => null,
                'error' => 'Tanggal lahir pada NIK tidak sah pada kalender.',
            ];
        }

        return [
            'valid' => true,
            'gender' => $isFemale ? 'female' : 'male',
            'birth_date' => $date->format('Y-m-d'),
            'birth_year' => $fullYear,
            'error' => null,
        ];
    }

    /**
     * Mengubah dua digit tahun menjadi tahun penuh.
     *
     * 00-49 berarti 2000-2049, dan 50-99 berarti 1950-1999. Dengan begitu
     * atlet muda (misalnya lahir 2012) dan orang tua (misalnya 1975) sama-sama
     * terbaca benar.
     */
    private function expandYear(int $shortYear): int
    {
        return $shortYear <= 49 ? 2000 + $shortYear : 1900 + $shortYear;
    }

    /**
     * Alamat yang direkomendasikan dari data wilayah NIK. Dipakai sebagai
     * nilai awal kolom alamat, yang tetap boleh disunting peserta karena
     * perpindahan domisili tidak mengubah NIK.
     *
     * @param  array<string, mixed>  $parsed  hasil dari parse()
     */
    public function suggestAddress(array $parsed): ?string
    {
        $parts = array_values(array_filter([
            $parsed['district_name'] ?? null,
            $parsed['regency_name'] ?? null,
            $parsed['province_name'] ?? null,
        ], fn ($part): bool => filled($part)));

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Mencari nama wilayah dari kode yang diberikan.
     *
     * @param  string  $level  salah satu dari "provinces", "regencies", "districts"
     */
    private function lookup(string $level, string $code): ?string
    {
        return $this->loadRegionData()[$level][$code] ?? null;
    }

    /**
     * Memuat data wilayah dari berkas JSON sekali per proses.
     *
     * @return array{provinces: array<string, string>, regencies: array<string, string>, districts: array<string, string>}
     */
    private function loadRegionData(): array
    {
        if (self::$regionData !== null) {
            return self::$regionData;
        }

        $path = database_path('seeders/data/wilayah.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                'Data kode wilayah tidak ditemukan di database/seeders/data/wilayah.json. Jalankan scripts/fetch-wilayah-data.php untuk mengunduhnya.',
            );
        }

        try {
            /** @var array{provinces: array<string, string>, regencies: array<string, string>, districts: array<string, string>} $data */
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Data kode wilayah tidak dapat dibaca.', previous: $exception);
        }

        return self::$regionData = $data;
    }
}
