<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventFinanceTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh catatan keuangan manual (sponsor dan pengeluaran).
 *
 * Pemasukan pendaftaran TIDAK ikut di-seed karena nilainya dibaca langsung dari
 * fee_total pada pendaftaran peserta yang pembayarannya sudah dikonfirmasi.
 * Yang di-seed di sini hanya sumber pemasukan lain dan seluruh pengeluaran.
 *
 * Aman dijalankan berulang: seeder berhenti kalau event-nya sudah punya
 * catatan, jadi tidak pernah menambah duplikat.
 */
class EventFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('role', 'admin')->orderBy('id')->first()
            ?? User::orderBy('id')->first();

        $events = Event::whereIn('status', ['approved', 'completed'])->orderBy('id')->get();

        foreach ($events as $event) {
            if ($event->financeTransactions()->exists()) {
                continue;
            }

            $this->seedFor($event, $author?->id);
        }
    }

    private function seedFor(Event $event, ?int $authorId): void
    {
        // Sebulan terakhir relatif terhadap tanggal event, supaya grafik
        // enam bulan terakhir tidak kosong.
        $anchor = $event->start_date->copy()->subDays(21);

        $rows = [
            ['type' => 'income', 'category' => 'sponsor', 'description' => 'Sponsor utama — Oppo Reno 13 Pro', 'amount' => 15000000, 'days' => 40, 'notes' => 'Kontak: Rizky (marketing Oppo Tulungagung).'],
            ['type' => 'income', 'category' => 'sponsor', 'description' => 'Sponsor pendukung — Kopi Kenangan', 'amount' => 3500000, 'days' => 30, 'notes' => 'Sewa tenda dan banner.'],
            ['type' => 'income', 'category' => 'merchandise', 'description' => 'Penjualan jersey peserta', 'amount' => 2400000, 'days' => 3, 'notes' => '62 pcs.'],
            ['type' => 'expense', 'category' => 'venue', 'description' => 'Sewa lapangan Gajahan', 'amount' => 6000000, 'days' => 12, 'notes' => 'Transfer BCA, bukti sudah di Arsip.'],
            ['type' => 'expense', 'category' => 'equipment', 'description' => 'Sewa gate start dan timing', 'amount' => 2750000, 'days' => 8, 'notes' => null],
            ['type' => 'expense', 'category' => 'marketing', 'description' => 'Poster dan media sosial', 'amount' => 1200000, 'days' => 25, 'notes' => 'Termasuk boost Instagram.'],
            ['type' => 'expense', 'category' => 'prize', 'description' => 'Piala dan medali', 'amount' => 4100000, 'days' => 5, 'notes' => '32 piala, 300 medali.'],
            ['type' => 'expense', 'category' => 'operations', 'description' => 'Konsumsi panitia dan judges', 'amount' => 1850000, 'days' => 4, 'notes' => null],
        ];

        foreach ($rows as $row) {
            EventFinanceTransaction::create([
                'event_id' => $event->id,
                'type' => $row['type'],
                'category' => $row['category'],
                'description' => $row['description'],
                'amount' => $row['amount'],
                'occurred_on' => $anchor->copy()->subDays($row['days'])->toDateString(),
                'notes' => $row['notes'],
                'created_by' => $authorId,
            ]);
        }
    }
}
