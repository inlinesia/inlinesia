<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sponsor extends Model
{
    use HasFactory;

    public const TYPE_WEB = 'web';

    public const TYPE_EVENT = 'event';

    public const PACKAGE_WEB_BANNER = 'web_banner';

    public const PACKAGE_WEB_MAIN = 'web_main';

    public const PACKAGE_EVENT_LOGO = 'event_logo';

    public const PACKAGE_EVENT_MAIN = 'event_main';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Tarif dasar yang disetujui pemilik; web dihitung per bulan,
     * event dihitung sekali per event.
     *
     * @var array<string, int>
     */
    public const PRICES = [
        self::PACKAGE_WEB_BANNER => 250000,
        self::PACKAGE_WEB_MAIN => 1000000,
        self::PACKAGE_EVENT_LOGO => 150000,
        self::PACKAGE_EVENT_MAIN => 300000,
    ];

    protected $fillable = [
        'type',
        'package',
        'event_id',
        'brand_name',
        'contact_name',
        'phone',
        'email',
        'website_url',
        'logo_path',
        'message',
        'start_date',
        'end_date',
        'amount',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeWeb(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_WEB);
    }

    public function scopeForEvent(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EVENT);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', today());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', today());
            });
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && ($this->start_date === null || $this->start_date->lessThanOrEqualTo(today()))
            && ($this->end_date === null || $this->end_date->greaterThanOrEqualTo(today()));
    }

    public function priceLabel(): string
    {
        $price = self::PRICES[$this->package] ?? 0;

        return 'Rp '.number_format($price, 0, ',', '.').($this->type === self::TYPE_WEB ? '/bln' : '/event');
    }
}
