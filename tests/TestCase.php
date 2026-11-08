<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Kode wilayah yang dipakai fixture: 320101 = Kabupaten Bogor, Jawa Barat.
     * Dipilih karena seluruh kecamatannya ada di data wilayah, sehingga NIK
     * yang dibangun helper ini selalu terbaca valid oleh NikParser.
     */
    private const FIXTURE_REGION_CODE = '320101';

    /**
     * Membangun NIK 16 digit yang konsisten dengan gender dan tanggal lahir.
     *
     * Gender dan tanggal lahir di dalam NIK harus cocok dengan biodata peserta,
     * karena aplikasi menolak NIK yang tidak sesuai. Helper ini membuat fixture
     * tes selalu konsisten tanpa menghitung digit secara manual: perempuan
     * ditandai dengan tanggal + 40, dan dua digit terakhir tahun diambil dari
     * tahun lahir.
     *
     * @param  string  $gender  "male" atau "female"
     * @param  string  $birthDate  tanggal dalam format Y-m-d
     * @param  int  $serial  nomor urut 1-9999, untuk membedakan antar-atlet
     */
    protected function nikFor(string $gender, string $birthDate, int $serial = 1): string
    {
        if (! in_array($gender, ['male', 'female'], true)) {
            throw new InvalidArgumentException("Gender fixture tidak dikenal: {$gender}.");
        }

        $date = Carbon::parse($birthDate);
        $day = $date->day + ($gender === 'female' ? 40 : 0);

        return self::FIXTURE_REGION_CODE
            .str_pad((string) $day, 2, '0', STR_PAD_LEFT)
            .$date->format('m')
            .$date->format('y')
            .str_pad((string) min(9999, max(1, $serial)), 4, '0', STR_PAD_LEFT);
    }
}
