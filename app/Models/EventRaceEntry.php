<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRaceEntry extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_DNF = 'dnf';

    public const STATUS_DSQ = 'dsq';

    /**
     * Atlet ditunda panitia: tidak balapan pada gilirannya, tetapi dipindahkan
     * ke urutan paling akhir pada kategori dan mata lomba yang sama.
     */
    public const STATUS_POSTPONED = 'postponed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Belum dilombakan',
        self::STATUS_FINISHED => 'Finis',
        self::STATUS_NO_SHOW => 'Tidak muncul',
        self::STATUS_DNF => 'Tidak menyelesaikan (DNF)',
        self::STATUS_DSQ => 'Diskualifikasi (DSQ)',
        self::STATUS_POSTPONED => 'Ditunda',
    ];

    protected $fillable = [
        'event_race_heat_id',
        'event_id',
        'event_registration_id',
        'category',
        'race',
        'lane',
        'status',
        'place',
        'time_ms',
        'points',
        'is_qualified',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'lane' => 'integer',
            'place' => 'integer',
            'time_ms' => 'integer',
            'points' => 'integer',
            'is_qualified' => 'boolean',
        ];
    }

    public function heat(): BelongsTo
    {
        return $this->belongsTo(EventRaceHeat::class, 'event_race_heat_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Atlet ini masih harus menghasilkan keputusan. Baik yang sama sekali
     * belum balapan maupun yang ditunda dianggap belum selesai, supaya babak
     * dan grup tidak ditutup sebelum semua atlet memperoleh hasil.
     */
    public function isAwaitingRace(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_POSTPONED;
    }

    /**
     * Waktu dalam milidetik dituliskan dalam bentuk mm:ss.ddd, misalnya
     * "1:23.45" atau "48.7". Nilai yang tidak bisa dibaca dianggap tidak diisi
     * supaya satu salah ketik tidak membuat hasil heat dianggap selesai.
     */
    public static function parseTime(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (! preg_match('/^(?:(\d+):)?([0-9]{1,2})(?:[.,](\d{1,3}))?$/', $raw, $m)) {
            return null;
        }

        $minutes = (int) ($m[1] ?? 0);
        $seconds = (int) $m[2];
        $fraction = str_pad($m[3] ?? '', 3, '0');

        if ($seconds > 59) {
            return null;
        }

        return (($minutes * 60 + $seconds) * 1000) + (int) $fraction;
    }

    public static function formatTime(?int $milliseconds): ?string
    {
        if ($milliseconds === null) {
            return null;
        }

        $minutes = intdiv($milliseconds, 60000);
        $seconds = intdiv($milliseconds % 60000, 1000);
        $fraction = $milliseconds % 1000;

        return $minutes > 0
            ? sprintf('%d:%02d.%03d', $minutes, $seconds, $fraction)
            : sprintf('%d.%03d', $seconds, $fraction);
    }
}
