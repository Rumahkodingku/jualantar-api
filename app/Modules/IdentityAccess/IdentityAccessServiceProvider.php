<?php

namespace App\Modules\IdentityAccess;

use Illuminate\Support\ServiceProvider;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
