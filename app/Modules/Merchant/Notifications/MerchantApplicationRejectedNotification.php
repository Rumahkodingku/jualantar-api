<?php

namespace App\Modules\Merchant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MerchantApplicationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicationNumber,
        public readonly ?string $businessName,
        public readonly string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan merchant ditolak')
            ->greeting('Halo,')
            ->line('Mohon maaf, pengajuan merchant Anda belum dapat disetujui.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Nama usaha: '.($this->businessName ?? '-'))
            ->line('Alasan: '.$this->reason);
    }
}
