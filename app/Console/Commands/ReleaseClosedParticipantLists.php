<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Membuka daftar peserta event yang masa pendaftaran sudah habis.
 *
 * Daftar hanya terlihat oleh pengelola sampai perintah ini dijalankan.
 * Karena itu perintah ini dijadwalkan, supaya peserta tidak perlu menunggu
 * pengelola menekan tombol rilis secara manual.
 *
 * Daftar yang pernah dirilis lalu ditarik kembali oleh pengelola tidak
 * tersentuh, karena kolom participants_published_at sudah terisi. Dengan
 * begitu pilihan menutup daftar tidak dibatalkan oleh jadwal ini.
 */
class ReleaseClosedParticipantLists extends Command
{
    protected $signature = 'events:release-participant-lists';

    protected $description = 'Rilis otomatis daftar peserta event yang masa pendaftaran sudah habis';

    public function handle(): int
    {
        $released = 0;

        Event::query()
            ->where('status', 'approved')
            ->where('participants_published', false)
            ->whereNull('participants_published_at')
            ->whereNotNull('registration_deadline')
            ->whereDate('registration_deadline', '<', today())
            ->eachById(function (Event $event) use (&$released): void {
                $event->releaseParticipantList(true);
                $released++;

                $this->components->twoColumnDetail($event->name, 'dirilis');
            });

        $this->components->info($released === 0
            ? 'Tidak ada daftar peserta yang perlu dirilis.'
            : "{$released} daftar peserta berhasil dirilis.");

        return self::SUCCESS;
    }
}
