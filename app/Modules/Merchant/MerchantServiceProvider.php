<?php

namespace App\Modules\Merchant;

use Illuminate\Support\ServiceProvider;

final class MerchantServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
