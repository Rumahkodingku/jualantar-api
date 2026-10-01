<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded per-outlet restriction of a master catalog item.
 *
 * Used for the owner's reverse view ("which outlets hid this?") and as the
 * response of a deactivate call. `outlet_name` and `deactivated_by_email` are
 * hydrated by the query service, so this stays a pure serializer.
 *
 * @mixin OutletProductVariant|OutletProductModifierGroup|OutletProductModifier
 */
class OutletItemOverrideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Model $override */
        $override = $this->resource;

        return [
            'subject_type' => self::subjectTypeOf($override),
            'item_id' => self::itemIdOf($override),
            'outlet_id' => $override->outlet_id,
            'outlet_name' => $override->getAttribute('outlet_name'),
            'status' => $override->status instanceof CatalogStatus
                ? $override->status->value
                : $override->status,
            'deactivated_at' => $override->deactivated_at?->toIso8601String(),
            'deactivated_by' => $override->deactivated_by === null ? null : [
                'id' => (string) $override->deactivated_by,
                'email' => $override->getAttribute('deactivated_by_email'),
            ],
        ];
    }

    /**
     * @return 'variant'|'modifier_group'|'modifier'
     */
    private static function subjectTypeOf(Model $override): string
    {
        return match (true) {
            $override instanceof OutletProductVariant => 'variant',
            $override instanceof OutletProductModifierGroup => 'modifier_group',
            default => 'modifier',
        };
    }

    private static function itemIdOf(Model $override): string
    {
        return match (true) {
            $override instanceof OutletProductVariant => (string) $override->product_variant_id,
            $override instanceof OutletProductModifierGroup => (string) $override->product_modifier_group_id,
            default => (string) $override->product_modifier_id,
        };
    }
}
