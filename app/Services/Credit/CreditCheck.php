<?php

namespace App\Services\Credit;

use App\Models\Company;
use App\Models\Finance\PreInvoice;
use Illuminate\Support\Str;

/**
 * The credit traffic light on the customer card. Our own rules, from official
 * sources and our own figures, each with its reason:
 * - register (KBO/BCE): stopped, bankrupt, in liquidation or reorganisation → red;
 *   another unusual situation, not found, or younger than 2 years → orange;
 * - VIES: VAT number not valid → red (orange when the register shows an active company: no VAT registration, e.g. a non-profit);
 * - our proformas: more than 60 days overdue → red; overdue or often paid late → orange.
 * Annual accounts (NBB) follow when that connection is active.
 */
class CreditCheck
{
    /** Words in the register's own description that mean the company is in trouble (NL, FR, DE, EN). */
    private const RED_WORDS = ['faill', 'konkurs', 'bankrupt', 'vereffen', 'liquidat', 'reorganis', 'réorganis', 'akkoord', 'concordat', 'ontbind', 'dissolution', 'auflösung', 'sluiting', 'clôture', 'gerechtelijk', 'judiciaire'];

    /** @return array{level: string, reasons: list<array{level: string, text: string}>, sources: list<string>} */
    public static function for(Company $c): array
    {
        $reasons = [];
        $sources = [];
        $add = function (string $level, string $key, array $p = []) use (&$reasons) {
            $reasons[] = ['level' => $level, 'text' => __('credit.reasons.'.$key, $p)];
        };

        $reg = $c->relationLoaded('registry') ? $c->registry : $c->registry()->first();
        if ($reg) {
            $sources[] = 'kbo';
            if (! $reg->found) {
                $add('orange', 'not_found', ['number' => $reg->enterprise_number]);
            } else {
                $sit = $reg->text('situation', 'nl').' '.$reg->text('situation', 'fr').' '.$reg->text('situation', 'en');
                if (! $reg->isNormal() && Str::contains(Str::lower($sit), self::RED_WORDS)) {
                    $add('red', 'situation', ['what' => $reg->text('situation') ?? $reg->situation_code]);
                } elseif (! $reg->isNormal()) {
                    $add('orange', 'situation_other', ['what' => $reg->text('situation') ?? $reg->situation_code]);
                }
                if ($reg->status && $reg->status !== 'AC') {
                    $add('red', 'stopped');
                }
                if ($reg->start_date && $reg->start_date->gt(today()->subYears(2))) {
                    $add('orange', 'young', ['date' => $reg->start_date->format('d/m/Y')]);
                }
            }
        }

        if ($c->vies_status) {
            $sources[] = 'vies';
            if ($c->vies_status === 'invalid') {
                // A company the register shows as active and normal is not a credit risk because it has no VAT
                // number (non-profits, small exempt firms): then it is only a point for the invoice.
                $registered = $reg && $reg->found && $reg->isActive() && $reg->isNormal();
                $add($registered ? 'orange' : 'red', $registered ? 'vies_invalid_registered' : 'vies_invalid');
            }
        }

        // Our own proformas: what is overdue now, and how it was paid in the last 12 months.
        $open = PreInvoice::where('company_id', $c->id)->where('status', 'sent')->whereNotNull('due_date')->where('due_date', '<', today())->get();
        $paid = PreInvoice::where('company_id', $c->id)->where('status', 'paid')->whereNotNull('due_date')->whereNotNull('paid_at')->where('paid_at', '>=', now()->subYear())->get();
        if ($open->isNotEmpty() || $paid->isNotEmpty()) {
            $sources[] = 'own';
        }
        if ($open->isNotEmpty()) {
            $late = (int) $open->max(fn ($p) => $p->daysLate());
            $add($late > 60 ? 'red' : 'orange', 'overdue', ['n' => $open->count(), 'days' => $late]);
        }
        $lateOnes = $paid->filter(fn ($p) => $p->paid_at->startOfDay()->gt($p->due_date));
        if ($paid->count() >= 2 && $lateOnes->count() / $paid->count() >= 0.5) {
            $avg = (int) round($lateOnes->avg(fn ($p) => $p->due_date->diffInDays($p->paid_at->startOfDay())));
            $add('orange', 'paid_late', ['n' => $lateOnes->count(), 'of' => $paid->count(), 'days' => $avg]);
        }

        $level = collect($reasons)->contains('level', 'red') ? 'red' : (collect($reasons)->contains('level', 'orange') ? 'orange' : ($sources ? 'green' : 'unknown'));

        return ['level' => $level, 'reasons' => $reasons, 'sources' => array_values(array_unique($sources))];
    }
}
