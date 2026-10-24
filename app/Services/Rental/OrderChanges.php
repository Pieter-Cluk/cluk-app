<?php

namespace App\Services\Rental;

use App\Models\AuditLog;
use App\Models\Rental\Order;
use App\Models\Rental\OrderLine;
use App\Models\Rental\SubhireRequest;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Services\Afrit6\Pricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * After a quote is won: logistics change (other times), or the customer changes
 * or cancels. Times can move; the whole order can go back to a new quote
 * version; sub-hire partners get a clear "adjustment" message.
 */
class OrderChanges
{
    /**
     * New out / use times. Pieces of ours that are not free in the new period are
     * released (the order goes back to allocation). Returns what was released.
     *
     * @param  array{out_from: string, out_until: string, use_from: string, use_until: string}  $t
     * @return list<string>
     */
    public static function changeTimes(Order $order, array $t, bool $moveSubhire, User $by): array
    {
        abort_unless(in_array($order->status, ['allocation', 'approved'], true) && ! $order->returned_at, 409);
        $from = Carbon::parse($t['out_from']);
        $until = Carbon::parse($t['out_until']);
        if ($order->loaded_at && ! $from->equalTo($order->out_from)) {
            throw ValidationException::withMessages(['out_from' => __('orders.change.already_out')]);
        }
        $was = ['out' => $order->out_from->format('d/m H:i').' – '.$order->out_until->format('d/m H:i')];
        $shiftIn = (int) $order->out_from->diffInMinutes($from, false);
        $shiftBack = (int) $order->out_until->diffInMinutes($until, false);

        return DB::transaction(function () use ($order, $t, $from, $until, $moveSubhire, $by, $was, $shiftIn, $shiftBack) {
            $order->update(['out_from' => $from, 'out_until' => $until, 'use_from' => $t['use_from'], 'use_until' => $t['use_until']]);
            $released = [];
            if (! $order->loaded_at) {
                $avail = Orders::availability($order->fresh());
                $order->load(['lines.item', 'lines.assets.asset', 'lines.subhireLines.request']);
                foreach ($order->lines as $line) {
                    if (! $line->needsStock()) {
                        continue;
                    }
                    if ($line->bySerial()) {
                        $free = $avail->freeAssets($line->item)->pluck('id')->flip();
                        foreach ($line->assets as $oa) {
                            if (! isset($free[$oa->asset_id])) {
                                $released[] = $oa->asset->code.' · '.$line->name;
                                $oa->delete();
                            }
                        }
                    } elseif ($line->own_qty > 0) {
                        $can = max(0, (int) $avail->free($line->item));
                        if ($can < $line->own_qty) {
                            $released[] = ($line->own_qty - $can).' × '.$line->name;
                            $line->update(['own_qty' => $can]);
                        }
                    }
                }
                if ($released && $order->status === 'approved') {
                    $order->update(['status' => 'allocation', 'approved_at' => null, 'approved_by_id' => null]);
                }
            }
            if ($moveSubhire) {
                foreach ($order->subhires()->whereIn('status', ['draft', 'sent', 'accepted'])->whereNull('handed_back_at')->get() as $r) {
                    $r->update([
                        'delivery_at' => $r->received_at ? $r->delivery_at : $r->delivery_at?->copy()->addMinutes($shiftIn),
                        'return_at' => $r->return_at?->copy()->addMinutes($shiftBack),
                    ]);
                }
            }
            AuditLog::record('order.times_changed', $order, $was + ['now' => $from->format('d/m H:i').' – '.$until->format('d/m H:i'), 'released' => implode(', ', $released) ?: null], $by);

            return $released;
        });
    }

    /**
     * The customer changes or cancels: the order stops (it keeps holding its stock),
     * the accepted quote gets a new draft version to edit and send again, the deal
     * is open again and confirmed sub-hire goes back to "asked" with its prices.
     */
    public static function reopen(Order $order, string $reason, User $by): Quote
    {
        abort_unless(in_array($order->status, ['allocation', 'approved'], true) && ! $order->loaded_at, 409);
        $quote = $order->quote()->with('lines')->firstOrFail();

        return DB::transaction(function () use ($order, $quote, $reason, $by) {
            $order->update(['status' => 'reopened', 'reopened_at' => now(), 'reopened_by_id' => $by->id, 'reopen_reason' => $reason, 'approved_at' => null, 'approved_by_id' => null]);
            foreach ($order->subhires()->where('status', 'accepted')->get() as $r) {
                $r->update(['status' => 'sent']);
            }
            $copy = $quote->replicate(['token', 'sent_at', 'sent_by_id', 'sent_to', 'valid_until', 'reserved_until', 'answered_at', 'answered_name', 'answered_ip', 'decline_reason', 'answer_note']);
            $copy->version = Quote::where('number', $quote->number)->max('version') + 1;
            $copy->status = 'draft';
            // Transport may have been added on the order after the quote was won: the new version starts from that.
            $copy->fill(['logistics' => $order->logistics ?? 'open', 'transport_cents' => (int) $order->transport_cents, 'transport_note' => $order->transport_note]);
            $copy->save();
            foreach ($quote->lines as $line) {
                $copy->lines()->create($line->only(['position', 'kind', 'item_id', 'number', 'name', 'qty', 'unit_price_cents', 'factor', 'discount_bp']));
            }
            $copy->recalculate();
            $quote->update(['status' => 'replaced', 'reserved_until' => null]);
            if ($deal = $order->deal) {
                $deal->forceFill(['stage' => 'negotiation', 'closed_at' => null, 'closed_by_id' => null])->save();
                $deal->log('stage', __('orders.change.log_reopened', ['number' => $order->number, 'code' => $copy->code(), 'reason' => $reason], $by->locale ?: config('app.locale')), $by);
            }
            AuditLog::record('order.reopened', $order, ['reason' => $reason, 'quote' => $copy->code()], $by);

            return $copy;
        });
    }

    /** The new version of a reopened order's quote is accepted: the same order takes the new lines. */
    public static function takeBack(Quote $quote): ?Order
    {
        $order = Order::where('status', 'reopened')->whereIn('quote_id', Quote::where('number', $quote->number)->pluck('id'))->latest('id')->first();
        if (! $order) {
            return null;
        }

        return DB::transaction(function () use ($order, $quote) {
            $quote->loadMissing('lines.item');
            $order->update([
                'status' => 'allocation', 'quote_id' => $quote->id, 'title' => $quote->title, 'out_from' => $quote->out_from, 'out_until' => $quote->out_until,
                'use_from' => $quote->use_from, 'use_until' => $quote->use_until, 'location' => $quote->location, 'po_number' => $quote->po_number ?: $order->po_number,
                'confirmed_name' => $quote->answered_name, 'confirmed_at' => $quote->answered_at ?? now(), 'confirmed_online' => $quote->answered_ip !== null,
                'customer_email' => $quote->sent_to ?: $order->customer_email, 'project_discount_bp' => $quote->project_discount_bp, 'vat_code' => $quote->vat_code,
                'vat_rate_bp' => $quote->vat_rate_bp, 'subtotal_cents' => $quote->subtotal_cents, 'discount_cents' => $quote->discount_cents, 'net_cents' => $quote->net_cents,
                'vat_cents' => $quote->vat_cents, 'gross_cents' => $quote->gross_cents,
                'logistics' => $quote->logistics ?? 'open', 'transport_cents' => (int) $quote->transport_cents, 'transport_note' => $quote->transport_note,
            ]);
            // Keep lines (with their pieces and sub-hire) for the same item; new ones are added, removed ones go.
            $old = $order->lines()->get();
            $used = [];
            $factor = $quote->defaultFactor();
            foreach ($quote->lines as $l) {
                $match = $old->first(fn (OrderLine $o) => ! isset($used[$o->id]) && $o->kind === $l->kind && $o->item_id === $l->item_id && ($l->item_id || $o->name === $l->name));
                $data = ['position' => $l->position, 'kind' => $l->kind, 'item_id' => $l->item_id, 'number' => $l->number, 'name' => $l->name, 'qty' => $l->qty,
                    'unit_price_cents' => $l->unit_price_cents, 'factor' => $l->effectiveFactor($factor), 'discount_bp' => $l->discount_bp, 'amount_cents' => $l->amount_cents];
                if ($match) {
                    $match->update($data);
                    $used[$match->id] = true;
                } else {
                    $order->lines()->create($data);
                }
            }
            foreach ($old as $o) {
                if (! isset($used[$o->id])) {
                    $o->delete();
                }
            }
            foreach ($order->lines()->with(['assets', 'subhireLines.request', 'item'])->get() as $line) {
                if ($line->needsStock()) {
                    Orders::trimOwn($line);
                }
            }
            foreach ($order->subhires()->whereIn('status', ['draft', 'sent'])->withCount('lines')->get() as $r) {
                if ($r->lines_count === 0) {
                    $r->update(['status' => 'cancelled']);
                }
            }
            AuditLog::record('order.taken_back', $order, ['quote' => $quote->code(), 'total' => Pricing::eur($order->net_cents)]);

            return $order;
        });
    }

    /** Tell a partner what changed on a sub-hire they had confirmed. */
    public static function sendAdjustment(SubhireRequest $r, User $by, ?string $message = null): bool
    {
        abort_unless($r->wasConfirmed(), 409);
        $changes = $r->changes();
        $ok = OrderMails::subhireAdjustment($r, $by, $changes, $message);
        $r->update(['adjusted_at' => now(), 'adjust_count' => $r->adjust_count + 1, 'confirmed_snapshot' => $r->snapshot()]);
        AuditLog::record('subhire.adjusted', $r, ['changes' => count($changes), 'to' => $r->sent_to], $by);

        return $ok;
    }
}
