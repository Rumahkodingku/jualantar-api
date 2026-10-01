<?php

namespace App\Modules\Merchant\Domain\Catalog;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;

/**
 * Resolves the status a catalog item actually has at one outlet.
 *
 * The master status is the ceiling and the per-outlet override is a
 * deviation from it, never a copy:
 *
 * - a master-inactive item is inactive at every outlet, because only the owner
 *   decides whether an item exists in the catalog at all;
 * - a master-active item follows the master unless an outlet recorded a
 *   deviation, and that deviation can only be `inactive`.
 *
 * An outlet manager can therefore hide an item from their own outlet but can
 * never promote one the owner turned off. Kept as pure functions so the whole
 * table is exercised without a database, mirroring ProductSellability and
 * ModifierSelectionRule.
 */
final class OutletItemStatus
{
    /**
     * @param  CatalogStatus|null  $override  The outlet's recorded deviation, or null when it follows the master.
     */
    public static function resolve(CatalogStatus $master, ?CatalogStatus $override): CatalogStatus
    {
        if ($master !== CatalogStatus::Active) {
            return CatalogStatus::Inactive;
        }

        return $override ?? CatalogStatus::Active;
    }

    /**
     * Whether the item is effective-active at the outlet.
     */
    public static function isEffectiveActive(CatalogStatus $master, ?CatalogStatus $override): bool
    {
        return self::resolve($master, $override) === CatalogStatus::Active;
    }

    /**
     * Whether the outlet recorded a deviation from the master.
     *
     * A deviation is always a restriction, so this is also the flag that tells
     * the owner "this outlet changed something" and tells the manager "your
     * switch is not showing the master value".
     */
    public static function isOverridden(?CatalogStatus $override): bool
    {
        return $override !== null;
    }
}
