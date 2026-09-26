<?php

namespace App\Modules\Merchant\Http\Catalog\Requests;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Domain\Enums\ProductDraftStep;
use App\Modules\Merchant\Domain\Enums\ProductMediaMimeType;
use App\Modules\Merchant\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the shape and the size of a wizard draft.
 *
 * Only structure and quotas are checked. A draft is an in-progress form, so the
 * referenced category and outlets are deliberately not verified here, and no
 * field has to be meaningful yet — completeness is enforced by the
 * create-product endpoints when the merchant finally submits.
 *
 * Fields the wizard has not filled in arrive as null, because Laravel's
 * ConvertEmptyStringsToNull middleware runs before this request, so anything
 * optional is nullable rather than a string.
 */
class SaveProductDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $limits = config('merchant.product_draft');

        return [
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'step_index' => ['required', 'integer', Rule::in(ProductDraftStep::values())],

            'data' => ['required', 'array'],

            'data.info' => ['required', 'array:name,category_id,product_type,description'],
            'data.info.name' => ['present', 'nullable', 'string', 'max:150'],
            'data.info.category_id' => ['present', 'nullable', 'uuid'],
            'data.info.description' => ['present', 'nullable', 'string', 'max:500'],
            'data.info.product_type' => ['required', Rule::in(ProductType::values())],

            'data.price_raw' => ['present', 'nullable', 'string', 'max:20'],

            'data.variants' => ['present', 'array', 'max:'.$limits['max_variants']],
            'data.variants.*.key' => ['required', 'string', 'max:64'],
            'data.variants.*.name' => ['required', 'string', 'max:150'],
            'data.variants.*.sku' => ['present', 'nullable', 'string', 'max:100'],
            'data.variants.*.price' => ['required', 'numeric', 'min:0'],
            'data.variants.*.status' => ['required', Rule::in(CatalogStatus::values())],
            'data.variants.*.is_default' => ['required', 'boolean'],

            'data.modifier_groups' => ['present', 'array', 'max:'.$limits['max_modifier_groups']],
            'data.modifier_groups.*.key' => ['required', 'string', 'max:64'],
            'data.modifier_groups.*.name' => ['required', 'string', 'max:150'],
            'data.modifier_groups.*.description' => ['present', 'nullable', 'string', 'max:500'],
            'data.modifier_groups.*.selection_type' => ['required', Rule::in(ModifierSelectionType::values())],
            'data.modifier_groups.*.min_selection' => ['required', 'integer', 'min:0', 'max:50'],
            'data.modifier_groups.*.max_selection' => ['present', 'nullable', 'integer', 'min:0', 'max:50'],
            'data.modifier_groups.*.is_required' => ['required', 'boolean'],
            'data.modifier_groups.*.status' => ['required', Rule::in(CatalogStatus::values())],
            'data.modifier_groups.*.modifiers' => [
                'present',
                'array',
                'max:'.$limits['max_modifiers_per_group'],
            ],
            'data.modifier_groups.*.modifiers.*.key' => ['required', 'string', 'max:64'],
            'data.modifier_groups.*.modifiers.*.name' => ['required', 'string', 'max:150'],
            'data.modifier_groups.*.modifiers.*.description' => ['present', 'nullable', 'string', 'max:500'],
            'data.modifier_groups.*.modifiers.*.price' => ['required', 'numeric', 'min:0'],
            'data.modifier_groups.*.modifiers.*.is_default' => ['required', 'boolean'],
            'data.modifier_groups.*.modifiers.*.status' => ['required', Rule::in(CatalogStatus::values())],

            'data.media' => ['present', 'array', 'max:'.config('storage.uploads.max_product_media')],
            'data.media.*.key' => ['required', 'string', 'max:64'],
            'data.media.*.object_key' => ['required', 'string', 'max:1024'],
            'data.media.*.file_name' => ['required', 'string', 'max:255'],
            'data.media.*.mime_type' => ['required', Rule::in(ProductMediaMimeType::values())],
            'data.media.*.file_size' => ['required', 'integer', 'min:1', 'max:'.config('storage.uploads.max_size')],
            'data.media.*.alt_text' => ['present', 'nullable', 'string', 'max:255'],
            'data.media.*.is_primary' => ['required', 'boolean'],

            'data.outlet_ids' => ['present', 'array', 'max:'.$limits['max_outlets']],
            'data.outlet_ids.*' => ['uuid'],
        ];
    }
}
