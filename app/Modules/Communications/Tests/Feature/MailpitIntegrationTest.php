<?php

use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Domain\Enums\CommunicationChannel;
use App\Modules\Communications\Domain\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Models\Communication;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Optional Mailpit-backed integration test.
 *
 * Run with: COMMUNICATIONS_MAILPIT_TEST=1 php artisan test --filter=mailpit
 * Requires the repository's Mailpit service to be running on 127.0.0.1:1025.
 */
it('delivers a real email through smtp to mailpit', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', env('MAIL_HOST', '127.0.0.1'));
    config()->set('mail.mailers.smtp.port', (int) env('MAIL_PORT', 1025));

    $result = app(Communications::class)->send(new SendCommunicationData(
        channel: CommunicationChannel::Email,
        type: 'system.integration_test',
        recipientAddress: 'mailpit@example.test',
        subject: 'Mailpit integration',
        template: 'email.generic',
        payload: ['title' => 'Halo', 'body' => 'Pesan uji'],
    ));

    expect(Communication::query()->find($result->communicationId)->status)->toBe(CommunicationStatus::Sent);
})->skip(
    fn () => env('COMMUNICATIONS_MAILPIT_TEST') !== '1',
    'Set COMMUNICATIONS_MAILPIT_TEST=1 with Mailpit running to enable.',
);
