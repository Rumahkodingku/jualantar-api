<?php

use App\Modules\Communications\Application\Jobs\DeliverCommunication;
use App\Modules\Communications\Application\Services\CommunicationDispatcher;
use App\Modules\Communications\Application\Services\DeliveryStatusService;
use App\Modules\Communications\Contracts\Channels\ProviderSendResult;
use App\Modules\Communications\Domain\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Enums\DeliveryAttemptStatus;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;
use App\Modules\Communications\Domain\Models\Communication;
use App\Modules\Communications\Infrastructure\Channels\Email\CommunicationsMailable;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailProvider;
use App\Modules\Communications\Tests\Support\FakeEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function bindFakeEmailProvider(ProviderSendResult $result): FakeEmailProvider
{
    $fake = new FakeEmailProvider($result);
    app()->instance(EmailProvider::class, $fake);
    app()->forgetInstance(CommunicationDispatcher::class);

    return $fake;
}

function deliverCommunication(Communication $communication): void
{
    (new DeliverCommunication($communication->id))->handle(
        app(CommunicationDispatcher::class),
        app(DeliveryStatusService::class),
    );
}

it('marks a communication as sent when the provider accepts', function () {
    $fake = bindFakeEmailProvider(ProviderSendResult::accepted('fake', 'msg_1', now()->toDateTimeImmutable()));
    $communication = Communication::factory()->queued()->create();

    deliverCommunication($communication);

    $communication->refresh();

    expect($communication->status)->toBe(CommunicationStatus::Sent)
        ->and($communication->sent_at)->not->toBeNull()
        ->and($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]->subject)->toBe($communication->subject)
        ->and($communication->attempts()->first()->status)->toBe(DeliveryAttemptStatus::Succeeded);
});

it('marks a communication as failed without retrying on a permanent failure', function () {
    bindFakeEmailProvider(ProviderSendResult::failed(
        'fake',
        'communication_provider_rejected',
        '550 rejected',
        permanent: true,
    ));
    $communication = Communication::factory()->queued()->create();

    deliverCommunication($communication);

    $communication->refresh();

    expect($communication->status)->toBe(CommunicationStatus::Failed)
        ->and($communication->last_error_code)->toBe('communication_provider_rejected')
        ->and($communication->failed_at)->not->toBeNull()
        ->and($communication->attempts()->first()->status)->toBe(DeliveryAttemptStatus::Failed);
});

it('throws so the queue retries a transient failure', function () {
    bindFakeEmailProvider(ProviderSendResult::failed('fake', 'communication_provider_unavailable', 'timeout'));
    $communication = Communication::factory()->queued()->create();

    expect(fn () => deliverCommunication($communication))->toThrow(CommunicationException::class);

    $communication->refresh();

    expect($communication->status)->toBe(CommunicationStatus::Processing)
        ->and($communication->attempts()->first()->status)->toBe(DeliveryAttemptStatus::Failed);
});

it('does not re-deliver an already sent communication', function () {
    $fake = bindFakeEmailProvider(ProviderSendResult::accepted('fake'));
    $communication = Communication::factory()->sent()->create();

    deliverCommunication($communication);

    expect($fake->sent)->toBeEmpty()
        ->and($communication->refresh()->attempts()->count())->toBe(0);
});

it('records the provider name on the delivery attempt', function () {
    bindFakeEmailProvider(ProviderSendResult::accepted('fake'));
    $communication = Communication::factory()->queued()->create();

    deliverCommunication($communication);

    expect($communication->attempts()->first()->provider)->toBe('fake');
});

it('delivers through the real smtp provider using the mail fake', function () {
    Mail::fake();
    $communication = Communication::factory()->queued()->create();

    deliverCommunication($communication);

    $communication->refresh();

    expect($communication->status)->toBe(CommunicationStatus::Sent);

    Mail::assertSent(
        CommunicationsMailable::class,
        fn (CommunicationsMailable $mail) => $mail->hasTo($communication->recipient_address),
    );
});
