<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;
use App\Modules\Notifications\Domain\Models\Notification;
use App\Shared\Result\Result;

final class MarkNotificationAsRead
{
    public function __invoke(Notification $notification): Result
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return Result::ok(NotificationData::fromModel($notification));
    }
}
