<?php

use App\Modules\Communications\Application\Services\CommunicationRenderer;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;
use App\Modules\Communications\Domain\Models\Communication;

it('renders a named template into html and plain text', function () {
    $communication = new Communication([
        'channel' => 'email',
        'type' => 'system.test',
        'recipient_address' => 'user@example.test',
        'subject' => 'Hello',
        'template' => 'email.generic',
        'payload' => ['title' => 'Judul', 'body' => 'Isi pesan'],
    ]);

    $message = app(CommunicationRenderer::class)->render($communication);

    expect($message->subject)->toBe('Hello')
        ->and($message->recipientAddress)->toBe('user@example.test')
        ->and($message->html)->toContain('Judul')->toContain('Isi pesan')
        ->and($message->text)->toContain('Judul')->toContain('Isi pesan');
});

it('falls back to the configured default subject', function () {
    config()->set('communications.default_subject', 'JualAntar');

    $communication = new Communication([
        'channel' => 'email',
        'type' => 'system.test',
        'recipient_address' => 'user@example.test',
        'template' => 'email.generic',
        'payload' => ['title' => 'Judul', 'body' => 'Isi'],
    ]);

    expect(app(CommunicationRenderer::class)->render($communication)->subject)->toBe('JualAntar');
});

it('throws when the template does not exist', function () {
    $communication = new Communication([
        'channel' => 'email',
        'type' => 'system.test',
        'recipient_address' => 'user@example.test',
        'template' => 'email.does-not-exist',
    ]);

    expect(fn () => app(CommunicationRenderer::class)->render($communication))
        ->toThrow(CommunicationException::class);
});

it('throws when no template identifier is set', function () {
    $communication = new Communication([
        'channel' => 'email',
        'type' => 'system.test',
        'recipient_address' => 'user@example.test',
    ]);

    expect(fn () => app(CommunicationRenderer::class)->render($communication))
        ->toThrow(CommunicationException::class);
});
