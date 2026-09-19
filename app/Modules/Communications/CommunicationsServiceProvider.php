<?php

namespace App\Modules\Communications;

use App\Modules\Communications\Application\Services\CommunicationDispatcher;
use App\Modules\Communications\Application\Services\CommunicationRenderer;
use App\Modules\Communications\Application\Services\EloquentCommunications;
use App\Modules\Communications\Contracts\Channels\Channel;
use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Domain\Enums\CommunicationChannel;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailChannel;
use App\Modules\Communications\Infrastructure\Channels\Email\EmailProvider;
use App\Modules\Communications\Infrastructure\Providers\Smtp\SmtpEmailProvider;
use Illuminate\Support\ServiceProvider;

final class CommunicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Communications::class, EloquentCommunications::class);
        $this->app->bind(EmailProvider::class, SmtpEmailProvider::class);
        $this->app->bind(Channel::class, EmailChannel::class);

        $this->app->singleton(CommunicationRenderer::class, function (): CommunicationRenderer {
            return new CommunicationRenderer(
                (array) config('communications.templates', ['communications']),
            );
        });

        $this->app->singleton(CommunicationDispatcher::class, function ($app): CommunicationDispatcher {
            return new CommunicationDispatcher(
                $app->make(CommunicationRenderer::class),
                [
                    CommunicationChannel::Email->value => $app->make(EmailChannel::class),
                ],
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Infrastructure/Templates', 'communications');
    }
}
