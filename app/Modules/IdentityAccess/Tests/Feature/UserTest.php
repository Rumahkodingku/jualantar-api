<?php

use App\Modules\IdentityAccess\Domain\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns the authenticated user in the data envelope', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissing(['password']);
});
