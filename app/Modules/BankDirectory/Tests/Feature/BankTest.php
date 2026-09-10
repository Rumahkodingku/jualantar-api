<?php

use App\Modules\BankDirectory\Domain\Models\Bank;

function bankPayload(array $overrides = []): array
{
    return array_merge([
        'code' => '123',
        'name' => 'PT BANK CONTOH',
        'category' => 'persero',
        'address' => 'Jl. Contoh No. 1, Jakarta',
        'phone' => '(021) 1234567',
        'website' => 'www.contohbank.co.id',
    ], $overrides);
}

it('lists active banks inside the paginated envelope', function () {
    Bank::factory()->count(3)->create(['is_active' => true]);

    $this->getJson('/api/v1/banks')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'code', 'name', 'category', 'address', 'phone', 'website', 'is_active', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ])
        ->assertJsonPath('meta.total', 3);
});

it('excludes inactive banks from the list by default but can include them via filter', function () {
    Bank::factory()->create(['name' => 'Aktif', 'is_active' => true]);
    Bank::factory()->create(['name' => 'Nonaktif', 'is_active' => false]);

    $this->getJson('/api/v1/banks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Aktif');

    $this->getJson('/api/v1/banks?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nonaktif');
});

it('filters banks by category', function () {
    Bank::factory()->create(['name' => 'Persero', 'category' => 'persero']);
    Bank::factory()->create(['name' => 'Privat', 'category' => 'private']);

    $this->getJson('/api/v1/banks?category=persero')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Persero');
});

it('searches banks by name or code', function () {
    Bank::factory()->create(['name' => 'PT BANK RAKYAT INDONESIA', 'code' => '002']);
    Bank::factory()->create(['name' => 'PT BANK MANDIRI', 'code' => '008']);

    $this->getJson('/api/v1/banks?search=rakyat')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', '002');

    $this->getJson('/api/v1/banks?search=008')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'PT BANK MANDIRI');
});

it('returns a validation problem for invalid index query params', function () {
    $this->getJson('/api/v1/banks?category=bogus')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.category.0', 'The selected category is invalid.');

    $this->getJson('/api/v1/banks?per_page=0')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('shows a single bank, including inactive ones', function () {
    $bank = Bank::factory()->create(['code' => '002', 'is_active' => true]);
    $inactive = Bank::factory()->create(['is_active' => false]);

    $this->getJson("/api/v1/banks/{$bank->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $bank->id)
        ->assertJsonPath('data.code', '002')
        ->assertJsonPath('data.is_active', true);

    $this->getJson("/api/v1/banks/{$inactive->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('returns a 404 problem when the bank does not exist', function () {
    $this->getJson('/api/v1/banks/999999')
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found')
        ->assertJsonPath('detail', 'The requested resource was not found.');
});

it('creates a bank', function () {
    $this->postJson('/api/v1/banks', bankPayload(['code' => '002']))
        ->assertCreated()
        ->assertJsonPath('data.code', '002')
        ->assertJsonPath('data.name', 'PT BANK CONTOH')
        ->assertJsonPath('data.category', 'persero')
        ->assertJsonPath('data.is_active', true);

    $bank = Bank::where('code', '002')->firstOrFail();

    $this->assertDatabaseHas('banks', ['code' => '002']);
});

it('returns a conflict problem when creating a bank with a duplicate code', function () {
    Bank::factory()->create(['code' => '008']);

    $this->postJson('/api/v1/banks', bankPayload(['code' => '008']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'conflict')
        ->assertJsonPath('title', 'Conflict');
});

it('returns a validation problem when creating a bank with an invalid payload', function () {
    $this->postJson('/api/v1/banks', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonMissingPath('data');

    $this->postJson('/api/v1/banks', bankPayload(['category' => 'bogus']))
        ->assertStatus(422)
        ->assertJsonPath('errors.category.0', 'The selected category is invalid.');
});

it('updates a bank', function () {
    $bank = Bank::factory()->create(['code' => '002', 'name' => 'Lama']);

    $this->putJson("/api/v1/banks/{$bank->id}", bankPayload(['code' => '002', 'name' => 'Baru']))
        ->assertOk()
        ->assertJsonPath('data.id', $bank->id)
        ->assertJsonPath('data.name', 'Baru');

    $this->assertDatabaseHas('banks', ['id' => $bank->id, 'name' => 'Baru']);
});

it('updates a bank via patch', function () {
    $bank = Bank::factory()->create(['code' => '002', 'name' => 'Lama']);

    $this->patchJson("/api/v1/banks/{$bank->id}", bankPayload(['code' => '002', 'name' => 'Patch']))
        ->assertOk()
        ->assertJsonPath('data.name', 'Patch');
});

it('returns a conflict problem when updating a bank onto an existing code', function () {
    $bank = Bank::factory()->create(['code' => '002']);
    Bank::factory()->create(['code' => '008']);

    $this->putJson("/api/v1/banks/{$bank->id}", bankPayload(['code' => '008']))
        ->assertStatus(409)
        ->assertJsonPath('code', 'conflict');
});

it('returns a 404 problem when updating a missing bank', function () {
    $this->putJson('/api/v1/banks/999999', bankPayload())
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('deactivates a bank instead of deleting it', function () {
    $bank = Bank::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/banks/{$bank->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('banks', ['id' => $bank->id, 'is_active' => false]);
});

it('hides deactivated banks from the list but keeps them accessible and delete stays idempotent', function () {
    $bank = Bank::factory()->create(['is_active' => true]);

    $this->deleteJson("/api/v1/banks/{$bank->id}")->assertNoContent();

    $this->getJson('/api/v1/banks')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson("/api/v1/banks/{$bank->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/api/v1/banks/{$bank->id}")->assertNoContent();
});
