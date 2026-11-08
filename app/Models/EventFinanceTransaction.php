<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFinanceTransaction extends Model
{
    /**
     * Kategori pemasukan yang dicatat manual. Pemasukan pendaftaran tidak
     * termasuk daftar ini karena dihitung dari data peserta.
     *
     * @var array<int, string>
     */
    public const INCOME_CATEGORIES = [
        'sponsor' => 'Sponsor',
        'merchandise' => 'Penjualan merchandise',
        'donation' => 'Donasi',
        'other_income' => 'Pemasukan lain',
    ];

    /**
     * @var array<int, string>
     */
    public const EXPENSE_CATEGORIES = [
        'venue' => 'Sewa lokasi',
        'equipment' => 'Peralatan dan raket',
        'operations' => 'Operasional panitia',
        'marketing' => 'Promosi dan publikasi',
        'prize' => 'Hadiah dan piala',
        'other_expense' => 'Pengeluaran lain',
    ];

    protected $fillable = [
        'event_id',
        'type',
        'category',
        'description',
        'amount',
        'occurred_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function categoryLabel(): string
    {
        return self::INCOME_CATEGORIES[$this->category]
            ?? self::EXPENSE_CATEGORIES[$this->category]
            ?? 'Lainnya';
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function categoriesFor(string $type): array
    {
        return $type === 'expense' ? self::EXPENSE_CATEGORIES : self::INCOME_CATEGORIES;
    }
}
