<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Domain\Models\Notification;
use App\Shared\Result\Result;

final class GetUnreadNotificationCount
{
    public function __invoke(string $recipientId): Result
    {
        $count = Notification::query()
            ->forRecipient($recipientId)
            ->notExpired()
            ->whereNull('read_at')
            ->count();

        return Result::ok($count);
    }
}
