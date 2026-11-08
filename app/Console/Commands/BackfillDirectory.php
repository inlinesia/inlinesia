<?php

namespace App\Console\Commands;

use App\Models\EventRegistration;
use App\Services\SyncRegistrationToDirectory;
use Illuminate\Console\Command;

/**
 * Menyalin seluruh pendaftaran lomba yang sudah ada ke direktori.
 *
 * Pencerminkan otomatis hanya berjalan pada pendaftaran yang disimpan setelah
 * fitur direktori ada, sehingga peserta lama tidak muncul sendiri. Perintah ini
 * perlu dijalankan sekali setelah tabel clubs dan athletes dibuat, dan boleh
 * dijalankan ulang kapan saja karena pencocokannya idempoten.
 */
class BackfillDirectory extends Command
{
    protected $signature = 'directory:backfill';

    protected $description = 'Isi direktori klub dan peserta dari seluruh pendaftaran lomba yang sudah ada';

    public function handle(SyncRegistrationToDirectory $sync): int
    {
        $total = 0;
        $lastId = 0;

        do {
            // Diproses per halaman supaya perintah ini tetap ringan walau
            // pendaftarannya sudah puluhan ribu baris.
            $registrations = EventRegistration::query()
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get();

            foreach ($registrations as $registration) {
                $sync->syncRegistration($registration);
                $lastId = $registration->id;
                $total++;
            }
        } while ($registrations->count() === 500);

        $this->components->info($total === 0
            ? 'Belum ada pendaftaran yang perlu dicerminkan.'
            : "{$total} pendaftaran sudah dicerminkan ke direktori.");

        return self::SUCCESS;
    }
}
