<?php

namespace App\Services\ProductSales;

use App\Models\Purchasing\PurchaseRequest;
use App\Models\AuditLog;
use App\Models\ProductSales\SalesQuote;
use App\Models\User;
use App\Services\Afrit6\Pricing;
use App\Services\Finance\PreInvoices;
use App\Services\Purchasing\Purchasing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Quote → (approval under 10% margin) → sent → accepted (price request for what is not in stock) → S-OC with dates → delivered. */
class SalesFlow
{
    public static function approve(SalesQuote $q, User $by, ?string $note): void
    {
        $q->load('lines');
        $q->update(['approved_by_id' => $by->id, 'approved_at' => now(), 'approved_sig' => $q->signature(), 'approval_note' => $note]);
        AuditLog::record('psales.approved', $q, ['margin' => number_format($q->marginBp() / 100, 1).'%', 'note' => $note], $by);
    }

    public static function send(SalesQuote $q, string $to, ?string $message, User $by): void
    {
        $q->load('lines');
        if ($q->lines->where('kind', 'item')->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('psales.errors.no_lines')]);
        }
        if ($q->needsApproval()) {
            throw ValidationException::withMessages(['lines' => __('psales.errors.needs_approval')]);
        }
        $q->recalculate();
        $q->update(['status' => 'sent', 'sent_at' => now(), 'sent_to' => $to, 'token' => $q->token ?: Str::random(48),
            'valid_until' => $q->valid_until ?? today()->addDays(30)]);
        AuditLog::record('psales.sent', $q, ['to' => $to, 'total' => Pricing::eur($q->net_cents)], $by);
        // The deal moves on, as with a rental quote.
        if ($deal = $q->deal) {
            $deal->forceFill(['value_cents' => $q->net_cents])->save();
            if (in_array($deal->stage, ['new', 'qualified'], true)) {
                $from = $deal->stage;
                $deal->update(['stage' => 'proposal']);
                $deal->log('stage', __('deals.log.moved', ['from' => __('deals.stages.'.$from), 'to' => __('deals.stages.proposal')]));
            }
            $deal->log('email', __('quotes.log.sent', ['code' => $q->code(), 'to' => $to, 'total' => Pricing::eur($q->net_cents)]));
        }
        SalesMails::sent($q->fresh(['lines', 'contact', 'owner']), $message, $by);
    }

    /**
     * Ask the suppliers for prices before the customer has accepted (draft or sent quote): what is not in free stock.
     * Returns the request, or null when everything is in stock.
     */
    public static function requestPrices(SalesQuote $q, User $by): ?PurchaseRequest
    {
        $buy = [];
        foreach ($q->lines()->with('item.suppliers')->get() as $l) {
            if ($l->kind !== 'item' || ! $l->item) {
                continue;
            }
            $short = $l->qty - min($l->qty, $l->item->freeStock($q->id));
            if ($short > 0) {
                $buy[] = ['item' => $l->item, 'qty' => $short, 'purpose' => 'sales', 'quote_line_id' => $l->id];
            }
        }
        if (! $buy) {
            return null;
        }
        $need = collect($buy)->max(fn ($b) => $b['item']->leadDays());
        $pq = Purchasing::create($buy, $by, $q, Purchasing::workdays(today(), $need + 1), __('psales.early_note', ['quote' => $q->number]));
        Purchasing::send($pq, $by);

        return $pq;
    }

    /** Accepted: pieces in stock are kept for this sale, the rest goes to a price request to the suppliers. */
    public static function accept(SalesQuote $q, string $name, ?string $po, ?User $by = null): void
    {
        DB::transaction(function () use ($q, $name, $po, $by) {
            $q->update(['status' => 'accepted', 'answered_at' => now(), 'answered_name' => $name, 'po_number' => $po ?: null]);
            $buy = [];
            foreach ($q->lines()->with('item.suppliers')->get() as $l) {
                if ($l->kind !== 'item' || ! $l->item) {
                    continue;
                }
                $from = min($l->qty, $l->item->freeStock($q->id));
                $l->update(['from_stock' => $from]);
                if ($l->qty > $from) {
                    $buy[] = ['item' => $l->item, 'qty' => $l->qty - $from, 'purpose' => 'sales', 'quote_line_id' => $l->id];
                }
            }
            AuditLog::record('psales.accepted', $q, ['name' => $name, 'po' => $po, 'total' => Pricing::eur($q->net_cents)], $by);
            if (($deal = $q->deal) && $deal->isOpen()) {
                $deal->log('stage', __('quotes.log.accepted', ['code' => $q->code(), 'name' => $name, 'po' => $po ?: '—']), $by);
                $deal->forceFill(['stage' => 'won', 'closed_at' => now(), 'closed_by_id' => $by?->id ?? $deal->owner_id, 'value_cents' => $q->net_cents])->save();
            }
            // Prices already asked before the acceptance: that request is used, no second one goes out.
            $early = $q->requests()->whereNotIn('status', ['cancelled'])->exists();
            if ($buy && ! $early) {
                $owner = $q->owner ?? $by ?? User::query()->orderBy('id')->first();
                $need = collect($buy)->max(fn ($b) => $b['item']->leadDays());
                $pq = Purchasing::create($buy, $owner, $q, Purchasing::workdays(today(), $need + 1));
                Purchasing::send($pq, $owner);
            }
        });
        if (! $by) {
            SalesMails::accepted($q->fresh(['lines', 'contact', 'owner']));
        }
    }

    public static function decline(SalesQuote $q, ?string $reason): void
    {
        $q->update(['status' => 'declined', 'answered_at' => now(), 'decline_reason' => $reason]);
        AuditLog::record('psales.declined', $q, ['reason' => $reason]);
        $q->deal?->log('system', __('psales.log_declined', ['code' => $q->code(), 'reason' => $reason ?: '—']));
    }

    /** Order confirmation (S-OC) with the delivery dates, once every piece to buy has a confirmed date. */
    public static function confirm(SalesQuote $q, User $by): void
    {
        $q->load('lines');
        if (! $q->datesIn()) {
            throw ValidationException::withMessages(['confirm' => __('psales.errors.dates_missing')]);
        }
        foreach ($q->lines as $l) {
            if ($l->kind === 'item' && $l->toBuy() === 0 && ! $l->delivery_date) {
                $l->update(['delivery_date' => Purchasing::workdays(today(), 1)]);
            }
        }
        $q->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        AuditLog::record('psales.confirmed', $q, ['number' => $q->docCode('S-OC')], $by);
        SalesMails::confirmed($q->fresh(['lines', 'contact', 'owner']), $by);
    }

    /** Handed over: the stock goes down. */
    public static function deliver(SalesQuote $q, User $by): void
    {
        DB::transaction(function () use ($q, $by) {
            foreach ($q->lines()->with('item')->get() as $l) {
                if ($l->kind === 'item' && $l->item) {
                    $l->item->decrement('stock_qty', $l->qty);
                }
            }
            $q->update(['status' => 'delivered', 'delivered_at' => now()]);
            AuditLog::record('psales.delivered', $q, ['number' => $q->docCode('S-DN')], $by);
        });
        PreInvoices::make('sales', $q->fresh(), $by);
    }
}
