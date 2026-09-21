<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\OutletUserRole;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantOutletUser>
 */
class MerchantOutletUserFactory extends Factory
{
    protected $model = MerchantOutletUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'outlet_id' => MerchantOutlet::factory(),
            'user_id' => (string) Str::uuid(),
            'role' => OutletUserRole::OutletStaff,
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (): array => ['role' => OutletUserRole::OutletManager]);
    }

    public function staff(): static
    {
        return $this->state(fn (): array => ['role' => OutletUserRole::OutletStaff]);
    }

    public function forMerchant(string $merchantId): static
    {
        return $this->state(fn (): array => ['merchant_id' => $merchantId]);
    }

    public function forOutlet(MerchantOutlet $outlet): static
    {
        return $this->state(fn (): array => [
            'merchant_id' => $outlet->merchant_id,
            'outlet_id' => $outlet->id,
        ]);
    }

    public function forUser(string $userId): static
    {
        return $this->state(fn (): array => ['user_id' => $userId]);
    }
}
