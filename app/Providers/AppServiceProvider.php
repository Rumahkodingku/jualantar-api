<?php

namespace App\Providers;

use App\Support\Http\ProblemDetailsFactory;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
