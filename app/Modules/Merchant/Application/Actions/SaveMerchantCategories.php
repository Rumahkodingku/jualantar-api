<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class SaveMerchantCategories
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly ServiceLookup $serviceLookup) {}

    /**
     * @param  list<string>  $categoryIds
     */
    public function __invoke(Merchant $merchant, array $categoryIds): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        if ($merchant->service_id === null) {
            return $this->invalidCategory('Select a service before choosing categories.');
        }

        $categories = $this->serviceLookup->categoriesByIds($categoryIds);

        foreach ($categoryIds as $categoryId) {
            $category = $categories[$categoryId] ?? null;

            if ($category === null || ! $category->isActive || $category->serviceId !== $merchant->service_id) {
                return $this->invalidCategory();
            }
        }

        DB::transaction(function () use ($merchant, $categoryIds): void {
            $merchant->categories()->delete();

            foreach ($categoryIds as $categoryId) {
                $merchant->categories()->create(['category_id' => $categoryId]);
            }
        });

        return Result::ok($merchant->refresh());
    }
}
