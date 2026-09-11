<?php

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Authorization\SpatieAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('reports whether a user has a role', function () {
    $user = User::factory()->driver()->create();
    $missingUserId = '00000000-0000-0000-0000-000000000000';

    $authorization = new SpatieAuthorization;

    expect($authorization->userHasRole($user->id, 'driver'))->toBeTrue()
        ->and($authorization->userHasRole($user->id, 'auditor'))->toBeFalse()
        ->and($authorization->userHasRole($missingUserId, 'driver'))->toBeFalse();
});

it('reports whether a user has a permission', function () {
    $user = User::factory()->driver()->create();
    $missingUserId = '00000000-0000-0000-0000-000000000000';

    $authorization = new SpatieAuthorization;

    expect($authorization->userHasPermission($user->id, 'deliveries.view'))->toBeTrue()
        ->and($authorization->userHasPermission($user->id, 'users.delete'))->toBeFalse()
        ->and($authorization->userHasPermission($user->id, 'does.not.exist'))->toBeFalse()
        ->and($authorization->userHasPermission($missingUserId, 'deliveries.view'))->toBeFalse();
});

it('binds the authorization contract to the Spatie implementation', function () {
    expect(app(Authorization::class))->toBeInstanceOf(SpatieAuthorization::class);
});
