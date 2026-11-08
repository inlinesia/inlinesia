<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Club;
use App\Models\EventRegistration;
use App\Models\User;

/**
 * Menyimpan atlet dan klub ke direktori tanpa perlu didaftarkan dua kali.
 *
 * Pendaftaran lomba tetap menjadi sumber utama biodata, tetapi setiap kali
 * ada pendaftaran baru atau biodata berubah, datanya dicerminkan ke tabel
 * athletes dan clubs. Dengan begitu direktori selalu terisi tanpa membuat
 * peserta harus mengetik NIK yang sama berulang kali.
 */
class SyncRegistrationToDirectory
{
    /**
     * Field yang memengaruhi catatan direktori. Perubahan pada field lain
     * tidak perlu menulis ulang catatan direktori.
     *
     * @var array<int, string>
     */
    private const MIRRORED_FIELDS = [
        'participant_name',
        'gender',
        'date_of_birth',
        'nik',
        'passport_number',
        'nationality',
        'address',
        'whatsapp',
        'guardian_name',
        'club_name',
    ];

    /**
     * Dipanggil oleh model setiap kali ada pendaftaran baru atau biodata
     * berubah, supaya direktori ikut terisi tanpa campur tangan siapa pun.
     */
    public function registrationChanged(EventRegistration $registration): void
    {
        // wasChanged() kosong pada baris yang baru disimpan, jadi pendaftaran
        // baru harus disinkronkan tanpa memeriksa perubahan.
        if (! $registration->wasRecentlyCreated && ! $this->mirroredFieldsDiffer($registration)) {
            return;
        }

        $this->syncRegistration($registration);
    }

    /**
     * Menyalin satu pendaftaran ke direktori tanpa memeriksa perubahan.
     *
     * Dipakai oleh backfill untuk pendaftaran lama, yang tidak lagi punya
     * jejak perubahan yang bisa diperiksa.
     */
    public function syncRegistration(EventRegistration $registration): void
    {
        // Pemilik akun yang menandai siapa yang berhak melihat NIK. Tanpa
        // akun, catatan itu tidak akan bisa dibuka siapa pun, jadi lebih
        // baik tidak dicatat sama sekali.
        if ($registration->user_id === null) {
            return;
        }

        $club = $this->resolveClub($registration);

        $attributes = [
            'name' => (string) $registration->participant_name,
            'gender' => $registration->gender,
            'date_of_birth' => $registration->date_of_birth?->toDateString(),
            'address' => $registration->address,
            'phone' => $registration->whatsapp,
            'guardian_name' => $registration->guardian_name,
            'club_id' => $club?->id,
        ];

        if ($registration->isForeignNational()) {
            $attributes += [
                'nik' => $registration->passport_number,
                'nik_hash' => $registration->passport_hash,
            ];
        } else {
            $attributes += [
                'nik' => $registration->nik,
                'nik_hash' => $registration->nik_hash,
            ];
        }

        $query = Athlete::query()->where('owner_user_id', $registration->user_id);

        $attributes['nik_hash'] === null
            ? $query->whereNull('nik_hash')
            : $query->where('nik_hash', $attributes['nik_hash']);

        $athlete = $query->first();

        $athlete === null
            ? Athlete::query()->create(['owner_user_id' => $registration->user_id, ...$attributes])
            : $athlete->fill($attributes)->save();
    }

    /**
     * Membuat catatan klub bila belum ada, lalu mengembalikannya.
     *
     * Klub hasil pendaftaran lomba tidak punya akun pemilik, jadi owner_user_id
     * dibiarkan kosong sampai ada yang menguasainya.
     */
    public function resolveClub(EventRegistration $registration): ?Club
    {
        // Spasi dibersihkan dulu supaya klub hasil pendaftaran lomba cocok
        // dengan klub yang sama yang mendaftar sendiri lewat direktori.
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $registration->club_name));

        if ($name === '') {
            return null;
        }

        $club = Club::query()->firstOrCreate(['name' => $name], ['city' => null]);

        // Akun pengelola klub biasanya tahu nama klubnya sendiri lewat kolom
        // organization. Bila cocok, akun itulah yang ditandai sebagai pemilik
        // supaya ada yang bisa mengubah data klub. Kontaknya sengaja tidak
        // ikut disalin, karena nomor dan email pribadi tidak boleh
        // dipublikasikan tanpa persetujuan pemiliknya.
        if ($club->owner_user_id !== null || $registration->user_id === null) {
            return $club;
        }

        $manager = User::query()
            ->where('role', 'club_manager')
            ->whereKey($registration->user_id)
            ->first();

        if ($manager === null || trim((string) $manager->organization) === '') {
            return $club;
        }

        if (mb_strtolower(trim($manager->organization)) !== mb_strtolower($name)) {
            return $club;
        }

        $club->fill(['owner_user_id' => $manager->id])->save();

        return $club;
    }

    /**
     * Apakah ada field direktori yang berubah pada pembaruan terakhir.
     *
     * Pemeriksaan ini membuat sinkronisasi murah, karena tidak perlu
     * menulis ulang direktori setiap kali status pembayaran berubah.
     */
    private function mirroredFieldsDiffer(EventRegistration $registration): bool
    {
        if (! $registration->wasChanged()) {
            return false;
        }

        return collect(self::MIRRORED_FIELDS)
            ->contains(fn (string $field): bool => $registration->wasChanged($field));
    }
}
