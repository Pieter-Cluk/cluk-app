<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Member;

/** Numbers VIES could not answer for (busy, down) are checked again automatically, a few at a time. */
class ViesRecheck
{
    public static function member(Member $m, Vies $vies): void
    {
        if (! Vies::appliesTo($m->reg_vat_number)) {
            return;
        }
        $r = $vies->check($m->reg_vat_number);
        $reason = $r['reason'] ?? null;
        if ($r['status'] === 'valid' && Vies::nameDiffers($m->reg_company_name, $r['name'])) {
            $reason = 'name_differs';
        }
        $m->forceFill(['vies_status' => $r['status'], 'vies_reason' => $reason, 'vies_name' => $r['name'] ?? $m->vies_name, 'vies_checked_at' => now()])->save();
    }

    public static function company(Company $c, Vies $vies): void
    {
        $r = $vies->check($c->vat_number);
        $c->forceFill(['vies_status' => $r['status'], 'vies_reason' => $r['reason'] ?? null, 'vies_checked_at' => now(),
            'vies_name' => $r['name'] ?? $c->vies_name, 'vies_address' => $r['address'] ?? $c->vies_address])->save();
    }

    /** @return int how many were checked */
    public static function run(Vies $vies, int $limit = 15): int
    {
        $n = 0;
        foreach (Member::where('vies_status', 'unavailable')->whereNotNull('reg_vat_number')->orderBy('vies_checked_at')->limit($limit)->get() as $m) {
            self::member($m, $vies);
            $n++;
        }
        foreach (Company::where('vies_status', 'unavailable')->whereNotNull('vat_number')->orderBy('vies_checked_at')->limit(max(0, $limit - $n))->get() as $c) {
            self::company($c, $vies);
            $n++;
        }

        return $n;
    }
}
