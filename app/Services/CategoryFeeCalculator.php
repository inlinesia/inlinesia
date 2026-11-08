<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Menghitung harga tiket peserta dari aturan harga yang disimpan pengelola
 * pada setiap kategori lomba.
 *
 * Aturan harga per kategori:
 *  - required_races   : jumlah mata lomba minimal yang wajib diikuti
 *  - package_fee      : harga paket untuk required_races mata lomba pertama
 *  - extra_race_fee   : biaya per mata nominate tambahan di dalam kategori
 *  - outside_race_fee : biaya per mata lomba di luar kategori
 *  - discount_percent : potongan persen dari total, opsional
 *  - discount_amount  : potongan rupiah tetap, opsional
 *
 * Contoh required_races = 2, package_fee = 150000, extra_race_fee = 50000,
 * outside_race_fee = 5000, diskon 10% + 10000:
 *  2 mata lomba dalam kategori  -> 150.000 - 25.000 = 125.000
 *  3 mata lomba dalam kategori  -> 150.000 + 50.000 - 30.000 = 170.000
 *  2 dalam + 1 luar kategori    -> 150.000 + 5.000  - 25.500 = 129.500
 */
class CategoryFeeCalculator
{
    public function __construct(
        private readonly PricingWaveResolver $waves = new PricingWaveResolver,
    ) {}

    /**
     * Aturan harga kategori yang sudah dilengkapi nilai bawaan.
     *
     * Bila kategori memakai gelombang tarif dan ada gelombang yang berlaku
     * pada tanggal $on, nilai tarif dari gelombang tersebut yang dipakai.
     * Kolom yang tidak diisi pada gelombang mengikuti tarif dasar.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function pricing(array $rule, ?CarbonInterface $on = null): array
    {
        $wave = $this->waves->activeFor($rule, $on);

        // Tanpa gelombang yang cocok, tarif dasar hanya dipakai bila rentang
        // tanggalnya masih berlaku. Kalau sudah lewat, semua nominal dikosongkan
        // supaya peserta tidak diam-diam ditagih harga lama.
        $pricing = $this->waves->basePricingAppliesOn($rule, $on)
            ? $this->basePricing($rule)
            : $this->expiredBasePricing($rule);

        if ($wave !== null) {
            $pricing = $this->waves->apply($pricing, $wave);
            $pricing['pricing_wave'] = $wave;
        }

        return $pricing;
    }

    /**
     * Tarif dasar yang rentang tanggalnya sudah tidak berlaku.
     *
     * Nominal dikosongkan, bukan dipaksa nol, supaya hasPricing() mengembalikan
     * false dan kolom fee peserta dikosongkan, bukan tertulis Rp 0 yang menyesatkan.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function expiredBasePricing(array $rule): array
    {
        return [
            'required_races' => max(1, (int) ($rule['required_races'] ?? 1)),
            'package_fee' => 0,
            'extra_race_fee' => 0,
            'outside_race_fee' => 0,
            'discount_percent' => null,
            'discount_amount' => null,
            'pricing_wave' => null,
        ];
    }

    /**
     * Tarif dasar kategori sebelum gelombang tarif dipertimbangkan.
     *
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function basePricing(array $rule): array
    {
        return [
            'required_races' => max(1, (int) ($rule['required_races'] ?? 1)),
            'package_fee' => max(0, (int) ($rule['package_fee'] ?? 0)),
            'extra_race_fee' => max(0, (int) ($rule['extra_race_fee'] ?? 0)),
            'outside_race_fee' => max(0, (int) ($rule['outside_race_fee'] ?? 0)),
            'discount_percent' => $this->nullableAmount($rule['discount_percent'] ?? null),
            'discount_amount' => $this->nullableAmount($rule['discount_amount'] ?? null),
            'pricing_wave' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function requiredRaces(array $rule): int
    {
        return $this->pricing($rule)['required_races'];
    }

    /**
     * Menghitung rincian harga satu peserta pada satu kategori.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<int, string>  $selectedRaces
     * @return array<string, mixed>
     */
    public function breakdown(array $rule, array $selectedRaces, ?CarbonInterface $on = null): array
    {
        $pricing = $this->pricing($rule, $on);
        $categoryRaces = array_map('strval', $rule['races'] ?? []);
        $selected = array_values(array_map('strval', $selectedRaces));

        $inCategory = array_values(array_intersect($selected, $categoryRaces));
        $outside = array_values(array_diff($selected, $categoryRaces));

        if ($rule['outside_obligation'] ?? false) {
            $outside = $selected;
            $inCategory = [];
        }

        $extraRaces = ($rule['outside_obligation'] ?? false)
            ? 0
            : max(0, count($inCategory) - $pricing['required_races']);
        $extraTotal = $extraRaces * $pricing['extra_race_fee'];
        $outsideTotal = count($outside) * $pricing['outside_race_fee'];

        $subtotal = $pricing['package_fee'] + $extraTotal + $outsideTotal;

        $percentDiscount = $pricing['discount_percent'] === null
            ? 0
            : (int) round($subtotal * $pricing['discount_percent'] / 100);
        $amountDiscount = $pricing['discount_amount'] ?? 0;
        $discountTotal = $percentDiscount + $amountDiscount;
        $total = max(0, $subtotal - $discountTotal);

        return [
            'category' => (string) ($rule['name'] ?? ''),
            'required_races' => $pricing['required_races'],
            'in_category_races' => $inCategory,
            'outside_races' => $outside,
            'package_fee' => $pricing['package_fee'],
            'extra_races' => $extraRaces,
            'extra_race_fee' => $pricing['extra_race_fee'],
            'extra_race_total' => $extraTotal,
            'outside_race_fee' => $pricing['outside_race_fee'],
            'outside_race_total' => $outsideTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $pricing['discount_percent'],
            'discount_amount' => $amountDiscount,
            'discount_total' => $discountTotal,
            'total' => $total,
            'pricing_wave' => $pricing['pricing_wave'],
            'pricing_lock_expires_on' => $pricing['pricing_wave']['ends_on']
                ?? $this->waves->baseRange($rule)['ends_on'],
        ];
    }

    /**
     * Harga yang harus dibayar peserta pada saat konfirmasi pembayaran.
     *
     * Harga daftar dikunci sejak peserta mendaftar, tetapi kunci itu hanya
     * berlaku sampai hari terakhir periode harga yang dipakai saat pendaftaran.
     * Kalau peserta membayar lewat dari itu, tarif dinaikkan mengikuti periode
     * harga yang sedang berlaku sehingga pengelola tidak menanggung selisih
     * Early Bird. Bila saat itu tidak ada periode harga yang terbuka, harga
     * daftar tetap dipakai supaya peserta tidak mendadak menjadi gratis.
     *
     * Pengelola bisa memaksa pilihan lain lewat $basis ketika mengonfirmasi
     * pembayaran, misalnya memberi harga Early Bird sebagai kebijakan baik,
     * atau sebaliknya menagih harga periode berjalan kepada peserta yang telat.
     *
     * @param  'auto'|'registration'|'current'|'manual'  $basis
     * @return array<string, mixed>
     */
    public function breakdownForPayment(
        Event $event,
        EventRegistration $registration,
        ?CarbonInterface $paidAt = null,
        string $basis = 'auto',
    ): array {
        $rule = $this->ruleByName($event, $registration->category);

        if (! $rule) {
            return [];
        }

        $paidAt ??= now();
        $races = $registration->races ?? [];
        $locked = $this->breakdown($rule, $races, $this->registrationDate($registration));

        return match ($basis) {
            'registration' => $locked,
            'current' => $this->breakdownAtOpenPeriod($rule, $races, $paidAt, $locked),
            default => $this->resolveAutomaticBasis($rule, $races, $paidAt, $locked),
        };
    }

    /**
     * Harga pada periode yang sedang berlaku, atau harga daftar bila saat itu
     * tidak ada periode harga yang terbuka.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<int, string>  $races
     * @param  array<string, mixed>  $locked
     * @return array<string, mixed>
     */
    private function breakdownAtOpenPeriod(
        array $rule,
        array $races,
        CarbonInterface $paidAt,
        array $locked,
    ): array {
        $current = $this->breakdown($rule, $races, $paidAt);
        $periodOpen = $current['pricing_wave'] !== null
            || $this->waves->basePricingAppliesOn($rule, $paidAt);

        return $periodOpen ? $current : $locked;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<int, string>  $races
     * @param  array<string, mixed>  $locked
     * @return array<string, mixed>
     */
    private function resolveAutomaticBasis(
        array $rule,
        array $races,
        CarbonInterface $paidAt,
        array $locked,
    ): array {
        $lockExpiresOn = $locked['pricing_lock_expires_on'] ?? null;

        if ($lockExpiresOn === null || $paidAt->format('Y-m-d') <= $lockExpiresOn) {
            return $locked;
        }

        return $this->breakdownAtOpenPeriod($rule, $races, $paidAt, $locked);
    }

    /**
     * Mencari aturan kategori berdasarkan nama kategori yang tersimpan pada
     * peserta. Digunakan untuk menghitung ulang harga peserta yang kategorinya
     * sudah ditetapkan.
     *
     * @return array<string, mixed>|null
     */
    public function ruleByName(Event $event, ?string $categoryName): ?array
    {
        if (! $categoryName) {
            return null;
        }

        foreach ($event->category_rules ?: [] as $rule) {
            if (($rule['name'] ?? null) === $categoryName) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Mengisi fee_total dan fee_breakdown pada model peserta tanpa menyimpan.
     *
     * Kalau kategorinya belum ditetapkan, atau kategori itu belum punya
     * aturan harga sama sekali, kedua kolom dikosongkan supaya nominal
     * fiktif tidak pernah muncul di daftar peserta.
     */
    public function sync(Event $event, EventRegistration $registration): void
    {
        $rule = $this->ruleByName($event, $registration->category);

        if (! $rule || ! $this->hasAnyPricing($rule)) {
            $registration->fee_total = null;
            $registration->fee_breakdown = null;

            return;
        }

        // Tarif dihitung memakai tanggal pendaftaran peserta, bukan tanggal
        // hari ini, supaya peserta yang mendaftar di gelombang awal tetap
        // memegang tarif gelombang tersebut meski tarif kemudian naik.
        $breakdown = $this->breakdown($rule, $registration->races ?? [], $this->registrationDate($registration));
        $registration->fee_total = $breakdown['total'];
        $registration->fee_breakdown = $breakdown;
    }

    /**
     * Tanggal yang dipakai untuk memilih gelombang tarif seorang peserta.
     * Pendaftaran yang belum tersimpan memakai waktu sekarang.
     */
    private function registrationDate(EventRegistration $registration): CarbonInterface
    {
        return $registration->exists
            ? ($registration->created_at ?? now())
            : now();
    }

    /**
     * Apakah kategori ini punya aturan harga yang perlu dihitung?
     *
     * @param  array<string, mixed>  $rule
     */
    public function hasPricing(array $rule): bool
    {
        $pricing = $this->pricing($rule);

        return $pricing['package_fee'] > 0
            || $pricing['extra_race_fee'] > 0
            || $pricing['outside_race_fee'] > 0
            || $pricing['discount_percent'] !== null
            || $pricing['discount_amount'] !== null;
    }

    /**
     * Apakah kategori ini punya harga sama sekali, baik dari tarif dasar
     * maupun dari salah satu gelombang tarif.
     *
     * @param  array<string, mixed>  $rule
     */
    public function hasAnyPricing(array $rule): bool
    {
        if ($this->hasPricing($rule)) {
            return true;
        }

        foreach ($this->waves->waves($rule) as $wave) {
            if ($this->hasPricing([...$rule, ...array_filter($wave, fn ($value): bool => $value !== null)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sama seperti sync(), tetapi hanya melaporkan perubahan supaya pemanggil
     * bisa menghindari penulisan database yang tidak perlu.
     */
    public function syncChanged(Event $event, EventRegistration $registration): bool
    {
        $before = [$registration->fee_total, $registration->fee_breakdown];
        $this->sync($event, $registration);

        return $before !== [$registration->fee_total, $registration->fee_breakdown];
    }

    /**
     * Menghitung ulang harga seluruh peserta yang sudah punya kategori.
     *
     * Dipakai setelah aturan harga kategori berubah supaya daftar peserta
     * ikut memakai harga terbaru.
     *
     * @return int jumlah peserta yang diperbarui
     */
    public function refreshEvent(
        Event $event,
        ?ReconcileRegistrationFee $feeReconciler = null,
    ): int {
        $updated = 0;
        $feeReconciler ??= app(ReconcileRegistrationFee::class);

        EventRegistration::query()
            ->where('event_id', $event->id)
            ->whereNotNull('category')
            ->chunkById(200, function ($registrations) use ($event, $feeReconciler, &$updated): void {
                foreach ($registrations as $registration) {
                    $hasRecordedPayment = $registration->payment_status === 'paid'
                        || $registration->financialEntries()->exists();
                    if ($hasRecordedPayment) {
                        $feeReconciler->preserveHistoricalPayment($registration);
                    }

                    if ($this->syncChanged($event, $registration)) {
                        if ($hasRecordedPayment) {
                            $feeReconciler->reconcile(
                                $event,
                                $registration,
                                $event->submitted_by,
                            );
                        } else {
                            $registration->save();
                        }

                        $updated++;
                    }
                }
            });

        return $updated;
    }

    public static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Daftar gelombang tarif yang tersusun siap tampil, lengkap dengan tarif
     * efektif dan status apakah gelombang itu sedang berlaku.
     *
     * @param  array<string, mixed>  $rule
     * @param  CarbonInterface|null  $on  tanggal acuan status "berlaku"
     * @return array<int, array<string, mixed>>
     */
    public function waveSummaries(array $rule, ?CarbonInterface $on = null): array
    {
        $on ??= now();
        $waves = $this->waves->waves($rule);
        $active = $this->waves->activeFor($rule, $on);

        return collect($waves)->map(function (array $wave) use ($rule, $active): array {
            // Harga efektif gelombang dihitung dari tarif dasar tanpa memanggil
            // pricing() lagi, karena pricing() akan memilih ulang gelombang yang
            // aktif hari ini dan menimpa nilai gelombang yang sedang ditampilkan.
            $effective = $this->waves->apply($this->basePricing($rule), $wave);

            return [
                'label' => $wave['label'],
                'starts_on' => $wave['starts_on'],
                'ends_on' => $wave['ends_on'],
                'package_fee' => $effective['package_fee'],
                'extra_race_fee' => $effective['extra_race_fee'],
                'outside_race_fee' => $effective['outside_race_fee'],
                'discount_percent' => $effective['discount_percent'],
                'discount_amount' => $effective['discount_amount'],
                'is_active' => $active === $wave,
            ];
        })->values()->all();
    }

    /**
     * Label rentang tanggal gelombang untuk ditampilkan ke peserta.
     *
     * @param  array<string, mixed>  $wave
     */
    public function waveRangeLabel(array $wave): string
    {
        $format = fn (?string $date): ?string => $date === null ? null : Carbon::parse($date)->format('d/m/Y');
        $from = $format($wave['starts_on'] ?? null);
        $to = $format($wave['ends_on'] ?? null);

        return match (true) {
            $from !== null && $to !== null => "{$from}–{$to}",
            $from !== null => "mulai {$from}",
            $to !== null => "sampai {$to}",
            default => 'tanpa batas tanggal',
        };
    }

    /**
     * Ringkasan singkat aturan harga untuk ditampilkan di halaman publik.
     *
     * @param  array<string, mixed>  $rule
     */
    public function summary(array $rule): string
    {
        $pricing = $this->pricing($rule);
        $parts = [];

        if ($rule['outside_obligation'] ?? false) {
            return 'Kategori tambahan di luar kewajiban · '.self::rupiah($pricing['outside_race_fee']).' per mata lomba';
        }

        if (! $this->hasPricing($rule)) {
            return $pricing['required_races'].' mata lomba wajib · harga belum diatur';
        }

        $parts[] = $pricing['package_fee'] > 0
            ? 'paket '.self::rupiah($pricing['package_fee']).' untuk '.$pricing['required_races'].' mata lomba'
            : $pricing['required_races'].' mata lomba wajib';

        if ($pricing['extra_race_fee'] > 0) {
            $parts[] = 'tambahan '.self::rupiah($pricing['extra_race_fee']).' per mata lomba tambahan';
        }

        if ($pricing['outside_race_fee'] > 0) {
            $parts[] = self::rupiah($pricing['outside_race_fee']).' per mata lomba di luar kategori';
        }

        $discounts = [];
        if ($pricing['discount_percent'] !== null) {
            $discounts[] = $pricing['discount_percent'].'%';
        }
        if ($pricing['discount_amount'] !== null) {
            $discounts[] = self::rupiah($pricing['discount_amount']);
        }
        if ($discounts !== []) {
            $parts[] = 'diskon '.implode(' + ', $discounts);
        }

        return implode(' · ', $parts);
    }

    private function nullableAmount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
