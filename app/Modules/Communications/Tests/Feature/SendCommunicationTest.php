<?php

use App\Modules\Communications\Application\Jobs\DeliverCommunication;
use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Domain\Enums\CommunicationChannel;
use App\Modules\Communications\Domain\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;
use App\Modules\Communications\Domain\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function sendCommunicationData(array $overrides = []): SendCommunicationData
{
    return new SendCommunicationData(...array_merge([
        'channel' => CommunicationChannel::Email,
        'type' => 'identity.email_verification',
        'recipientAddress' => 'user@example.test',
        'subject' => 'Verify your email',
        'template' => 'email.generic',
        'payload' => ['title' => 'Verify', 'body' => 'Click the link.'],
    ], $overrides));
}

it('persists a communication and queues delivery', function () {
    Queue::fake();

    $result = app(Communications::class)->send(sendCommunicationData());

    expect($result->queued)->toBeTrue()
        ->and($result->status)->toBe(CommunicationStatus::Queued);

    $communication = Communication::query()->find($result->communicationId);

    expect($communication)->not->toBeNull()
        ->and($communication->status)->toBe(CommunicationStatus::Queued)
        ->and($communication->queued_at)->not->toBeNull();

    Queue::assertPushed(
        DeliverCommunication::class,
        fn (DeliverCommunication $job) => $job->communicationId === $result->communicationId,
    );
});

it('does not create a duplicate for the same idempotency key', function () {
    Queue::fake();
    $key = 'identity.email_verification:123';

    $first = app(Communications::class)->send(sendCommunicationData(['idempotencyKey' => $key]));
    $second = app(Communications::class)->send(sendCommunicationData(['idempotencyKey' => $key]));

    expect($second->communicationId)->toBe($first->communicationId)
        ->and(Communication::query()->count())->toBe(1);
});

it('allows the same idempotency key across different sends without a key', function () {
    Queue::fake();

    app(Communications::class)->send(sendCommunicationData());
    app(Communications::class)->send(sendCommunicationData());

    expect(Communication::query()->count())->toBe(2);
});

it('rejects an invalid recipient address', function () {
    expect(fn () => app(Communications::class)->send(sendCommunicationData(['recipientAddress' => 'not-an-email'])))
        ->toThrow(CommunicationException::class);
});

it('requires a template for email communications', function () {
    expect(fn () => app(Communications::class)->send(sendCommunicationData(['template' => null])))
        ->toThrow(CommunicationException::class);
});

it('rejects an unsupported channel', function () {
    expect(fn () => app(Communications::class)->send(sendCommunicationData(['channel' => CommunicationChannel::Sms])))
        ->toThrow(CommunicationException::class);
});

it('rejects an invalid communication type', function () {
    expect(fn () => app(Communications::class)->send(sendCommunicationData(['type' => 'Invalid Type!'])))
        ->toThrow(CommunicationException::class);
});

it('stores the payload encrypted at rest', function () {
    Queue::fake();

    $result = app(Communications::class)->send(sendCommunicationData([
        'payload' => ['otp' => '123456'],
    ]));

    $raw = DB::table('communications.communications')
        ->where('id', $result->communicationId)
        ->value('payload');

    expect($raw)->not->toContain('123456');

    $communication = Communication::query()->find($result->communicationId);

    expect($communication->payload)->toBe(['otp' => '123456']);
});
