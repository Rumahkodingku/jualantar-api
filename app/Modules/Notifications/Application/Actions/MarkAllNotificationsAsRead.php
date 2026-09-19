<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Domain\Models\Notification;
use App\Shared\Result\Result;

final class MarkAllNotificationsAsRead
{
    public function __invoke(string $recipientId): Result
    {
        $affected = Notification::query()
            ->forRecipient($recipientId)
            ->notExpired()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return Result::ok($affected);
    }
}
