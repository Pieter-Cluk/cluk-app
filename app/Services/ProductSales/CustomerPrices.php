<?php

namespace App\Services\ProductSales;

use App\Models\Company;
use App\Models\ProductSales\CustomerDiscount;
use App\Models\ProductSales\SalesItem;
use App\Models\Web\CustomerAccount;

/**
 * A customer's own price for a sales product: the list price minus the
 * discount set on their customer card for that brand (or for every other
 * brand). Customers without a company, or without a discount, pay list price.
 */
class CustomerPrices
{
    /** @var array<int, array<string, int>> company id => [brand id or '*' => discount bp] */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function table(int $companyId): array
    {
        return self::$cache[$companyId] ??= CustomerDiscount::where('company_id', $companyId)->get()
            ->mapWithKeys(fn ($d) => [$d->brand_id ?? '*' => $d->discount_bp])->all();
    }

    public static function company(Company|CustomerAccount|null $who): ?Company
    {
        return $who instanceof CustomerAccount ? $who->company : $who;
    }

    /** Discount in basis points (1250 = 12.5%). */
    public static function discountBp(Company|CustomerAccount|null $who, SalesItem $item): int
    {
        $c = self::company($who);
        if (! $c) {
            return 0;
        }
        $t = self::table($c->id);

        return (int) ($item->brand_id && isset($t[$item->brand_id]) ? $t[$item->brand_id] : ($t['*'] ?? 0));
    }

    /** Price per unit in cents, excl. VAT. */
    public static function priceCents(Company|CustomerAccount|null $who, SalesItem $item): int
    {
        return (int) round($item->list_price_cents * (1 - self::discountBp($who, $item) / 10000));
    }
}
