<?php

namespace App\Modules\Service;

use App\Modules\Service\Contracts\ServiceLookup;
use App\Modules\Service\Infrastructure\Repositories\EloquentServiceLookup;
use Illuminate\Support\ServiceProvider;

final class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServiceLookup::class, EloquentServiceLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
