<?php

namespace App\Services\Storage;

use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Finance\PreInvoice;
use App\Models\Sales\LabourType;
use App\Models\Storage\StorageCheck;
use App\Models\Storage\StorageContract;
use App\Models\Storage\StorageMove;
use App\Models\Storage\StoragePlace;
use App\Models\Storage\StorageStatement;
use App\Models\Storage\StoredItem;
use App\Models\Storage\StoredPiece;
use App\Models\User;
use App\Models\Warehouse\Position;
use App\Services\Afrit6\Pricing;
use App\Services\Afrit6\StatementBuilder;
use App\Services\Finance\PreInvoices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer storage: a customer or supplier keeps equipment at CLUK. Afrit 6 prices: shelf or pallet place per day,
 * handling per colli / pallet in and out, and Tech Checks by CLUK at the procedure's duration × the technician rate.
 * One statement per calendar month, through pre-invoicing.
 */
class CustomerStorage
{
    public const PLACE_USE = 'customer';

    /** Place code => the contract holding it now. */
    public static function held(?int $except = null): array
    {
        return StoragePlace::with('position', 'contract.company')->whereNull('until')->when($except, fn ($q) => $q->where('contract_id', '!=', $except))->get()
            ->mapWithKeys(fn ($p) => [$p->position->code => $p->contract])->all();
    }

    public static function addPlace(StorageContract $c, Position $p, string $kind, Carbon $from, User $by): StoragePlace
    {
        if ($p->use !== self::PLACE_USE || ! $p->active) {
            throw ValidationException::withMessages(['position_id' => __('storage.errors.not_customer_place', ['place' => $p->code])]);
        }
        if ($other = self::held()[$p->code] ?? null) {
            throw ValidationException::withMessages(['position_id' => __('storage.errors.place_taken', ['place' => $p->code, 'name' => $other->company->name])]);
        }
        $place = $c->places()->create(['position_id' => $p->id, 'kind' => $kind, 'from' => $from]);
        AuditLog::record('storage.place_added', $c, ['place' => $p->code, 'kind' => $kind, 'from' => $from->toDateString()], $by);

        return $place;
    }

    public static function endPlace(StoragePlace $place, Carbon $until, User $by): void
    {
        $place->update(['until' => $until->lt($place->from) ? $place->from : $until]);
        AuditLog::record('storage.place_ended', $place->contract, ['place' => $place->position->code, 'until' => $place->until->toDateString()], $by);
    }

    /** A kind of equipment with its pieces; each piece gets our label. */
    public static function addItem(StorageContract $c, array $d, int $pieces, array $serials, User $by): StoredItem
    {
        return DB::transaction(function () use ($c, $d, $pieces, $serials, $by) {
            $item = $c->items()->create($d);
            for ($i = 0; $i < $pieces; $i++) {
                $item->pieces()->create(['code' => StoredPiece::nextCode(), 'serial_number' => trim((string) ($serials[$i] ?? '')) ?: null, 'status' => 'out']);
            }
            AuditLog::record('storage.item_added', $c, ['name' => $item->name, 'pieces' => $pieces], $by);

            return $item;
        });
    }

    /** Goods in or out: handling per colli and pallet, the pieces scanned or ticked. */
    public static function move(StorageContract $c, string $kind, array $d, User $by): StorageMove
    {
        return DB::transaction(function () use ($c, $kind, $d, $by) {
            $ids = $c->pieces()->whereIn('stored_pieces.id', $d['pieces'] ?? [])->pluck('stored_pieces.id')->all();
            $m = $c->moves()->create(['kind' => $kind, 'date' => $d['date'], 'colis' => (int) ($d['colis'] ?? 0), 'pallets' => (int) ($d['pallets'] ?? 0),
                'pieces' => $ids, 'signed_name' => $d['signed_name'] ?? null, 'note' => $d['note'] ?? null, 'user_id' => $by->id]);
            StoredPiece::whereIn('id', $ids)->update(['status' => $kind === 'in' ? 'in' : 'out'] + ($kind === 'out' ? ['position_code' => null] : []));
            AuditLog::record('storage.moved_'.$kind, $c, ['colis' => $m->colis, 'pallets' => $m->pallets, 'pieces' => count($ids)], $by);

            return $m;
        });
    }

    public static function hourRate(): int
    {
        return (int) (LabourType::for('sales')->where('unit', 'hour')->value('price_cents') ?? 6000);
    }

    /**
     * The lines of one period: places per day, handling, checks by CLUK.
     *
     * @return array<int, array{d: string, q: string, unit_cents: int, amount_cents: int, note: null}>
     */
    public static function lines(StorageContract $c, Carbon $start, Carbon $end, string $lang): array
    {
        $d = fn (Carbon $x) => $x->format('d/m');
        $lines = [];
        foreach ($c->places()->with('position')->get() as $p) {
            $from = $p->from->max($start);
            $to = ($p->until ?? $end)->min($end);
            if ($to->lt($from)) {
                continue;
            }
            $days = StatementBuilder::days($from, $to);
            $unit = Pricing::dayPrice($p->kind);
            $lines[] = ['d' => __('storage.bill.place_'.$p->kind, ['place' => $p->position->code, 'from' => $d($from), 'to' => $d($to), 'days' => $days], $lang), 'q' => (string) $days, 'unit_cents' => $unit, 'amount_cents' => $days * $unit, 'note' => null];
        }
        foreach ($c->moves()->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->sortBy('date') as $m) {
            if ($amount = Pricing::handling($m->colis, $m->pallets)) {
                $lines[] = ['d' => __('storage.bill.handling_'.$m->kind, ['date' => $d($m->date), 'what' => StatementBuilder::counts($m->colis, $m->pallets, $lang)], $lang), 'q' => '1', 'unit_cents' => $amount, 'amount_cents' => $amount, 'note' => null];
            }
        }
        $rate = self::hourRate();
        $checks = StorageCheck::with('piece.item')->where('done_by', 'cluk')->whereNotNull('finished_at')->whereBetween('finished_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereHas('piece.item', fn ($q) => $q->where('contract_id', $c->id))->orderBy('finished_at')->get();
        foreach ($checks as $ch) {
            $amount = (int) round($rate * (int) $ch->minutes / 60);
            $lines[] = ['d' => __('storage.bill.check', ['code' => $ch->piece->code, 'name' => $ch->piece->item->name, 'date' => $d($ch->finished_at), 'minutes' => (int) $ch->minutes], $lang),
                'q' => rtrim(rtrim(number_format($ch->minutes / 60, 2, '.', ''), '0'), '.'), 'unit_cents' => $rate, 'amount_cents' => $amount, 'note' => null];
        }

        return $lines;
    }

    /** The statement of one calendar month → pre-invoicing (once per month). */
    public static function statement(StorageContract $c, Carbon $month, ?User $by = null): ?StorageStatement
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        if ($c->statements()->whereDate('period_start', $start->toDateString())->exists()) {
            throw ValidationException::withMessages(['month' => __('storage.errors.already_billed')]);
        }
        $company = $c->company;
        $lang = $company->language ?: 'nl';
        $lines = self::lines($c, $start, $end, $lang);
        if (! $lines) {
            return null;
        }

        return DB::transaction(function () use ($c, $start, $end, $lines, $company, $by) {
            $me = CompanyDetail::current();
            $rate = $company->vatRate($end->toDateString());
            $net = (int) collect($lines)->sum('amount_cents');
            $vat = (int) round($net * ($rate?->rate_bp ?? 0) / 10000);
            $st = $c->statements()->create(['period_start' => $start, 'period_end' => $end]);
            $number = PreInvoice::nextNumber();
            $pf = PreInvoice::create([
                'number' => $number, 'source_type' => 'storage', 'source_id' => $st->id, 'source_code' => $c->code().'-'.$start->format('Ym'),
                'module' => 'warehouse', 'company_id' => $company->id, 'owner_id' => $company->account_owner_id ?? $by?->id, 'made_by_id' => $by?->id,
                'description' => __('storage.bill.title', ['month' => $start->locale($company->language ?: 'nl')->translatedFormat('F Y')], $company->language ?: 'nl'),
                'lines' => $lines, 'vat_code' => $rate?->code, 'vat_rate_bp' => $rate?->rate_bp ?? 0, 'net_cents' => $net, 'cost_cents' => 0, 'vat_cents' => $vat, 'gross_cents' => $net + $vat,
                'payment_days' => (int) ($company->customer_payment_days ?? $me->payment_term_days ?? 30), 'service_date' => $end, 'status' => 'check',
            ]);
            $pf->update(['checks' => PreInvoices::checks($pf->fresh(['company', 'member']))]);
            $st->update(['pre_invoice_id' => $pf->id]);
            AuditLog::record('storage.statement', $c, ['month' => $start->format('Y-m'), 'pre_invoice' => $pf->number, 'total' => Pricing::eur($pf->gross_cents)], $by);

            return $st;
        });
    }

    /** First of the month: last month's statement for every contract that ran. */
    public static function monthly(?Carbon $month = null): int
    {
        $month ??= today()->subMonth();
        $n = 0;
        foreach (StorageContract::with('company')->whereDate('start_date', '<=', $month->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $month->copy()->startOfMonth()))->get() as $c) {
            if (! $c->statements()->whereDate('period_start', $month->copy()->startOfMonth()->toDateString())->exists() && self::statement($c, $month)) {
                $n++;
            }
        }

        return $n;
    }
}
