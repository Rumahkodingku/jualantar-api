<?php

namespace App\Providers;

use App\Shared\Http\ProblemDetailsFactory;
use App\Shared\OpenApi\ProblemDetailsOperationTransformer;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
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

        Scramble::configure()
            ->withOperationTransformers(ProblemDetailsOperationTransformer::class);
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
