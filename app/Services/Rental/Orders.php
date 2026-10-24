<?php

namespace App\Services\Rental;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Rental\OrderLine;
use App\Models\Rental\SubhireLine;
use App\Models\Rental\SubhireRequest;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Services\Afrit6\Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The work on a rental order: from accepted quote to approved order confirmation. */
class Orders
{
    /** The customer accepted: the order lands in Order allocation. */
    public static function fromQuote(Quote $quote): Order
    {
        if ($existing = $quote->order) {
            return $existing;
        }
        if ($reopened = OrderChanges::takeBack($quote)) {
            return $reopened;
        }

        return DB::transaction(function () use ($quote) {
            $quote->loadMissing('lines.item');
            $order = Order::create([
                'number' => Order::nextNumber(), 'status' => 'allocation', 'quote_id' => $quote->id, 'deal_id' => $quote->deal_id,
                'company_id' => $quote->company_id, 'contact_id' => $quote->contact_id, 'title' => $quote->title, 'language' => $quote->language,
                'out_from' => $quote->out_from, 'out_until' => $quote->out_until, 'use_from' => $quote->use_from, 'use_until' => $quote->use_until,
                'location' => $quote->location, 'po_number' => $quote->po_number, 'confirmed_name' => $quote->answered_name,
                'confirmed_at' => $quote->answered_at ?? now(), 'confirmed_online' => $quote->answered_ip !== null,
                'customer_email' => $quote->sent_to, 'handed_by_id' => $quote->owner_id,
                'project_discount_bp' => $quote->project_discount_bp, 'vat_code' => $quote->vat_code, 'vat_rate_bp' => $quote->vat_rate_bp,
                'subtotal_cents' => $quote->subtotal_cents, 'discount_cents' => $quote->discount_cents, 'net_cents' => $quote->net_cents,
                'vat_cents' => $quote->vat_cents, 'gross_cents' => $quote->gross_cents,
                'logistics' => $quote->logistics ?? 'open', 'transport_cents' => (int) $quote->transport_cents, 'transport_note' => $quote->transport_note,
            ]);
            $factor = $quote->defaultFactor();
            foreach ($quote->lines as $l) {
                $order->lines()->create([
                    'position' => $l->position, 'kind' => $l->kind, 'item_id' => $l->item_id, 'number' => $l->number, 'name' => $l->name,
                    'qty' => $l->qty, 'unit_price_cents' => $l->unit_price_cents, 'factor' => $l->effectiveFactor($factor),
                    'discount_bp' => $l->discount_bp, 'amount_cents' => $l->amount_cents,
                ]);
            }
            AuditLog::record('order.created', $order, ['quote' => $quote->code(), 'total' => Pricing::eur($order->net_cents)]);

            return $order;
        });
    }

    public static function availability(Order $order): Availability
    {
        return new Availability($order->out_from, $order->out_until, null, $order->id);
    }

    /** Book own stock for one line: serial pieces (best free first) or a count. */
    public static function autoAllocateLine(Order $order, OrderLine $line, ?Availability $avail = null): void
    {
        if (! $line->needsStock()) {
            return;
        }
        $avail ??= self::availability($order);
        $line->loadMissing(['assets', 'subhireLines.request', 'item.contents']);
        $missing = $line->ownNeeded() - $line->ownAllocated();
        if ($missing <= 0) {
            return;
        }
        if ($line->bySerial()) {
            $mine = $order->assets()->pluck('asset_id')->all();
            $pick = $avail->freeAssets($line->item)->reject(fn ($a) => in_array($a->id, $mine, true))->take($missing);
            foreach ($pick as $asset) {
                OrderAsset::create(['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $asset->id]);
            }
        } else {
            $free = (int) $avail->free($line->item);
            $line->update(['own_qty' => min($line->ownNeeded(), max(0, $free - self::countedElsewhereOnOrder($order, $line)))]);
        }
        $line->unsetRelation('assets');
    }

    /** Counted pieces of the same item already booked on other lines of this order. */
    private static function countedElsewhereOnOrder(Order $order, OrderLine $line): int
    {
        return (int) $order->lines()->where('item_id', $line->item_id)->whereKeyNot($line->id)->sum('own_qty');
    }

    public static function autoAllocate(Order $order): void
    {
        $avail = self::availability($order);
        foreach ($order->lines()->with(['item.contents', 'assets', 'subhireLines.request'])->get() as $line) {
            self::autoAllocateLine($order, $line, $avail);
        }
    }

    public static function clearLine(OrderLine $line): void
    {
        $line->assets()->delete();
        $line->update(['own_qty' => 0]);
    }

    /** Ask a partner for pieces of a line: joins the draft request for that partner, or starts one. */
    public static function addSubhire(Order $order, OrderLine $line, Company $supplier, int $qty, User $by): SubhireRequest
    {
        $request = SubhireRequest::firstOrCreate(
            ['order_id' => $order->id, 'supplier_id' => $supplier->id, 'status' => 'draft'],
            [
                'number' => SubhireRequest::nextNumber('SHQ'), 'created_by_id' => $by->id,
                'delivery_at' => $order->out_from->copy()->subDay()->setTime(16, 0),
                'return_at' => $order->out_until->copy()->addDay()->setTime(10, 0),
            ],
        );
        $existing = $request->lines()->where('order_line_id', $line->id)->first();
        if ($existing) {
            $existing->update(['qty' => $existing->qty + $qty]);
        } else {
            $request->lines()->create([
                'order_line_id' => $line->id, 'item_id' => $line->item_id, 'name' => $line->name, 'qty' => $qty,
                'last_price_cents' => SubhireLine::lastPrice($supplier->id, $line->item_id),
                'factor' => Quote::factorFor($request->days()),
            ]);
        }
        // Pieces now rented in no longer need own stock: give back what is over.
        self::trimOwn($line->fresh(['assets', 'subhireLines.request', 'item']));

        return $request;
    }

    /** Own stock never covers more than what is still needed. */
    public static function trimOwn(OrderLine $line): void
    {
        $over = $line->ownAllocated() - $line->ownNeeded();
        if ($over <= 0) {
            return;
        }
        if ($line->bySerial()) {
            $line->assets()->latest('id')->take($over)->get()->each->delete();
        } else {
            $line->update(['own_qty' => $line->ownNeeded()]);
        }
    }

    /** Send every draft request of the order to its partner. Returns the partners without an email address. */
    public static function sendRequests(Order $order, User $by): array
    {
        $missing = [];
        foreach ($order->subhires()->where('status', 'draft')->with(['supplier', 'lines'])->get() as $request) {
            if ($request->lines->isEmpty()) {
                $request->delete();

                continue;
            }
            $to = self::supplierEmail($request->supplier);
            if (! $to) {
                $missing[] = $request->supplier->name;

                continue;
            }
            $request->update(['status' => 'sent', 'sent_at' => now(), 'sent_to' => $to]);
            AuditLog::record('subhire.sent', $request, ['to' => $to, 'supplier' => $request->supplier->name], $by);
            OrderMails::priceRequest($request, $by);
        }

        return $missing;
    }

    public static function supplierEmail(Company $supplier): ?string
    {
        return $supplier->email ?: $supplier->contacts()->whereNotNull('email')->orderByDesc('company_contact.is_main')->value('email');
    }

    /** The partner can deliver at the prices typed in: confirm (SHC). */
    public static function acceptSubhire(SubhireRequest $request, User $by): void
    {
        $request->loadMissing('lines');
        if (! $request->hasPrices()) {
            throw ValidationException::withMessages(['price' => __('orders.errors.prices_first')]);
        }
        $request->recalculate();
        $again = $request->wasConfirmed();
        $request->update(['status' => 'accepted', 'confirm_number' => $request->confirm_number ?? SubhireRequest::nextNumber('SHC'), 'accepted_at' => now(), 'accepted_by_id' => $by->id, 'answered_at' => $request->answered_at ?? now()]);
        AuditLog::record('subhire.accepted', $request, ['supplier' => $request->supplier->name, 'total' => Pricing::eur($request->total_cents), 'again' => $again ?: null], $by);
        if ($again) {
            // Confirmed before: the partner gets an adjustment, not a second confirmation.
            OrderChanges::sendAdjustment($request->fresh('lines'), $by);
        } else {
            if ($request->sent_to) {
                OrderMails::subhireConfirmation($request, $by);
            }
            $request->update(['confirmed_snapshot' => $request->fresh('lines')->snapshot()]);
        }
    }

    public static function notAvailable(SubhireRequest $request, User $by): void
    {
        $request->update(['status' => 'not_available', 'answered_at' => now()]);
        AuditLog::record('subhire.not_available', $request, ['supplier' => $request->supplier->name], $by);
    }

    /** Every line covered: approve and send the order confirmation. */
    public static function approve(Order $order, User $by): bool
    {
        $order->load(['lines.item', 'lines.assets', 'lines.subhireLines.request']);
        if ($order->lines->contains(fn (OrderLine $l) => ! $l->isCovered())) {
            throw ValidationException::withMessages(['order' => __('orders.errors.not_covered')]);
        }
        $order->update(['status' => 'approved', 'approved_at' => now(), 'approved_by_id' => $by->id, 'assignee_id' => $order->assignee_id ?? $by->id]);
        AuditLog::record('order.approved', $order, ['to' => $order->customer_email, 'total' => Pricing::eur($order->net_cents)], $by);
        $order->deal?->log('stage', __('orders.log.approved', ['number' => $order->number], $order->handedBy?->locale ?: config('app.locale')), $by);
        $sent = $order->customer_email ? OrderMails::orderConfirmation($order, $by) : false;
        if ($order->handedBy && ! $order->handedBy->is($by)) {
            OrderMails::tellSalesperson($order, $by);
        }

        return $sent;
    }

    public static function cancel(Order $order, string $reason, User $by): void
    {
        DB::transaction(function () use ($order, $reason, $by) {
            $order->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $order->assets()->delete();
            foreach ($order->subhires()->whereIn('status', ['draft', 'sent', 'accepted'])->with('supplier')->get() as $r) {
                // A partner who had confirmed gets a cancellation; open requests just close.
                if ($r->wasConfirmed() && ! $r->handed_back_at && $r->sent_to) {
                    OrderMails::subhireCancelled($r, $reason, $by);
                }
                if ($r->status !== 'accepted' || ! $r->received_at) {
                    $r->update(['status' => 'cancelled']);
                }
            }
            AuditLog::record('order.cancelled', $order, ['reason' => $reason], $by);
        });
    }
}
