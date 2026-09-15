<?php

use App\Modules\IdentityAccess\Database\Seeders\SuperAdminSeeder;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use Spatie\Permission\PermissionRegistrar;

const SEEDED_SUPER_ADMIN_EMAIL = 'seeded-super-admin@example.test';

beforeEach(function () {
    putenv('SUPER_ADMIN_EMAIL='.SEEDED_SUPER_ADMIN_EMAIL);
    $_ENV['SUPER_ADMIN_EMAIL'] = SEEDED_SUPER_ADMIN_EMAIL;
    $_SERVER['SUPER_ADMIN_EMAIL'] = SEEDED_SUPER_ADMIN_EMAIL;
    putenv('SUPER_ADMIN_PASSWORD=secret-password');
    $_ENV['SUPER_ADMIN_PASSWORD'] = 'secret-password';
    $_SERVER['SUPER_ADMIN_PASSWORD'] = 'secret-password';
    putenv('SUPER_ADMIN_PHONE=');
    $_ENV['SUPER_ADMIN_PHONE'] = '';
    $_SERVER['SUPER_ADMIN_PHONE'] = '';
});

afterEach(function () {
    foreach (['SUPER_ADMIN_EMAIL', 'SUPER_ADMIN_PASSWORD', 'SUPER_ADMIN_PHONE'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});

it('seeds a super admin user with the super-admin role and its permissions', function () {
    $this->seed(SuperAdminSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::query()->where('email', SEEDED_SUPER_ADMIN_EMAIL)->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::SUPER_ADMIN))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasPermissionTo('users.view'))->toBeTrue()
        ->and($user->can('anything.at.all'))->toBeTrue();
});

it('is idempotent and does not duplicate the account on re-seed', function () {
    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->where('email', SEEDED_SUPER_ADMIN_EMAIL)->count())->toBe(1);
});

it('keeps the existing password when re-seeded', function () {
    $this->seed(SuperAdminSeeder::class);
    $user = User::query()->where('email', SEEDED_SUPER_ADMIN_EMAIL)->firstOrFail();
    $originalHash = $user->password;

    $this->seed(SuperAdminSeeder::class);

    expect($user->refresh()->password)->toBe($originalHash);
});
