<?php

namespace App\Services\Warehouse;

use App\Models\AuditLog;
use App\Models\Rental\Order;
use App\Models\User;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\ChargeNotice;
use App\Models\Warehouse\Check;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Services\Finance\PreInvoices;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Return costs (missing parts, cleaning, damage) of one order: once every piece of the order is checked, the
 * customer's account owner reviews them and informs the customer. Without a reaction within the deadline they are
 * invoiced (R-CH, through pre-invoicing); a reaction goes back to the account owner.
 */
class CostNotices
{
    /** The salesperson linked to the customer (not who entered the order); else who handled the order. */
    public static function owner(Order $order): ?User
    {
        return $order->company?->accountOwner ?? $order->handedBy;
    }

    public static function canReview(User $u, Order $order): bool
    {
        return self::owner($order)?->is($u) || $u->hasRight('orders.approve');
    }

    /** Every piece of the order is back and through its check. */
    public static function allChecked(Order $order): bool
    {
        if (! $order->returned_at) {
            return false;
        }
        $assets = $order->assets()->pluck('asset_id');
        $checked = Check::where('order_id', $order->id)->whereNotNull('finished_at')->whereIn('asset_id', $assets)->distinct()->count('asset_id');

        return $checked >= $assets->count() && ! Check::where('order_id', $order->id)->whereNull('finished_at')->exists();
    }

    /** After a check, a return or a change: is the order ready for the account owner? */
    public static function refresh(?Order $order): ?ChargeNotice
    {
        if (! $order || ! self::allChecked($order) || ! Charge::where('order_id', $order->id)->where('status', 'pending')->exists()) {
            return null;
        }
        $notice = ChargeNotice::firstOrCreate(['order_id' => $order->id], ['status' => 'review']);
        if ($notice->wasRecentlyCreated && ($owner = self::owner($order)) && $owner->email) {
            $mail = InLocale::run($owner->locale ?: 'en', fn () => new Confirmation(
                subject: __('checks.notice.owner_subject', ['order' => $order->number]),
                greeting: __('mails.hello', ['name' => $owner->first_name]),
                intro: [__('checks.notice.owner_intro', ['order' => $order->number, 'customer' => $order->customerName()])],
                button: ['label' => __('checks.notice.owner_button'), 'url' => route('warehouse.charges', $order)],
            ));
            $mail->banner = false;
            SafeMail::notify(Notification::route('mail', $owner->email), $mail);
        }

        return $notice;
    }

    public static function emailFor(Order $order): ?string
    {
        return $order->contact?->email ?: ($order->quote?->sent_to ?: $order->company?->email);
    }

    /** The account owner informs the customer: the costs, the proof, and until when they can react. */
    public static function send(Order $order, User $by, ?string $to = null): ChargeNotice
    {
        $charges = Charge::where('order_id', $order->id)->where('status', 'pending')->get();
        if ($charges->isEmpty()) {
            throw ValidationException::withMessages(['charges' => __('checks.notice.nothing')]);
        }
        if ($charges->contains(fn ($c) => $c->amount_cents === null)) {
            throw ValidationException::withMessages(['amount' => __('warehouse.office.price_first')]);
        }
        $to = $to ?: self::emailFor($order);
        if (! $to) {
            throw ValidationException::withMessages(['to' => __('checks.notice.no_email')]);
        }
        $notice = ChargeNotice::firstOrNew(['order_id' => $order->id]);
        $notice->fill(['status' => 'sent', 'token' => $notice->token ?: Str::random(48), 'sent_to' => $to, 'sent_at' => now(), 'sent_by_id' => $by->id,
            'deadline' => today()->addDays((int) config('warehouse.costs_days', 7)), 'objection' => null, 'objected_at' => null])->save();
        Charge::whereIn('id', $charges->pluck('id'))->update(['status' => 'informed']);
        $lang = array_key_exists($order->language, config('cluk.locales')) ? $order->language : 'en';
        $mail = InLocale::run($lang, fn () => new Confirmation(
            subject: __('checks.notice.subject', ['order' => $order->number, 'title' => $order->title]),
            greeting: $order->contact?->first_name ? __('mails.hello', ['name' => $order->contact->first_name]) : __('mails.hello_plain'),
            intro: array_values(array_filter([__('checks.notice.intro', ['title' => $order->title]), $charges->contains('kind', 'missing') ? __('checks.notice.incomplete') : null])),
            next: [__('checks.notice.next', ['date' => $notice->deadline->locale($lang)->translatedFormat('j F Y')])],
            button: ['label' => __('checks.notice.button'), 'url' => route('costs.public', $notice->token)],
            signer: $by,
            rows: $charges->map(fn ($c) => [(string) $c->qty, $c->label, Pricing::eur($c->amount_cents)])->all(),
            totals: [__('checks.notice.total') => Pricing::eur((int) $charges->sum('amount_cents'))],
        ))->locale($lang);
        SafeMail::notify(Notification::route('mail', $to), $mail);
        AuditLog::record('warehouse.costs_sent', $order, ['to' => $to, 'total' => Pricing::eur((int) $charges->sum('amount_cents')), 'deadline' => $notice->deadline->toDateString()], $by);

        return $notice;
    }

    /** The customer reacts: back to the account owner, who can change the lines and inform again or invoice. */
    public static function object(ChargeNotice $notice, string $text): void
    {
        abort_unless($notice->status === 'sent', 409);
        DB::transaction(function () use ($notice, $text) {
            $notice->update(['status' => 'objected', 'objection' => mb_substr($text, 0, 2000), 'objected_at' => now()]);
            Charge::where('order_id', $notice->order_id)->where('status', 'informed')->update(['status' => 'pending']);
        });
        $order = $notice->order;
        AuditLog::record('warehouse.costs_objected', $order, ['text' => mb_substr($text, 0, 300)]);
        if (($owner = self::owner($order)) && $owner->email) {
            $mail = InLocale::run($owner->locale ?: 'en', fn () => new Confirmation(
                subject: __('checks.notice.objected_subject', ['order' => $order->number]),
                greeting: __('mails.hello', ['name' => $owner->first_name]),
                intro: [__('checks.notice.objected_intro', ['customer' => $order->customerName()]), '“'.$notice->objection.'”'],
                button: ['label' => __('checks.notice.owner_button'), 'url' => route('warehouse.charges', $order)],
            ));
            $mail->banner = false;
            SafeMail::notify(Notification::route('mail', $owner->email), $mail);
        }
    }

    /** Invoice: the costs go to pre-invoicing as R-CH (after the deadline, or by the account owner). */
    public static function invoice(Order $order, ?User $by = null): void
    {
        DB::transaction(function () use ($order, $by) {
            $by ??= ChargeNotice::where('order_id', $order->id)->first()?->sentBy ?? self::owner($order);
            $open = Charge::where('order_id', $order->id)->whereIn('status', ['pending', 'informed'])->get();
            if ($open->contains(fn ($c) => $c->amount_cents === null)) {
                throw ValidationException::withMessages(['amount' => __('warehouse.office.price_first')]);
            }
            foreach ($open as $c) {
                $c->update(['status' => 'approved', 'decided_by_id' => $by?->id, 'decided_at' => now()]);
            }
            ChargeNotice::where('order_id', $order->id)->update(['status' => 'invoiced', 'closed_at' => now()]);
            if ($open->isNotEmpty()) {
                PreInvoices::make('charges', $order, $by);
            }
            AuditLog::record('warehouse.charges_approved', $order, ['total' => Pricing::eur((int) $open->sum('amount_cents')), 'lines' => $open->count()], $by);
        });
    }

    /** Daily: the deadline passed without a reaction. */
    public static function invoiceDue(): int
    {
        $n = 0;
        foreach (ChargeNotice::with('order')->where('status', 'sent')->whereDate('deadline', '<', today())->get() as $notice) {
            self::invoice($notice->order);
            $n++;
        }

        return $n;
    }
}
