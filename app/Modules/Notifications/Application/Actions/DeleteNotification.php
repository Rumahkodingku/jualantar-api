<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Domain\Models\Notification;
use App\Shared\Result\Result;

final class DeleteNotification
{
    public function __invoke(Notification $notification): Result
    {
        $notification->delete();

        return Result::ok(null);
    }
}
