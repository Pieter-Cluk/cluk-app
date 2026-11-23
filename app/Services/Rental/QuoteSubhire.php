<?php

namespace App\Services\Rental;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireLine;
use App\Models\Rental\SubhireRequest;
use App\Models\Sales\Quote;
use App\Models\Sales\QuoteLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sub-hire price requests asked while the quote is being made, so the quote is priced
 * on the partner's real price. Same SHQ as on an order (one per partner), but on the
 * quote's lines. A new version takes them along; when the customer accepts, the order
 * takes them over and they carry on as normal: confirm, receive, hand back.
 */
class QuoteSubhire
{
    public static function add(Quote $quote, QuoteLine $line, Company $supplier, int $qty, string $transport, User $by): SubhireRequest
    {
        return DB::transaction(function () use ($quote, $line, $supplier, $qty, $transport, $by) {
            $request = SubhireRequest::firstOrCreate(
                ['quote_id' => $quote->id, 'order_id' => null, 'supplier_id' => $supplier->id, 'status' => 'draft'],
                [
                    'number' => SubhireRequest::nextNumber('SHQ'), 'created_by_id' => $by->id,
                    'delivery_at' => $quote->out_from->copy()->subDay()->setTime(16, 0),
                    'return_at' => $quote->out_until->copy()->addDay()->setTime(10, 0),
                ],
            );
            $request->update(['transport' => $transport]);
            $existing = $request->lines()->where('quote_line_id', $line->id)->first();
            if ($existing) {
                $existing->update(['qty' => $qty]);
            } else {
                $request->lines()->create([
                    'quote_line_id' => $line->id, 'item_id' => $line->item_id, 'name' => $line->name, 'qty' => $qty,
                    'last_price_cents' => SubhireLine::lastPrice($supplier->id, $line->item_id),
                    'factor' => Quote::factorFor($request->days()),
                ]);
            }
            AuditLog::record('subhire.quote_added', $request, ['quote' => $quote->code(), 'item' => $line->name, 'qty' => $qty], $by);

            return $request;
        });
    }

    /** Send every draft request of this quote. Returns the partners without an email address. */
    public static function send(Quote $quote, User $by): array
    {
        $missing = [];
        foreach ($quote->subhires()->where('status', 'draft')->with(['supplier', 'lines'])->get() as $request) {
            if ($request->lines->isEmpty()) {
                $request->delete();

                continue;
            }
            $to = Orders::supplierEmail($request->supplier);
            if (! $to) {
                $missing[] = $request->supplier->name;

                continue;
            }
            $request->update(['status' => 'sent', 'sent_at' => now(), 'sent_to' => $to]);
            AuditLog::record('subhire.sent', $request, ['to' => $to, 'supplier' => $request->supplier->name, 'quote' => $quote->code()], $by);
            OrderMails::priceRequest($request, $by);
        }

        return $missing;
    }

    /** The newest partner price per quote line (per piece per day), for pricing the quote. */
    public static function prices(Quote $quote): array
    {
        $out = [];
        foreach ($quote->subhires()->whereIn('status', ['draft', 'sent', 'not_available'])->with(['supplier', 'lines'])->get() as $r) {
            foreach ($r->lines as $l) {
                $out[$l->quote_line_id][] = ['request' => $r, 'line' => $l];
            }
        }

        return $out;
    }

    /** A new version of the quote takes the open requests along (lines matched by position). */
    public static function moveToVersion(Quote $old, Quote $new): void
    {
        $map = self::lineMap($old->lines()->get(), $new->lines()->get());
        foreach (SubhireRequest::where('quote_id', $old->id)->whereNull('order_id')->with('lines')->get() as $r) {
            $r->update(['quote_id' => $new->id]);
            foreach ($r->lines as $l) {
                $l->update(['quote_line_id' => $map[$l->quote_line_id] ?? null]);
            }
        }
    }

    /** The customer accepted: the order takes the requests over (order lines matched by position). */
    public static function toOrder(Quote $quote, Order $order): void
    {
        $requests = SubhireRequest::where('quote_id', $quote->id)->whereNull('order_id')->with('lines')->get();
        if ($requests->isEmpty()) {
            return;
        }
        $map = self::lineMap($quote->lines()->get(), $order->lines()->get());
        foreach ($requests as $r) {
            $r->update(['order_id' => $order->id]);
            foreach ($r->lines as $l) {
                if ($id = $map[$l->quote_line_id] ?? null) {
                    $l->update(['order_line_id' => $id]);
                } elseif (in_array($r->status, ['draft', 'sent'], true)) {
                    $l->delete(); // the line was taken off the quote
                }
            }
            if ($r->status === 'draft' && $r->lines()->count() === 0) {
                $r->delete();
            }
        }
        foreach ($order->lines()->with(['assets', 'subhireLines.request', 'item'])->get() as $line) {
            if ($line->needsStock()) {
                Orders::trimOwn($line); // pieces rented in no longer need own stock
            }
        }
    }

    /** Quote line id => line id in the copy, by position and item. */
    private static function lineMap($from, $to): array
    {
        $map = [];
        foreach ($from as $a) {
            $b = $to->first(fn ($b) => (int) $b->position === (int) $a->position && $b->item_id === $a->item_id);
            if ($b) {
                $map[$a->id] = $b->id;
            }
        }

        return $map;
    }
}
