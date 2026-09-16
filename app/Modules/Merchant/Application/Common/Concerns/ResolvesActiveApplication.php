<?php

namespace App\Modules\Merchant\Application\Common\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;

trait ResolvesActiveApplication
{
    private function activeApplication(Merchant $merchant): ?MerchantApplication
    {
        return MerchantApplication::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', MerchantApplicationStatus::activeValues())
            ->latest('created_at')
            ->first();
    }

    private function latestApplication(Merchant $merchant): ?MerchantApplication
    {
        return MerchantApplication::query()
            ->where('merchant_id', $merchant->id)
            ->latest('created_at')
            ->first();
    }
}
