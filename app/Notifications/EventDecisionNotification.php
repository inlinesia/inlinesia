<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(public Event $event) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->event->status === 'approved';
        $mail = (new MailMessage)
            ->subject($approved ? 'Pengajuan event Anda disetujui' : 'Pembaruan pengajuan event Anda')
            ->greeting("Halo {$notifiable->name}")
            ->line($approved
                ? "Pengajuan event {$this->event->name} telah disetujui dan sekarang tampil pada daftar lomba."
                : "Pengajuan event {$this->event->name} belum disetujui.");

        if (! $approved && $this->event->decision_note) {
            $mail->line("Catatan Admin: {$this->event->decision_note}");
        }

        if ($approved) {
            $mail->action('Lihat daftar lomba', route('events.index'));
        }

        return $mail;
    }

    /**
     * @return array<int|string, string>
     */
    public function toArray(object $notifiable): array
    {
        $approved = $this->event->status === 'approved';

        return [
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'status' => $this->event->status,
            'decision_note' => $this->event->decision_note ?? '',
            'message' => $approved
                ? "Pengajuan event “{$this->event->name}” disetujui dan telah tampil di daftar lomba."
                : "Pengajuan event “{$this->event->name}” belum disetujui. Catatan: {$this->event->decision_note}",
        ];
    }
}
