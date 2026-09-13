<?php

namespace Tests\Support;

use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Modules\Geography\Domain\Models\District;
use App\Modules\Geography\Domain\Models\Province;
use App\Modules\Geography\Domain\Models\Regency;
use App\Modules\Geography\Domain\Models\Village;
use App\Modules\IdentityAccess\Database\Seeders\RbacSeeder;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Payout\Domain\Enums\PayoutOwnerType;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds cross-module test data through a single seam.
 *
 * Module tests must not import another module's Domain models directly; they
 * call these helpers instead. This trait is test infrastructure and therefore
 * lives under the Tests namespace, which the module boundary arch tests ignore.
 */
trait InteractsWithModules
{
    protected function seedRbac(): void
    {
        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    protected function customerUser(): User
    {
        return User::factory()->customer()->create();
    }

    protected function merchantUser(): User
    {
        return User::factory()->merchant()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function plainUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function userByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    protected function actingAsSuperAdmin(): User
    {
        $user = $this->superAdmin();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsCustomer(): User
    {
        $user = $this->customerUser();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsMerchant(): User
    {
        $user = $this->merchantUser();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newProvince(array $attributes = []): Province
    {
        return Province::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newRegency(array $attributes = []): Regency
    {
        return Regency::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newDistrict(array $attributes = []): District
    {
        return District::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newVillage(array $attributes = []): Village
    {
        return Village::factory()->create($attributes);
    }

    /**
     * @return array{province: Province, regency: Regency, district: District, village: Village}
     */
    protected function region(): array
    {
        $province = $this->newProvince();
        $regency = $this->newRegency(['province_id' => $province->id]);
        $district = $this->newDistrict(['regency_id' => $regency->id]);
        $village = $this->newVillage(['district_id' => $district->id]);

        return compact('province', 'regency', 'district', 'village');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newService(array $attributes = []): Service
    {
        return Service::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newServiceCategory(array $attributes = []): ServiceCategory
    {
        return ServiceCategory::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newBank(array $attributes = []): Bank
    {
        return Bank::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newMerchant(array $attributes = []): Merchant
    {
        return Merchant::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function newPayoutAccountForMerchant(string $merchantId, array $attributes = []): PayoutAccount
    {
        return PayoutAccount::factory()
            ->forOwner(PayoutOwnerType::Merchant, $merchantId)
            ->create($attributes);
    }
}
