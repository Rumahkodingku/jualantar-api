<?php

namespace App\Modules\Merchant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MerchantApplicationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicationNumber,
        public readonly ?string $businessName,
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
            ->subject('Pengajuan merchant disetujui')
            ->greeting('Selamat!')
            ->line('Pengajuan merchant Anda telah disetujui.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Nama usaha: '.($this->businessName ?? '-'))
            ->line('Akun merchant Anda kini aktif.');
    }
}
