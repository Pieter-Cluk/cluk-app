<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Finance\PreInvoice;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Afrit6\Pricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A pre-invoice made by hand, without a deal or quote: hours, a repair, extra costs the customer does not have to
 * approve. It follows the same road as every pre-invoice: automatic checks, the accountant, then OkiOki.
 */
class DirectInvoices
{
    public const TYPE = 'direct';

    /** May this user make one, or change one (while it is with the accountant or sent back)? */
    public static function canMake(User $u): bool
    {
        return $u->canUseModule('preinvoice', 2) || $u->canUseModule('sales', 2);
    }

    public static function canEdit(PreInvoice $pf, User $u): bool
    {
        return $pf->source_type === self::TYPE && in_array($pf->status, ['check', 'back'], true)
            && ($pf->made_by_id === $u->id || $u->canUseModule('preinvoice', 2));
    }

    /**
     * @param  array{company_id:int, module:string, description:string, po_number:?string, service_date:string, lines:array<int, array{d:string, q:float, unit_cents:int}>}  $d
     */
    public static function save(?PreInvoice $pf, array $d, User $by): PreInvoice
    {
        $company = Company::findOrFail($d['company_id']);
        $date = Carbon::parse($d['service_date']);
        $me = CompanyDetail::current();
        // The venue and Afrit 6 are invoiced with Belgian VAT; the rest follows the customer (e.g. reverse charge).
        $rate = in_array($d['module'], ['factory', 'afrit6'], true)
            ? VatRate::forCode((string) $me->vat_default_be, $date->toDateString())
            : $company->vatRate($date->toDateString());
        $fmt = fn (float $q) => rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
        $lines = collect($d['lines'])->map(fn ($l) => ['d' => $l['d'], 'q' => $fmt((float) $l['q']), 'unit_cents' => (int) $l['unit_cents'],
            'amount_cents' => (int) round((float) $l['q'] * (int) $l['unit_cents']), 'note' => null])->values()->all();
        $net = (int) collect($lines)->sum('amount_cents');
        $vat = (int) round($net * ($rate?->rate_bp ?? 0) / 10000);
        $data = [
            'module' => $d['module'], 'company_id' => $company->id, 'member_id' => null, 'owner_id' => $by->id, 'description' => mb_substr($d['description'], 0, 200),
            'lines' => $lines, 'vat_code' => $rate?->code, 'vat_rate_bp' => $rate?->rate_bp ?? 0, 'net_cents' => $net, 'cost_cents' => 0, 'vat_cents' => $vat, 'gross_cents' => $net + $vat,
            'po_number' => ($d['po_number'] ?? null) ?: null, 'payment_days' => (int) ($company->customer_payment_days ?? $me->payment_term_days ?? 0), 'service_date' => $date,
        ];

        return DB::transaction(function () use ($pf, $data, $by) {
            if ($pf) {
                $pf->update($data);
                AuditLog::record('finance.pf_direct_changed', $pf, ['total' => Pricing::eur($pf->gross_cents)], $by);
            } else {
                $number = PreInvoice::nextNumber();
                $pf = PreInvoice::create($data + ['number' => $number, 'source_type' => self::TYPE, 'source_id' => 0, 'source_code' => 'D-'.$number, 'made_by_id' => $by->id, 'status' => 'check']);
                $pf->update(['source_id' => $pf->id]);
                AuditLog::record('finance.pf_made', $pf, ['source' => $pf->source_code, 'total' => Pricing::eur($pf->gross_cents), 'direct' => true], $by);
            }
            $pf->update(['checks' => PreInvoices::checks($pf->fresh(['company', 'member']))]);

            return $pf->fresh();
        });
    }
}
