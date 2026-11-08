<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu blok atlet dalam satu tahap, misalnya "Grup 1 dari kategori pemula
 * putri skate cross: 15 atlet yang dibagi menjadi lima babak".
 *
 * Grup adalah satuan yang disiapkan panitia pada hari lomba. Selama grup
 * belum disiapkan, tombol hasil (Menang / Tidak muncul / DNF / DSQ / Ulangi /
 * Tunda) belum tampil, dan peserta hanya melihat susunan lawannya.
 */
class EventRaceGroup extends Model
{
    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const STATUSES = [
        self::PENDING => 'Menunggu',
        self::RUNNING => 'Sedang berlangsung',
        self::DONE => 'Selesai',
    ];

    protected $fillable = [
        'event_id',
        'category',
        'race',
        'stage',
        'number',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'started_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function heats(): HasMany
    {
        return $this->hasMany(EventRaceHeat::class);
    }

    /** Grup baru disiapkan panitia, belum pernah dijalankan. */
    public function isPrepared(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * Status tampilan: menunggu sampai panitia menekan "Siapkan peserta",
     * berlangsung selama masih ada atlet yang belum menghasilkan keputusan,
     * dan selesai setelah seluruh lane berstatus akhir.
     *
     * Atlet berstatus ditunda dihitung belum selesai karena mereka memang
     * masih harus balapan di urutan paling akhir.
     */
    public function status(): string
    {
        if (! $this->isPrepared() || $this->heats->isEmpty()) {
            return self::PENDING;
        }

        $allDecided = $this->heats->every(
            fn (EventRaceHeat $heat): bool => $heat->entries->every(
                fn (EventRaceEntry $entry): bool => ! $entry->isAwaitingRace()
            )
        );

        return $allDecided ? self::DONE : self::RUNNING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status()] ?? ucfirst((string) $this->status());
    }

    /** Label yang dipakai di halaman urutan lomba dan hari lomba. */
    public function label(): string
    {
        return 'Grup '.$this->number;
    }

    public function stageLabel(): string
    {
        return EventRaceHeat::STAGES[$this->stage] ?? ucfirst((string) $this->stage);
    }
}
