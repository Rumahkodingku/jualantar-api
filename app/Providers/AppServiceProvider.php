<?php

namespace App\Providers;

use App\Shared\Http\ProblemDetailsFactory;
use App\Shared\OpenApi\ProblemDetailsOperationTransformer;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProblemDetailsFactory::class, function () {
            return new ProblemDetailsFactory(
                baseUrl: (string) config('api.problem.base_url'),
                debug: (bool) config('app.debug'),
            );
        });

        $this->app->singleton(CollectorRegistry::class, function () {
            return new CollectorRegistry($this->metricsStorage(), registerDefaultMetrics: false);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', fn ($user = null): bool => ! app()->isProduction());

        $this->configureRateLimiters();

        Scramble::configure()
            ->withOperationTransformers(ProblemDetailsOperationTransformer::class);
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('auth.register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('auth.login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('auth.verification.resend', fn (Request $request) => Limit::perHour(3)
            ->by(Str::lower((string) $request->input('email'))));
    }

    private function metricsStorage(): InMemory|APC
    {
        if (config('api.observability.metrics_adapter') === 'apcu' && $this->apcuAvailable()) {
            return new APC;
        }

        return new InMemory;
    }

    private function apcuAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }
}
