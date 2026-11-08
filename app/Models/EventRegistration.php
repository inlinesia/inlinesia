<?php

namespace App\Models;

use App\Services\SyncRegistrationToDirectory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['nik', 'nik_hash', 'passport_number', 'passport_hash', 'payment_proof_path', 'participant_change_request'])]
class EventRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'participant_name',
        'nickname',
        'nik',
        'nik_hash',
        'passport_number',
        'passport_hash',
        'nationality',
        'gender',
        'date_of_birth',
        'address',
        'guardian_name',
        'whatsapp',
        'birth_year',
        'club_name',
        'category',
        'fee_total',
        'fee_breakdown',
        'races',
        'status',
        'payment_status',
        'payment_channel',
        'payment_proof_path',
        'confirmed_paid_at',
        'participant_change_request',
        'participant_change_status',
        'participant_change_requested_at',
        'cancellation_status',
        'cancellation_requested_at',
        'cancellation_decided_at',
        'refund_amount',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'nik' => 'encrypted',
            'passport_number' => 'encrypted',
            'date_of_birth' => 'date',
            'races' => 'array',
            'fee_total' => 'integer',
            'fee_breakdown' => 'array',
            'participant_change_request' => 'encrypted:array',
            'participant_change_requested_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'cancellation_decided_at' => 'datetime',
            'refund_amount' => 'integer',
            'refunded_at' => 'datetime',
            'confirmed_paid_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Setiap perubahan biodata langsung dicerminkan ke direktori atlet, supaya
     * daftar publik tidak harus diisi ulang setiap kali ada pendaftaran.
     */
    protected static function booted(): void
    {
        $sync = fn (EventRegistration $registration) => app(SyncRegistrationToDirectory::class)
            ->registrationChanged($registration);

        static::created($sync);
        static::updated($sync);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(EventRegistrationFinancialEntry::class);
    }

    /**
     * Apakah peserta ini bukan warga negara Indonesia.
     *
     * Peserta warga asing tidak punya NIK, sehingga memakai nomor paspor
     * sebagai identitas. Gender dan tanggal lahirnya tetap diisi manual dan
     * tetap menjadi dasar penentuan kategori, sama seperti pesertalain.
     */
    public function isForeignNational(): bool
    {
        return filled($this->passport_number) || filled($this->nationality);
    }

    /**
     * Nomor identitas yang dipakai untuk menampilkan peserta, sudah disamarkan
     * supaya tidak terekspos penuh di daftar.
     */
    public function maskedIdentity(): string
    {
        $number = $this->isForeignNational() ? (string) $this->passport_number : (string) $this->nik;

        if ($number === '') {
            return '—';
        }

        $visible = substr($number, 0, 4);
        $hidden = str_repeat('•', max(0, strlen($number) - 8));

        return $visible.$hidden.substr($number, -4);
    }

    /**
     * Sidik jari identitas untuk pencarian duplikat tanpa membuka isinya.
     */
    public static function identityHash(string $number): string
    {
        return hash_hmac('sha256', $number, (string) config('app.key'));
    }

    public function amountPaid(): int
    {
        if ($this->relationLoaded('financialEntries')) {
            $entries = $this->financialEntries;
        } else {
            $entries = $this->financialEntries()->get(['type', 'amount']);
        }

        if ($entries->isEmpty()) {
            return $this->payment_status === 'paid'
                ? max(0, (int) $this->fee_total - (int) $this->refund_amount)
                : 0;
        }

        return max(0, (int) $entries->sum(fn (EventRegistrationFinancialEntry $entry): int => $entry->type === 'payment'
            ? $entry->amount
            : -$entry->amount));
    }

    public function amountDue(): int
    {
        return max(0, (int) $this->fee_total - $this->amountPaid());
    }
}
