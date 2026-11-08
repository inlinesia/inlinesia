<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\Storage;

class ReconcileRegistrationFee
{
    public function preserveHistoricalPayment(EventRegistration $registration): void
    {
        if ($registration->financialEntries()->exists() || $registration->payment_status !== 'paid') {
            return;
        }

        $amount = (int) $registration->fee_total;
        $registration->financialEntries()->create([
            'event_id' => $registration->event_id,
            'type' => 'payment',
            'amount' => max(0, $amount),
            'description' => 'Pembayaran pendaftaran (riwayat lama)',
            'occurred_at' => $registration->confirmed_paid_at ?? $registration->created_at ?? now(),
            'recorded_by' => null,
        ]);

        if ((int) $registration->refund_amount > 0) {
            $registration->financialEntries()->create([
                'event_id' => $registration->event_id,
                'type' => 'refund',
                'amount' => (int) $registration->refund_amount,
                'description' => 'Refund pendaftaran (riwayat lama)',
                'occurred_at' => $registration->refunded_at ?? now(),
                'recorded_by' => null,
            ]);
        }
    }

    public function reconcile(
        Event $event,
        EventRegistration $registration,
        ?int $recordedBy,
    ): void {
        if ($registration->fee_total === null) {
            $registration->save();

            return;
        }

        $amountDue = (int) $registration->fee_total - $registration->amountPaid();
        $oldProofPath = $registration->payment_proof_path;

        if ($amountDue < 0) {
            $refundAmount = abs($amountDue);
            $registration->financialEntries()->create([
                'event_id' => $event->id,
                'type' => 'refund',
                'amount' => $refundAmount,
                'description' => 'Pengembalian selisih setelah perubahan tarif',
                'occurred_at' => now(),
                'recorded_by' => $recordedBy,
            ]);
            $registration->refund_amount = (int) $registration->refund_amount + $refundAmount;
            $registration->refunded_at = now();
            $registration->payment_status = 'paid';
        } elseif ($amountDue > 0) {
            $registration->payment_status = 'pending';
            $registration->payment_proof_path = null;
            $registration->payment_channel = null;
        } else {
            $registration->payment_status = 'paid';
        }

        $registration->save();

        if ($amountDue > 0 && $oldProofPath) {
            Storage::disk('local')->delete($oldProofPath);
        }
    }
}
