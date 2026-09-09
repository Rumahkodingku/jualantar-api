<?php

namespace App\Providers;

use App\Support\Http\ProblemDetailsFactory;
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
        //
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
