<?php

namespace App\Modules\BankDirectory;

use App\Modules\BankDirectory\Contracts\BankLookup;
use App\Modules\BankDirectory\Infrastructure\Repositories\EloquentBankLookup;
use Illuminate\Support\ServiceProvider;

final class BankDirectoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BankLookup::class, EloquentBankLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/Routes/api.php');
    }
}
