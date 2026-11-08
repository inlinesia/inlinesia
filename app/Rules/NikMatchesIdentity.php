<?php

namespace App\Rules;

use App\Services\NikParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan gender dan tanggal lahir yang diisi peserta benar-benar sesuai
 * dengan angka di dalam NIK.
 *
 * Gender dan tanggal lahir sudah terwakili di dalam NIK, sehingga ketiganya
 * tidak boleh berbeda. Bila berbeda, hampir pasti ada salah ketik pada NIK
 * atau salah pilih gender/tanggal lahir, dan keduanya berbahaya karena
 * menentukan kategori lomba seorang atlet.
 *
 * Aturan ini dipakai berdampingan dengan aturan gender/date_of_birth biasa;
 * pesan kesalahannya menempel pada field NIK supaya peserta tahu bahwa
 * sumber masalahnya ada di nomor tersebut.
 */
class NikMatchesIdentity implements ValidationRule
{
    /**
     * @param  string  $genderField  nama field gender pada request
     * @param  string  $birthDateField  nama field tanggal lahir pada request
     */
    public function __construct(
        private readonly string $genderField = 'gender',
        private readonly string $birthDateField = 'date_of_birth',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{16}$/', $value)) {
            // Format NIK sudah ditangani aturan regex terpisah.
            return;
        }

        $parsed = (new NikParser)->parse($value);

        if (! $parsed['valid']) {
            $fail($parsed['error'] ?? 'NIK tidak dapat dibaca.');

            return;
        }

        $gender = $this->value($this->genderField);

        if (filled($gender) && $gender !== $parsed['gender']) {
            $fail('Jenis kelamin tidak sesuai dengan NIK. Periksa kembali NIK atau pilihan jenis kelamin.');

            return;
        }

        $birthDate = $this->value($this->birthDateField);

        if (filled($birthDate) && $birthDate !== $parsed['birth_date']) {
            $fail('Tanggal lahir tidak sesuai dengan NIK (terbaca '.$parsed['birth_date'].'). Periksa kembali NIK atau tanggal lahir.');
        }
    }

    /**
     * Membaca nilai field lain dari request yang sedang divalidasi.
     */
    private function value(string $field): ?string
    {
        $value = request()->input($field);

        return is_scalar($value) ? (string) $value : null;
    }
}
