<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;

/**
 * Membagikan kategori peserta secara otomatis untuk satu event.
 *
 * Memakai ResolveEventRegistrationCategory yang sama dengan konfirmasi
 * pembayaran manual, jadi hasilnya identik dengan menekan tombol
 * "Konfirmasi pembayaran" satu per satu.
 *
 * Aturan yang dipakai sama dengan EventRegistrationController::store():
 * pendaftaran baru selalu disimpan dengan category null, dan kategori hanya
 * boleh diisi setelah pembayaran terkonfirmasi. Karena itu peserta yang belum
 * lunas tidak ikut diberi kategori.
 */
class AssignRegistrationCategories
{
    public function __construct(
        private readonly ResolveEventRegistrationCategory $resolver,
        private readonly CategoryFeeCalculator $feeCalculator,
        private readonly ReconcileRegistrationFee $feeReconciler,
    ) {}

    /**
     * Jalankan pembagian kategori dan simpan hasilnya.
     *
     * @return array<string, mixed>
     */
    public function assign(Event $event): array
    {
        return DB::transaction(fn (): array => $this->scan($event, persist: true));
    }

    /**
     * Hitung pembagian kategori tanpa menyimpan apa pun.
     *
     * @return array<string, mixed>
     */
    public function preview(Event $event): array
    {
        return $this->scan($event, persist: false);
    }

    /**
     * @return array{assigned: int, unchanged: int, awaiting_payment: int, incomplete: int, no_match: int, distribution: array<string, int>, unassigned: array<int, array{id: int, participant_name: string, reason: string}>}
     */
    private function scan(Event $event, bool $persist): array
    {
        $summary = [
            'assigned' => 0,
            'unchanged' => 0,
            'awaiting_payment' => 0,
            'incomplete' => 0,
            'no_match' => 0,
            'distribution' => [],
            'unassigned' => [],
        ];

        $feeReconciler = $this->feeReconciler;

        EventRegistration::query()
            ->where('event_id', $event->id)
            ->where(function ($query): void {
                $query->whereNull('cancellation_status')
                    ->orWhere('cancellation_status', '!=', 'approved');
            })
            ->orderBy('id')
            ->chunkById(200, function ($registrations) use ($event, $persist, $feeReconciler, &$summary): void {
                foreach ($registrations as $registration) {
                    $blocked = $this->blockedReason($event, $registration);

                    if ($blocked !== null) {
                        $summary[$blocked['bucket']]++;
                        $summary['unassigned'][] = [
                            'id' => $registration->id,
                            'participant_name' => $registration->participant_name,
                            'reason' => $blocked['reason'],
                        ];

                        continue;
                    }

                    $match = $this->resolver->matchingCategory(
                        $event,
                        $registration->date_of_birth->format('Y-m-d'),
                        $registration->gender,
                        $registration->races,
                    );

                    if (! $match) {
                        $summary['no_match']++;
                        $summary['unassigned'][] = [
                            'id' => $registration->id,
                            'participant_name' => $registration->participant_name,
                            'reason' => 'Tidak ada aturan kategori yang cocok dengan data atlet',
                        ];

                        continue;
                    }

                    $categoryName = $match['name'];
                    $summary['distribution'][$categoryName] = ($summary['distribution'][$categoryName] ?? 0) + 1;

                    if ($registration->category === $categoryName) {
                        $summary['unchanged']++;

                        if ($persist) {
                            $hasRecordedPayment = $registration->payment_status === 'paid'
                                || $registration->financialEntries()->exists();
                            if ($hasRecordedPayment) {
                                $feeReconciler->preserveHistoricalPayment($registration);
                            }

                            if ($this->feeCalculator->syncChanged($event, $registration)) {
                                if ($hasRecordedPayment) {
                                    $feeReconciler->reconcile($event, $registration, $event->submitted_by);
                                } else {
                                    $registration->save();
                                }
                            }
                        }

                        continue;
                    }

                    $summary['assigned']++;

                    if ($persist) {
                        $hasRecordedPayment = $registration->payment_status === 'paid'
                            || $registration->financialEntries()->exists();
                        if ($hasRecordedPayment) {
                            $feeReconciler->preserveHistoricalPayment($registration);
                        }

                        $registration->update(['category' => $categoryName]);
                        $this->feeCalculator->sync($event, $registration);
                        if ($hasRecordedPayment) {
                            $feeReconciler->reconcile($event, $registration, $event->submitted_by);
                        } else {
                            $registration->save();
                        }
                    }
                }
            });

        // Urutkan distribusi mengikuti urutan aturan kategori event agar
        // tampilannya tidak melompat-lompat setiap kali jumlahnya berubah.
        $ordered = [];
        foreach ($event->category_rules as $rule) {
            if (isset($summary['distribution'][$rule['name']])) {
                $ordered[$rule['name']] = $summary['distribution'][$rule['name']];
                unset($summary['distribution'][$rule['name']]);
            }
        }
        $summary['distribution'] = $ordered + $summary['distribution'];

        return $summary;
    }

    /**
     * Alasan peserta tidak boleh diberi kategori, atau null bila boleh.
     *
     * @return array{bucket: string, reason: string}|null
     */
    private function blockedReason(Event $event, EventRegistration $registration): ?array
    {
        if ($registration->payment_status !== 'paid') {
            return [
                'bucket' => 'awaiting_payment',
                'reason' => $event->is_free
                    ? 'Pendaftaran gratis belum dikonfirmasi lewat WhatsApp'
                    : 'Pembayaran belum terkonfirmasi',
            ];
        }

        if (! $registration->date_of_birth || ! $registration->gender || empty($registration->races)) {
            return [
                'bucket' => 'incomplete',
                'reason' => 'Tanggal lahir, gender, atau mata lomba belum lengkap',
            ];
        }

        return null;
    }
}
