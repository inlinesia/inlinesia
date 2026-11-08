<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Event $event) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan event baru menunggu persetujuan')
            ->greeting('Ada pengajuan event baru')
            ->line("{$this->event->name} diajukan oleh {$this->event->submitter->name}.")
            ->action('Tinjau pengajuan', route('admin.event-submissions.index'))
            ->line('Event belum ditampilkan kepada publik.');
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_name' => $this->event->name,
            'submitter_name' => $this->event->submitter->name,
            'message' => "Pengajuan event “{$this->event->name}” menunggu persetujuan.",
        ];
    }
}
