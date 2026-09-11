<?php

namespace App\Modules\IdentityAccess;

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\IdentityAccess\Contracts\EmailVerification;
use App\Modules\IdentityAccess\Contracts\UserProvisioning;
use App\Modules\IdentityAccess\Infrastructure\Authorization\SpatieAuthorization;
use App\Modules\IdentityAccess\Infrastructure\Users\EloquentUserProvisioning;
use App\Modules\IdentityAccess\Infrastructure\Verification\LaravelEmailVerification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authorization::class, SpatieAuthorization::class);
        $this->app->bind(UserProvisioning::class, EloquentUserProvisioning::class);
        $this->app->bind(EmailVerification::class, LaravelEmailVerification::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        Gate::before(function ($user): ?bool {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
