<?php

namespace App\Services\Purchasing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Purchasing\Reply;
use App\Models\User;
use App\Services\Afrit6\Pricing;
use App\Services\Rental\Orders;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchasing works like sub-hire: one price request goes to every supplier of the
 * items, we compare and choose per item, then one purchase order per supplier.
 * The supplier's delivery date goes to the sales order confirmation.
 */
class Purchasing
{
    /** Add working days (Saturday and Sunday skipped). */
    public static function workdays(Carbon $from, int $n): Carbon
    {
        $d = $from->copy();
        while ($n > 0) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $n--;
            }
        }

        return $d;
    }

    /** @param  list<array{item: SalesItem, qty: int, purpose?: string, quote_line_id?: int}>  $lines */
    public static function create(array $lines, User $by, ?SalesQuote $quote = null, ?Carbon $neededBy = null, ?string $note = null): PurchaseRequest
    {
        return DB::transaction(function () use ($lines, $by, $quote, $neededBy, $note) {
            $pq = PurchaseRequest::create(['number' => PurchaseRequest::nextNumber('PQ'), 'status' => 'draft', 'sales_quote_id' => $quote?->id,
                'needed_by' => $neededBy, 'note' => $note, 'created_by_id' => $by->id]);
            $suppliers = [];
            foreach ($lines as $l) {
                $pq->lines()->create(['sales_item_id' => $l['item']->id, 'sales_quote_line_id' => $l['quote_line_id'] ?? null, 'name' => $l['item']->name(),
                    'qty' => $l['qty'], 'purpose' => $l['purpose'] ?? 'sales']);
                foreach ($l['item']->suppliers as $s) {
                    $suppliers[$s->id] = true;
                }
            }
            $pq->suppliers()->sync(array_keys($suppliers));
            AuditLog::record('purchase.requested', $pq, ['lines' => count($lines), 'for' => $quote?->number], $by);

            return $pq;
        });
    }

    /** Email every supplier the lines it carries. Returns the suppliers without an email address. */
    public static function send(PurchaseRequest $pq, User $by): array
    {
        $pq->load(['lines.item.suppliers', 'suppliers']);
        $missing = [];
        foreach ($pq->suppliers as $s) {
            $lines = $pq->lines->filter(fn ($l) => ! $l->item || $l->item->suppliers->contains('id', $s->id));
            $to = Orders::supplierEmail($s);
            if (! $to || $lines->isEmpty()) {
                $missing[] = $s->name;

                continue;
            }
            PurchaseMails::request($pq, $s, $lines, $to, $by);
            $pq->suppliers()->updateExistingPivot($s->id, ['sent_at' => now()]);
        }
        $pq->update(['status' => 'sent', 'sent_at' => now()]);
        AuditLog::record('purchase.sent', $pq, ['suppliers' => $pq->suppliers->count()], $by);

        return $missing;
    }

    /**
     * Store the replies typed in: per line and supplier a net price (or their discount on the list
     * price) and a delivery date. Picks the cheapest when nobody chose yet.
     *
     * @param  array<int, array<int, array{disc?: ?string, net?: ?string, date?: ?string}>>  $data  line id => company id => values
     */
    public static function saveReplies(PurchaseRequest $pq, array $data, array $choose, User $by): void
    {
        $pq->load('lines.item.suppliers');
        DB::transaction(function () use ($pq, $data, $choose) {
            foreach ($pq->lines as $line) {
                foreach ($data[$line->id] ?? [] as $companyId => $v) {
                    $net = null;
                    if (($v['net'] ?? '') !== '' && $v['net'] !== null) {
                        $net = (int) round((float) str_replace(',', '.', (string) $v['net']) * 100);
                    } elseif (($v['disc'] ?? '') !== '' && $v['disc'] !== null && $line->item) {
                        $net = (int) round($line->item->list_price_cents * (1 - (float) str_replace(',', '.', (string) $v['disc']) / 100));
                    }
                    $date = ($v['date'] ?? null) ?: null;
                    if ($net === null && ! $date) {
                        Reply::where('purchase_request_line_id', $line->id)->where('company_id', $companyId)->delete();

                        continue;
                    }
                    Reply::updateOrCreate(['purchase_request_line_id' => $line->id, 'company_id' => $companyId], ['net_cents' => $net, 'delivery_date' => $date]);
                    $pq->suppliers()->updateExistingPivot($companyId, ['replied_at' => now()]);
                }
                $line->load('replies');
                $pick = $choose[$line->id] ?? null;
                if ($pick && $line->replies->contains('company_id', (int) $pick)) {
                    $line->update(['chosen_company_id' => (int) $pick]);
                } elseif (! $line->chosen_company_id && ($c = $line->cheapest())) {
                    $line->update(['chosen_company_id' => $c->company_id]);
                }
            }
        });
    }

    /** One purchase order per chosen supplier; the sales lines get the expected delivery date. */
    public static function order(PurchaseRequest $pq, User $by): array
    {
        $pq->load(['lines.replies', 'salesQuote']);
        if ($pq->lines->contains(fn ($l) => ! $l->chosen_company_id || ! $l->replies->firstWhere('company_id', $l->chosen_company_id)?->net_cents)) {
            throw ValidationException::withMessages(['choose' => __('purchasing.errors.choose_all')]);
        }

        return DB::transaction(function () use ($pq, $by) {
            $orders = [];
            foreach ($pq->lines->groupBy('chosen_company_id') as $companyId => $lines) {
                $po = PurchaseOrder::create(['number' => PurchaseRequest::nextNumber('PO'), 'purchase_request_id' => $pq->id, 'company_id' => $companyId, 'status' => 'sent',
                    'sent_at' => now(), 'created_by_id' => $by->id]);
                $total = 0;
                foreach ($lines as $l) {
                    $r = $l->replies->firstWhere('company_id', $l->chosen_company_id);
                    $po->lines()->create(['sales_item_id' => $l->sales_item_id, 'sales_quote_line_id' => $l->sales_quote_line_id, 'name' => $l->name, 'qty' => $l->qty,
                        'unit_cents' => $r->net_cents, 'amount_cents' => $r->net_cents * $l->qty, 'delivery_date' => $r->delivery_date, 'purpose' => $l->purpose]);
                    $total += $r->net_cents * $l->qty;
                    $l->quoteLine?->update(['cost_cents' => $r->net_cents]);
                }
                $po->update(['total_cents' => $total]);
                AuditLog::record('purchase.ordered', $po, ['supplier' => Company::find($companyId)?->name, 'total' => Pricing::eur($total)], $by);
                $to = Orders::supplierEmail($po->supplier);
                if ($to) {
                    PurchaseMails::order($po->load(['lines', 'supplier']), $to, $by);
                }
                $orders[] = $po;
            }
            $pq->update(['status' => 'ordered']);
            $pq->salesQuote?->recalculate();

            return $orders;
        });
    }

    /** The supplier confirmed: their reference and the delivery dates. The sales lines get the date + 1 working day. */
    public static function confirm(PurchaseOrder $po, ?string $ref, array $dates, User $by): void
    {
        DB::transaction(function () use ($po, $ref, $dates, $by) {
            foreach ($po->lines as $l) {
                $d = $dates[$l->id] ?? null;
                $l->update(['delivery_date' => $d ?: $l->delivery_date]);
                if ($l->quoteLine && $l->delivery_date) {
                    $l->quoteLine->update(['delivery_date' => self::workdays($l->delivery_date, 1)]);
                }
            }
            $po->update(['status' => 'confirmed', 'confirmed_at' => now(), 'their_ref' => $ref ?: $po->their_ref]);
            AuditLog::record('purchase.confirmed', $po, ['ref' => $ref], $by);
        });
    }

    /** Goods in: sales stock goes up (rental fleet pieces are added in Dry-hire). */
    public static function receive(PurchaseOrder $po, array $qty, User $by): void
    {
        DB::transaction(function () use ($po, $qty, $by) {
            foreach ($po->lines as $l) {
                $n = max(0, min((int) ($qty[$l->id] ?? $l->qty - $l->received_qty), $l->qty - $l->received_qty));
                if ($n === 0) {
                    continue;
                }
                $l->update(['received_qty' => $l->received_qty + $n]);
                if ($l->purpose === 'sales' && $l->item) {
                    $l->item->increment('stock_qty', $n);
                }
            }
            $done = $po->lines()->get()->every(fn ($l) => $l->received_qty >= $l->qty);
            $po->update(['status' => $done ? 'received' : $po->status, 'received_at' => $done ? now() : $po->received_at]);
            AuditLog::record('purchase.received', $po, ['complete' => $done], $by);
        });
    }

    public static function invoice(PurchaseOrder $po, int $cents, bool $accept, User $by): void
    {
        $po->update(['invoice_cents' => $cents, 'invoice_checked_at' => ($accept || $cents === $po->total_cents) ? now() : null]);
        AuditLog::record('purchase.invoice', $po, ['invoice' => Pricing::eur($cents), 'po' => Pricing::eur($po->total_cents)], $by);
    }
}
