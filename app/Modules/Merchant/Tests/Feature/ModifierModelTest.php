<?php

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Illuminate\Database\QueryException;

it('links modifier groups to their product and merchant', function () {
    $product = Product::factory()->simple()->create();
    $group = ProductModifierGroup::factory()->forProduct($product)->create();
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    expect($group->product->id)->toBe($product->id)
        ->and($group->merchant->id)->toBe($product->merchant_id)
        ->and($modifier->group->id)->toBe($group->id)
        ->and($modifier->merchant->id)->toBe($product->merchant_id)
        ->and($group->modifiers)->toHaveCount(1)
        ->and($product->modifierGroups)->toHaveCount(1);
});

it('casts the modifier group and modifier columns', function () {
    $group = ProductModifierGroup::factory()->single()->create();
    $modifier = ProductModifier::factory()->forGroup($group)->default()->create(['price' => 5000]);

    expect($group->selection_type)->toBe(ModifierSelectionType::Single)
        ->and($group->status)->toBe(CatalogStatus::Inactive)
        ->and($group->is_required)->toBeFalse()
        ->and($group->max_selection)->toBe(1)
        ->and($modifier->status)->toBe(CatalogStatus::Active)
        ->and($modifier->is_default)->toBeTrue()
        ->and($modifier->price)->toBe('5000.00');
});

it('soft deletes modifier groups and modifiers', function () {
    $group = ProductModifierGroup::factory()->create();
    $modifier = ProductModifier::factory()->forGroup($group)->create();

    $group->delete();
    $modifier->delete();

    expect(ProductModifierGroup::query()->count())->toBe(0)
        ->and(ProductModifier::query()->count())->toBe(0)
        ->and(ProductModifierGroup::withTrashed()->find($group->id))->not->toBeNull()
        ->and(ProductModifier::withTrashed()->find($modifier->id))->not->toBeNull();
});

it('enforces a case-insensitive unique group name per product', function () {
    $product = Product::factory()->simple()->create();
    ProductModifierGroup::factory()->forProduct($product)->create(['name' => 'Pilihan Saus']);

    expect(fn () => ProductModifierGroup::factory()->forProduct($product)->create(['name' => 'pilihan saus']))
        ->toThrow(QueryException::class);
});

it('allows reusing a group name after a soft delete', function () {
    $product = Product::factory()->simple()->create();
    $group = ProductModifierGroup::factory()->forProduct($product)->create(['name' => 'Pilihan Saus']);
    $group->delete();

    $recreated = ProductModifierGroup::factory()->forProduct($product)->create(['name' => 'Pilihan Saus']);

    expect($recreated->id)->not->toBe($group->id);
});

it('enforces a case-insensitive unique modifier name per group', function () {
    $group = ProductModifierGroup::factory()->create();
    ProductModifier::factory()->forGroup($group)->create(['name' => 'Extra Cheese']);

    expect(fn () => ProductModifier::factory()->forGroup($group)->create(['name' => 'EXTRA CHEESE']))
        ->toThrow(QueryException::class);
});
