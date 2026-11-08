<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRaceHeat extends Model
{
    public const STAGES = [
        'heat' => 'Babak heats',
        'qualification' => 'Babak penyisihan',
        'semi_final' => 'Semifinal',
        'final' => 'Final',
    ];

    public const STATUSES = [
        'draft' => 'Belum diisi',
        'running' => 'Sedang dilombakan',
        'done' => 'Selesai',
    ];

    protected $fillable = [
        'event_id',
        'event_race_group_id',
        'category',
        'race',
        'stage',
        'number',
        'label',
        'status',
        'lanes_count',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'lanes_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(EventRaceEntry::class);
    }

    /** Blok atlet yang membungkus babak ini. */
    public function group(): BelongsTo
    {
        return $this->belongsTo(EventRaceGroup::class, 'event_race_group_id');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? ucfirst((string) $this->stage);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Babak dianggap sudah menghasilkan hasil kalau minimal satu lane
     * sudah diisi statusnya selain "pending".
     */
    public function hasResults(): bool
    {
        return $this->entries->contains(
            fn (EventRaceEntry $entry): bool => $entry->status !== EventRaceEntry::STATUS_PENDING
                || $entry->place !== null
        );
    }
}
