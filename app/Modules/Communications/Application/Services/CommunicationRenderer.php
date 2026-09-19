<?php

namespace App\Modules\Communications\Application\Services;

use App\Modules\Communications\Contracts\Channels\RenderedMessage;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;
use App\Modules\Communications\Domain\Models\Communication;
use Illuminate\Support\Facades\View;

/**
 * Renders a communication's named template into a channel-neutral message.
 *
 * Concrete templates are owned by producers and registered through the
 * configurable view namespaces; Communications ships only a generic template.
 */
final class CommunicationRenderer
{
    /**
     * @param  list<string>  $namespaces
     */
    public function __construct(private readonly array $namespaces) {}

    public function render(Communication $communication): RenderedMessage
    {
        $template = (string) $communication->template;

        if ($template === '') {
            throw CommunicationException::templateNotFound('(none)');
        }

        $payload = $communication->payload ?? [];

        $htmlView = $this->findView($template);
        $textView = $this->findView($template.'-text');

        if ($htmlView === null && $textView === null) {
            throw CommunicationException::templateNotFound($template);
        }

        $html = $htmlView !== null ? View::make($htmlView, $payload)->render() : null;
        $text = $textView !== null
            ? View::make($textView, $payload)->render()
            : trim(strip_tags((string) $html));

        return new RenderedMessage(
            channel: $communication->channel,
            recipientAddress: $communication->recipient_address,
            subject: $communication->subject ?? (string) config('communications.default_subject'),
            html: $html,
            text: $text,
            metadata: $communication->metadata,
        );
    }

    private function findView(string $template): ?string
    {
        foreach ($this->namespaces as $namespace) {
            $candidate = "{$namespace}::{$template}";

            if (View::exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
