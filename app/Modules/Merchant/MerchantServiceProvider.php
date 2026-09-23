<?php

namespace App\Modules\Merchant;

use App\Modules\IdentityAccess\Contracts\UserContextContributor;
use App\Modules\Merchant\Infrastructure\UserContext\OutletAssignmentsContributor;
use Illuminate\Support\ServiceProvider;

final class MerchantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserContextContributor::class, OutletAssignmentsContributor::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        $this->loadViewsFrom(__DIR__.'/Infrastructure/Templates', 'merchant');
    }
}
