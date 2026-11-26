<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CancellationRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public EventRegistration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Permintaan pembatalan peserta menunggu keputusan')
            ->greeting('Ada permintaan pembatalan peserta')
            ->line("Atlet {$this->registration->participant_name} pada event {$this->event->name} mengajukan pembatalan.")
            ->line('Alasan: '.($this->registration->cancellation_reason ?: 'Tidak diisi.'))
            ->action('Tinjau pengajuan', route('events.registrations.index', $this->event))
            ->line('Setujui atau tolak permintaan ini dari halaman kelola peserta.');
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'registration_id' => $this->registration->id,
            'participant_name' => $this->registration->participant_name,
            'reason' => $this->registration->cancellation_reason,
            'message' => "Permintaan pembatalan {$this->registration->participant_name} pada event “{$this->event->name}” menunggu keputusan.",
        ];
    }
}
