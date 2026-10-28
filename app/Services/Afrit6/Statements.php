<?php

namespace App\Services\Afrit6;

use App\Models\Afrit6\Statement;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\User;
use App\Services\Finance\PreInvoices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Afrit 6 monthly statements: the bill of one contract month becomes a proforma in pre-invoicing.
 * Made by hand on the member's page, and automatically the day after each member's contract month ends.
 */
class Statements
{
    /** The statement of the contract month around $day; null when there is nothing to bill or it exists already. */
    public static function make(Member $member, Carbon $day, ?User $by = null): ?Statement
    {
        $bill = (new StatementBuilder($member))->build($day);
        if (! $bill['lines'] || Statement::where('member_id', $member->id)->whereDate('period_start', $bill['start']->toDateString())->exists()) {
            return null;
        }
        $statement = Statement::create([
            'member_id' => $member->id, 'company_id' => $member->company_id, 'period_start' => $bill['start'], 'period_end' => $bill['end'],
            'lines' => $bill['lines'], 'total_cents' => $bill['total_cents'], 'created_by_id' => $by?->id,
        ]);
        PreInvoices::make('afrit6', $statement, $by);
        AuditLog::record('afrit6.statement_created', $member, [
            'period' => $bill['start']->format('d/m/Y').' – '.$bill['end']->format('d/m/Y'), 'total' => Pricing::eur($statement->total_cents),
            'auto' => $by ? null : true,
        ], $by);

        return $statement;
    }

    /** Every member whose contract month ended yesterday (or earlier, not billed yet in the last 7 days): the statement is made. */
    public static function monthly(?Carbon $today = null): int
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $n = 0;
        foreach (Member::where('status', Member::ACTIVE)->get() as $m) {
            try {
                $builder = new StatementBuilder($m);
                for ($back = 1; $back <= 7; $back++) {
                    $day = $today->copy()->subDays($back);
                    [$start, $end] = $builder->period($day);
                    if ($end->isSameDay($day) && self::make($m, $day)) {
                        $n++;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Afrit 6 statement failed', ['member' => $m->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }
        Cache::put('afrit6.statements.ran', now()->toIso8601String(), 86400 * 7);

        return $n;
    }
}
