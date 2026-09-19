<?php

use App\Modules\Communications\Domain\Enums\CommunicationStatus;

it('follows the delivery status transition rules', function () {
    expect(CommunicationStatus::Pending->canTransitionTo(CommunicationStatus::Queued))->toBeTrue()
        ->and(CommunicationStatus::Queued->canTransitionTo(CommunicationStatus::Processing))->toBeTrue()
        ->and(CommunicationStatus::Processing->canTransitionTo(CommunicationStatus::Sent))->toBeTrue()
        ->and(CommunicationStatus::Processing->canTransitionTo(CommunicationStatus::Failed))->toBeTrue()
        ->and(CommunicationStatus::Sent->canTransitionTo(CommunicationStatus::Delivered))->toBeTrue()
        ->and(CommunicationStatus::Sent->canTransitionTo(CommunicationStatus::Failed))->toBeFalse()
        ->and(CommunicationStatus::Failed->canTransitionTo(CommunicationStatus::Queued))->toBeFalse()
        ->and(CommunicationStatus::Delivered->canTransitionTo(CommunicationStatus::Sent))->toBeFalse();
});
