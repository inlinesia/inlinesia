<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Rules\NikMatchesIdentity;
use Illuminate\Validation\ValidationException;

/**
 * Menyiapkan dan memeriksa identitas peserta.
 *
 * Warga negara Indonesia memakai NIK 16 digit. Yang bukan warga negara
 * Indonesia tidak punya NIK, sehingga memakai nomor paspor dan negara asal.
 * Satu peserta hanya boleh memakai salah satu di antaranya.
 *
 * Nomor paspor diperlakukan sama seperti NIK: disimpan pada kolom terenkripsi
 * dan dicuric menjadi hash HMAC supaya duplikat bisa dideteksi tanpa membuka
 * nomor aslinya.
 */
class RegistrationIdentity
{
    /**
     * Normalisasi input identitas menjadi kolom yang siap disimpan.
     *
     * @param  array<string, mixed>  $input
     * @return array{nik: ?string, nik_hash: ?string, passport_number: ?string, passport_hash: ?string, nationality: ?string}
     */
    public function normalize(array $input): array
    {
        $nik = $this->clean($input['nik'] ?? null);
        $passport = $this->clean($input['passport_number'] ?? null);

        return [
            'nik' => $nik,
            'nik_hash' => $nik === null ? null : $this->hash($nik),
            'passport_number' => $passport,
            'passport_hash' => $passport === null ? null : $this->hash($passport),
            'nationality' => $this->clean($input['nationality'] ?? null),
        ];
    }

    /**
     * Pastikan identitas yang dipakai belum terdaftar pada event yang sama
     * dengan akun yang sama.
     *
     * @param  array{nik_hash: ?string, passport_hash: ?string}  $identity
     */
    public function ensureNotDuplicate(
        Event $event,
        int $userId,
        array $identity,
        ?int $exceptRegistrationId = null,
    ): void {
        $hashes = array_values(array_filter([
            $identity['nik_hash'] ?? null,
            $identity['passport_hash'] ?? null,
        ]));

        if ($hashes === []) {
            return;
        }

        $duplicate = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->when($exceptRegistrationId !== null, fn ($query) => $query->whereKeyNot($exceptRegistrationId))
            ->where(function ($query) use ($hashes): void {
                $query->whereIn('nik_hash', $hashes)
                    ->orWhereIn('passport_hash', $hashes);
            })
            ->exists();

        if (! $duplicate) {
            return;
        }

        $field = ($identity['passport_hash'] ?? null) !== null ? 'passport_number' : 'nik';

        throw ValidationException::withMessages([
            $field => $field === 'nik'
                ? 'Atlet dengan NIK tersebut sudah terdaftar. Hubungi Admin atau pemilik kejuaraan untuk mengubah datanya.'
                : 'Atlet dengan nomor paspor tersebut sudah terdaftar pada akun ini.',
        ]);
    }

    /**
     * Aturan validasi untuk field identitas pada form pendaftaran.
     *
     * NIK dan paspor dibuat opsional di sini supaya aturan "wajib salah satu"
     * ditangani assertComplete(), bukan oleh aturan required terpisah.
     *
     * @return array<string, array<int, mixed>>
     */
    public function fieldRules(): array
    {
        return [
            'nik' => ['nullable', 'string', 'max:16', 'regex:/^[0-9]{16}$/', new NikMatchesIdentity],
            'passport_number' => ['nullable', 'string', 'max:60'],
            'nationality' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Pastikan peserta mengisi tepat satu identitas yang sah.
     *
     * Pesan kesalahannya ditempel pada field yang relevan supaya peserta tahu
     * bagian mana yang perlu diperbaiki.
     *
     * @param  array<string, mixed>  $input
     */
    public function assertComplete(array $input): void
    {
        $nik = $this->clean($input['nik'] ?? null);
        $passport = $this->clean($input['passport_number'] ?? null);
        $nationality = $this->clean($input['nationality'] ?? null);

        if ($nik === null && $passport === null) {
            throw ValidationException::withMessages([
                'nik' => 'Isi NIK 16 digit, atau pilih warga negara lain lalu isi nomor paspor.',
            ]);
        }

        if ($nik !== null && $passport !== null) {
            throw ValidationException::withMessages([
                'passport_number' => 'Isi NIK atau nomor paspor, jangan keduanya.',
            ]);
        }

        if ($passport !== null && $nationality === null) {
            throw ValidationException::withMessages([
                'nationality' => 'Negara asal wajib diisi bersama nomor paspor.',
            ]);
        }
    }

    private function hash(string $number): string
    {
        return EventRegistration::identityHash($number);
    }

    private function clean(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
