<?php

namespace App\Modules\Payout;

use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Modules\Payout\Infrastructure\Repositories\EloquentPayoutAccountLookup;
use Illuminate\Support\ServiceProvider;

final class PayoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PayoutAccountLookup::class, EloquentPayoutAccountLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
