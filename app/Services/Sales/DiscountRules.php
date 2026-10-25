<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\User;

/**
 * Who may give a customer a discount (rental % and sales conditions):
 * someone with "Sales: approve low margin" may give any discount; the
 * customer's account owner (sales level 2) up to the limit in Settings ›
 * Company. Every change is in the audit log.
 */
class DiscountRules
{
    public static function limitBp(): int
    {
        return (int) (CompanyDetail::current()->discount_limit_bp ?? 1000);
    }

    public static function approver(User $u): bool
    {
        return $u->hasRight('sales.approve_low_margin');
    }

    /** May this person change the customer's discounts at all? */
    public static function canEdit(User $u, Company $c): bool
    {
        return self::approver($u) || ($c->account_owner_id === $u->id && $u->canUseModule('sales', 2));
    }

    /** May this person give this discount (in hundredths of a percent)? */
    public static function allows(User $u, Company $c, int $bp): bool
    {
        return self::approver($u) || (self::canEdit($u, $c) && $bp <= self::limitBp());
    }

    public static function pct(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',').'%';
    }
}
