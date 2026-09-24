<?php

use App\Modules\Merchant\Domain\Catalog\ModifierSelectionRule;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;

/*
|--------------------------------------------------------------------------
| Selection rules (PRD P1 Bagian 7.1)
|--------------------------------------------------------------------------
*/

it('derives is_required from the min selection when it is not sent', function (?bool $explicit, int $min, bool $expected) {
    expect(ModifierSelectionRule::isRequiredFor($explicit, $min))->toBe($expected);
})->with([
    'derived from min 1' => [null, 1, true],
    'derived from min 0' => [null, 0, false],
    'explicit true' => [true, 1, true],
    'explicit false' => [false, 0, false],
]);

it('normalizes the max selection', function (ModifierSelectionType $type, ?int $max, ?int $expected) {
    expect(ModifierSelectionRule::normalizeMax($type, $max))->toBe($expected);
})->with([
    'single without max' => [ModifierSelectionType::Single, null, 1],
    'single with max' => [ModifierSelectionType::Single, 1, 1],
    'multiple without max' => [ModifierSelectionType::Multiple, null, null],
    'multiple with max' => [ModifierSelectionType::Multiple, 3, 3],
]);

it('keeps the required active count at least one', function (int $min, int $expected) {
    expect(ModifierSelectionRule::requiredActiveCount($min))->toBe($expected);
})->with([
    'optional group' => [0, 1],
    'min one' => [1, 1],
    'min two' => [2, 2],
    'min five' => [5, 5],
]);

it('accepts the valid selection combinations', function (ModifierSelectionType $type, int $min, ?int $max, bool $required) {
    expect(ModifierSelectionRule::validate($type, $min, $max, $required))->toBe([]);
})->with([
    'single required' => [ModifierSelectionType::Single, 1, 1, true],
    'single optional' => [ModifierSelectionType::Single, 0, 1, false],
    'multiple optional max 3' => [ModifierSelectionType::Multiple, 0, 3, false],
    'multiple required unbounded' => [ModifierSelectionType::Multiple, 1, null, true],
    'multiple required 2-3' => [ModifierSelectionType::Multiple, 2, 3, true],
]);

it('rejects the invalid selection combinations', function (ModifierSelectionType $type, int $min, ?int $max, bool $required, string $field) {
    expect(ModifierSelectionRule::validate($type, $min, $max, $required))->toHaveKey($field);
})->with([
    'negative min' => [ModifierSelectionType::Multiple, -1, 3, false, 'min_selection'],
    'max zero' => [ModifierSelectionType::Multiple, 0, 0, false, 'max_selection'],
    'max below min' => [ModifierSelectionType::Multiple, 3, 2, true, 'max_selection'],
    'single with max not one' => [ModifierSelectionType::Single, 0, 2, false, 'max_selection'],
    'single without max' => [ModifierSelectionType::Single, 0, null, false, 'max_selection'],
    'required without min' => [ModifierSelectionType::Multiple, 0, 3, true, 'is_required'],
    'min without required' => [ModifierSelectionType::Multiple, 1, 3, false, 'is_required'],
]);
