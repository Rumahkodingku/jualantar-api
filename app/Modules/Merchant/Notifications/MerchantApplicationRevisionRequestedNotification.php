<?php

namespace App\Modules\Merchant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MerchantApplicationRevisionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicationNumber,
        public readonly ?string $businessName,
        public readonly ?string $note,
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
        $message = (new MailMessage)
            ->subject('Perbaikan pengajuan merchant diperlukan')
            ->greeting('Halo,')
            ->line('Pengajuan merchant Anda memerlukan perbaikan data.')
            ->line('Nomor pengajuan: '.$this->applicationNumber)
            ->line('Nama usaha: '.($this->businessName ?? '-'));

        if ($this->note !== null && $this->note !== '') {
            $message->line('Catatan: '.$this->note);
        }

        return $message->line('Silakan perbaiki data Anda dan ajukan kembali.');
    }
}
