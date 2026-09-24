<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Requests\Concerns;

use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ResolvesOwnerMerchant
{
    /**
     * The merchant owned by the authenticated user, used to scope validation
     * rules (unique, exists) to the caller's own catalog.
     */
    protected function ownerMerchantId(): ?string
    {
        $userId = $this->user()?->getAuthIdentifier();

        if ($userId === null) {
            return null;
        }

        return Merchant::query()->where('user_id', $userId)->value('id');
    }

    /**
     * A live category belonging to the caller's own merchant.
     *
     * The table name stays unqualified so PostgreSQL resolves it through the
     * connection search_path (see .ai/rules/requests.md).
     */
    protected function ownedCategoryExistsRule(): Exists
    {
        return Rule::exists('catalog_categories', 'id')
            ->where('merchant_id', $this->ownerMerchantId())
            ->whereNull('deleted_at');
    }

    /**
     * An outlet belonging to the caller's own merchant.
     */
    protected function ownedOutletExistsRule(): Exists
    {
        return Rule::exists('merchant_outlets', 'id')
            ->where('merchant_id', $this->ownerMerchantId());
    }
}
