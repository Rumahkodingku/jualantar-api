<?php

namespace App\Modules\Communications\Infrastructure\Providers\Smtp;

use App\Modules\Communications\Contracts\Channels\ProviderSendResult;
use App\Modules\Communications\Infrastructure\Channels\Email\CommunicationsMailable;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailMessage;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailProvider;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

/**
 * SMTP email provider built on the repository's existing Laravel mail
 * configuration (Mailpit locally, real SMTP in production).
 *
 * Provider exceptions are normalized into a ProviderSendResult; the delivery
 * job decides whether to retry based on the `permanent` flag.
 */
final class SmtpEmailProvider implements EmailProvider
{
    private const NAME = 'smtp';

    public function __construct(private readonly ?string $mailer = null) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function send(EmailMessage $message): ProviderSendResult
    {
        try {
            Mail::mailer($this->mailer)->send(
                (new CommunicationsMailable($message->subject, $message->html, $message->text))
                    ->to($message->to),
            );

            return ProviderSendResult::accepted(self::NAME, acceptedAt: now()->toDateTimeImmutable());
        } catch (RfcComplianceException $e) {
            return ProviderSendResult::failed(
                self::NAME,
                'communication_invalid_recipient',
                $e->getMessage(),
                permanent: true,
            );
        } catch (TransportExceptionInterface $e) {
            return $this->failureFromTransport($e);
        } catch (Throwable $e) {
            return ProviderSendResult::failed(
                self::NAME,
                'communication_provider_unavailable',
                $e->getMessage(),
            );
        }
    }

    private function failureFromTransport(TransportExceptionInterface $e): ProviderSendResult
    {
        $code = (int) $e->getCode();
        $permanent = $code >= 500 && $code < 600;

        return ProviderSendResult::failed(
            self::NAME,
            $permanent ? 'communication_provider_rejected' : 'communication_provider_unavailable',
            $e->getMessage(),
            permanent: $permanent,
        );
    }
}
