<?php

namespace App\Notifications;

use App\Models\Sponsor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSponsorRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public Sponsor $sponsor) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan sponsor baru menunggu tinjauan')
            ->greeting('Ada pengajuan sponsor baru')
            ->line("Brand {$this->sponsor->brand_name} mengajukan sponsor {$this->sponsor->priceLabel()}.")
            ->line('Kontak: '.$this->sponsor->contact_name.' · '.$this->sponsor->phone)
            ->action('Tinjau pengajuan', route('admin.sponsors.index'));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sponsor_id' => $this->sponsor->id,
            'brand_name' => $this->sponsor->brand_name,
            'type' => $this->sponsor->type,
            'package' => $this->sponsor->package,
            'message' => "Pengajuan sponsor {$this->sponsor->brand_name} menunggu tinjauan.",
        ];
    }
}
