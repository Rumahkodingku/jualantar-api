<?php

namespace App\Modules\Geography;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Geography\Infrastructure\Repositories\EloquentGeographyLookup;
use Illuminate\Support\ServiceProvider;

final class GeographyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GeographyLookup::class, EloquentGeographyLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
