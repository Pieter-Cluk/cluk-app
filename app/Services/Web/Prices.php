<?php

namespace App\Services\Web;

use App\Models\ProductSales\SalesItem;
use App\Models\Web\CustomerAccount;
use App\Services\ProductSales\CustomerPrices;
use Illuminate\Support\Collection;

/**
 * Prices on the website's sales catalogue. A signed-in, active customer with a
 * company gets their standard sales conditions; everyone else the list price.
 * Amounts in cents, excl. VAT.
 */
class Prices
{
    /**
     * One item: ['list_cents' => …, 'price_cents' => …, 'discount_pct' => ?float].
     * Several: the same, keyed by item id.
     */
    public static function for(?CustomerAccount $account, SalesItem|Collection $items): array
    {
        $who = $account?->isActive() ? $account : null;
        if ($items instanceof SalesItem) {
            return self::one($who, $items);
        }

        return $items->mapWithKeys(fn (SalesItem $i) => [$i->id => self::one($who, $i)])->all();
    }

    private static function one(?CustomerAccount $who, SalesItem $item): array
    {
        $q = CustomerPrices::quote($who, $item);
        $pct = $q['list_cents'] > 0 && $q['price_cents'] < $q['list_cents'] ? round((1 - $q['price_cents'] / $q['list_cents']) * 100, 1) : null;

        return ['list_cents' => $q['list_cents'], 'price_cents' => $q['price_cents'], 'discount_pct' => $pct];
    }
}
