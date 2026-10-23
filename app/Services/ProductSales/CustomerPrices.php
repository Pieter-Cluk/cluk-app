<?php

namespace App\Services\ProductSales;

use App\Models\Company;
use App\Models\ProductSales\CustomerDiscount;
use App\Models\ProductSales\SalesItem;
use App\Models\Web\CustomerAccount;

/**
 * A company's own price for a sales product, from its standard sales
 * conditions (customer card). Priority:
 * product net price > brand % > sales-category % > default % > list price.
 */
class CustomerPrices
{
    /** @var array<int, array{p: array<int, int>, b: array<int, int>, c: array<int, int>, d: ?int}> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function table(int $companyId): array
    {
        if (! isset(self::$cache[$companyId])) {
            $t = ['p' => [], 'b' => [], 'c' => [], 'd' => null];
            foreach (CustomerDiscount::where('company_id', $companyId)->get() as $r) {
                match ($r->scope()) {
                    'product' => $t['p'][$r->sales_item_id] = (int) $r->net_cents,
                    'brand' => $t['b'][$r->brand_id] = (int) $r->discount_bp,
                    'category' => $t['c'][$r->category_id] = (int) $r->discount_bp,
                    default => $t['d'] = (int) $r->discount_bp,
                };
            }
            self::$cache[$companyId] = $t;
        }

        return self::$cache[$companyId];
    }

    public static function company(Company|CustomerAccount|null $who): ?Company
    {
        return $who instanceof CustomerAccount ? $who->company : $who;
    }

    /**
     * @return array{list_cents: int, price_cents: int, discount_bp: ?int, net: bool, source: string}
     *                                                                                                source: product, brand, category, default or list
     */
    public static function quote(Company|CustomerAccount|null $who, SalesItem $item): array
    {
        $list = (int) $item->list_price_cents;
        $c = self::company($who);
        $t = $c ? self::table($c->id) : null;
        if ($t && isset($t['p'][$item->id])) {
            return ['list_cents' => $list, 'price_cents' => $t['p'][$item->id], 'discount_bp' => null, 'net' => true, 'source' => 'product'];
        }
        [$bp, $source] = match (true) {
            $t && $item->brand_id && isset($t['b'][$item->brand_id]) => [$t['b'][$item->brand_id], 'brand'],
            $t && $item->category_id && isset($t['c'][$item->category_id]) => [$t['c'][$item->category_id], 'category'],
            $t && $t['d'] !== null => [$t['d'], 'default'],
            default => [0, 'list'],
        };

        return ['list_cents' => $list, 'price_cents' => (int) round($list * (1 - $bp / 10000)), 'discount_bp' => $bp ?: null, 'net' => false, 'source' => $bp ? $source : 'list'];
    }

    public static function priceCents(Company|CustomerAccount|null $who, SalesItem $item): int
    {
        return self::quote($who, $item)['price_cents'];
    }

    /**
     * The values for a sales quote line, so the line gives exactly this price:
     * a % stays a discount on the list price; a net price becomes the line's price.
     */
    public static function lineValues(Company|CustomerAccount|null $who, SalesItem $item): array
    {
        $q = self::quote($who, $item);

        return $q['net'] ? ['list_cents' => $q['price_cents'], 'discount_bp' => 0] : ['list_cents' => $q['list_cents'], 'discount_bp' => (int) $q['discount_bp']];
    }
}
