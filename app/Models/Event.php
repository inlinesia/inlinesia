<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Event extends Model
{
    protected $fillable = [
        'submitted_by',
        'name',
        'start_date',
        'end_date',
        'venue',
        'venue_address',
        'organizer_whatsapp',
        'scale',
        'regulations',
        'officials',
        'venue_conditions',
        'awards',
        'categories',
        'category_rules',
        'races',
        'registration_open',
        'is_free',
        'participants_published',
        'participants_published_at',
        'registration_deadline',
        'registration_info',
        'fees',
        'payment_bank_name',
        'payment_account_number',
        'payment_account_holder',
        'force_majeure',
        'schedule',
        'status',
        'decision_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_deadline' => 'date',
            'categories' => 'array',
            'category_rules' => 'array',
            'races' => 'array',
            'registration_open' => 'boolean',
            'is_free' => 'boolean',
            'participants_published' => 'boolean',
            'participants_published_at' => 'datetime',
            'schedule' => 'array',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function registrationFinancialEntries(): HasMany
    {
        return $this->hasMany(EventRegistrationFinancialEntry::class);
    }

    /**
     * Catatan keuangan manual (sponsor, pengeluaran, dan sejenisnya).
     * Pemasukan pendaftaran tidak ada di sini karena dihitung dari tabel
     * registrations, bukan dicatat manual.
     */
    public function financeTransactions(): HasMany
    {
        return $this->hasMany(EventFinanceTransaction::class);
    }

    /**
     * Cara perlombaan dijalankan per kategori (jumlah orang per babak,
     * sistem penilaian, jumlah pemenang, format tahap, dan aturan lawan).
     */
    public function raceSettings(): HasMany
    {
        return $this->hasMany(EventCategoryRaceSetting::class);
    }

    public function raceHeats(): HasMany
    {
        return $this->hasMany(EventRaceHeat::class);
    }

    public function raceEntries(): HasMany
    {
        return $this->hasMany(EventRaceEntry::class);
    }

    /**
     * Blok atlet per tahap, kategori, dan mata lomba. Grup adalah satuan
     * yang disiapkan panitia pada hari lomba dan yang tampil di halaman
     * urutan lomba.
     */
    public function raceGroups(): HasMany
    {
        return $this->hasMany(EventRaceGroup::class);
    }

    /**
     * Aturan perlombaan sebuah kategori dan mata lomba, atau bawaan bila
     * belum pernah disimpan. Halaman pengaturan perlombaan selalu punya
     * nilai untuk setiap pasangan kategori + mata lomba sehingga form-nya
     * tidak pernah kosong.
     */
    public function raceSettingFor(string $category, string $race): EventCategoryRaceSetting
    {
        $stored = $this->raceSettings->first(fn (EventCategoryRaceSetting $setting): bool => $setting->category === $category
            && ($setting->race ?? '') === $race)
            // Baris lama disimpan sebelum ada kolom mata lomba.
            ?? $this->raceSettings->first(fn (EventCategoryRaceSetting $setting): bool => $setting->category === $category
                && ($setting->race ?? '') === '');

        return $stored ?? new EventCategoryRaceSetting([
            'event_id' => $this->id,
            'category' => $category,
            'race' => $race,
        ]);
    }

    /**
     * Mata lomba yang dilombakan pada satu kategori. Bila aturan kategori
     * belum menyebut mata lomba, seluruh mata lomba event yang dipakai.
     *
     * @return array<int, string>
     */
    public function racesForCategory(string $category): array
    {
        $rules = $this->category_rules ?: collect($this->categories ?: [])
            ->map(fn (string $name): array => ['name' => $name])
            ->all();

        $rule = collect($rules)->firstWhere('name', $category) ?: [];

        return $this->cleanRaces($rule['races'] ?? $this->races);
    }

    /**
     * Ada kategori yang urutan lombanya sudah dirilis, sehingga layak
     * ditautkan dari dasbor peserta.
     */
    public function hasReleasedRaceOrder(): bool
    {
        return $this->raceSettings->contains(
            fn (EventCategoryRaceSetting $setting): bool => $setting->isReleased()
        );
    }

    /**
     * @param  mixed  $races
     * @return array<int, string>
     */
    private function cleanRaces($races): array
    {
        return collect(is_array($races) ? $races : [])
            ->map(fn ($race): string => trim((string) $race))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pemasukan pendaftaran yang benar-benar sudah dibayar peserta.
     *
     * Hanya pendaftaran dengan payment_status "confirmed" yang dihitung,
     * sehingga angka di halaman keuangan selalu sama dengan-condition yang
     * dipakai halaman "Kelola peserta & pembayaran".
     */
    public function confirmedRegistrationIncome(): int
    {
        $ledgerIncome = (int) $this->registrationFinancialEntries()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');

        $legacyIncome = (int) $this->registrations()
            ->where('payment_status', 'paid')
            ->whereDoesntHave('financialEntries')
            ->sum(DB::raw('fee_total - refund_amount'));

        return $ledgerIncome + $legacyIncome;
    }

    /**
     * Rekening hanya ditampilkan ke peserta kalau nomornya benar-benar ada,
     * supaya tidak ada baris half-filled yang menyesatkan.
     */
    public function hasPaymentAccount(): bool
    {
        return filled($this->payment_account_number);
    }

    /**
     * Masa pendaftaran sudah berakhir.
     *
     * * Event tanpa tenggat dianggap masih buka, karena hanya pengelola yang
     * menentukan kapan pendaftaran ditutup.
     */
    public function registrationClosed(): bool
    {
        return $this->registration_deadline !== null
            && $this->registration_deadline->endOfDay()->isPast();
    }

    /**
     * Daftar peserta sedang bisa dilihat akun lain selain pengelola.
     *
     * Selain tombol rilis manual, daftar juga otomatis terbuka begitu masa
     * pendaftaran habis, karena peserta yang sudah terlanjur mendaftar
     * seharusnya bisa melihat siapa saja lawan-nya. Satu pengecualian:
     * daftar yang pernah dirilis lalu ditarik kembali oleh pengelola tetap
     * tersembunyi.
     */
    public function isParticipantListReleased(): bool
    {
        if ($this->participants_published) {
            return true;
        }

        if ($this->participants_published_at !== null) {
            return false;
        }

        return $this->status === 'approved' && $this->registrationClosed();
    }

    /**
     * Siapa yang boleh mengatur peserta event ini.
     *
     * Pengelola hanya event miliknya sendiri; admin Inlinesia berlaku untuk
     * seluruh event. Pemilik event tetap satu-satunya yang boleh merilis
     * daftar peserta ke publik.
     */
    public function isManagedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->submitted_by === $user->id || $user->isAdmin();
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->submitted_by === $user->id;
    }

    /**
     * Membuka atau menutup daftar peserta.
     *
     * Waktu rilis pertama kali dicatat supaya rilis otomatis di kemudian hari
     * tidak membatalkan keputusan organizer untuk menutup daftar kembali.
     */
    public function releaseParticipantList(bool $released): void
    {
        $this->forceFill([
            'participants_published' => $released,
            'participants_published_at' => $released
                ? ($this->participants_published_at ?? now())
                : $this->participants_published_at,
        ])->save();
    }

    /**
     * Aturan kategori dengan nama tertentu, atau null kalau namanya tidak ada.
     *
     * @return array<string, mixed>|null
     */
    public function categoryRule(string $name): ?array
    {
        foreach ($this->category_rules ?? [] as $rule) {
            if (($rule['name'] ?? null) === $name) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Batas jumlah atlet yang terkonfirmasi pada satu kategori.
     *
     * Yang dihitung hanya atlet yang sudah lunas dan sudah diterima, bukan
     * semua orang yang mengisi formulir. Pendaftaran yang masih menunggu
     * pembayaran belum memakai slot karena uangnya belum masuk.
     */
    public function categoryQuota(string $category): ?int
    {
        $quota = $this->categoryRule($category)['max_registrants'] ?? null;

        return $quota === null || (int) $quota < 1 ? null : (int) $quota;
    }

    /**
     * Jumlah atlet yang sudah lunas dan sudah diterima pada satu kategori.
     *
     * Pembatalan yang sudah disetujui tidak dihitung karena atletnya sudah
     * tidak lagi ikut lomba.
     */
    public function confirmedCategoryCount(string $category): int
    {
        return $this->registrations()
            ->where('category', $category)
            ->where('payment_status', 'paid')
            ->where('status', 'approved')
            ->where(function ($query): void {
                $query->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', '!=', 'approved');
            })
            ->count();
    }

    /**
     * Kuota kategori sudah terpenuhi oleh atlet yang lunas dan diterima.
     */
    public function categoryIsFull(string $category): bool
    {
        $quota = $this->categoryQuota($category);

        return $quota !== null && $this->confirmedCategoryCount($category) >= $quota;
    }

    /**
     * Kategori masih menerima pendaftaran baru.
     *
     * Status punya tiga keadaan: mengikuti aturan kuota (auto), dipaksa
     * terbuka, dan dipaksa tertutup. Keadaan terakhir itulah yang dipakai
     * pengelola untuk menutup kategori tanpa batas peserta, sedangkan keadaan
     * tengah membukanya kembali walau kuota sudah terpenuhi.
     */
    public function isCategoryRegistrationOpen(string $category): bool
    {
        return match ($this->categoryRule($category)['registration_override'] ?? 'auto') {
            'closed' => false,
            'open' => true,
            default => ! $this->categoryIsFull($category),
        };
    }

    /**
     * Sisa slot yang masih tersedia di satu kategori.
     *
     * Mengembalikan null kalau kategorinya tidak punya batas peserta.
     */
    public function categoryRemainingQuota(string $category): ?int
    {
        $quota = $this->categoryQuota($category);

        if ($quota === null) {
            return null;
        }

        return max(0, $quota - $this->confirmedCategoryCount($category));
    }
}
