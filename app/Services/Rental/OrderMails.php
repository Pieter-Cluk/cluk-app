<?php

namespace App\Services\Rental;

use App\Models\BankAccount;
use App\Models\CompanyDetail;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireRequest;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Emails about orders: the order confirmation for the customer, price requests and confirmations for sub-hire partners. */
class OrderMails
{
    private static function send(string $to, string $locale, callable $build): bool
    {
        $locale = array_key_exists($locale, config('cluk.locales')) ? $locale : 'en'; // e.g. a German partner gets English
        $mail = InLocale::run($locale, $build)->locale($locale);

        return SafeMail::notify(Notification::route('mail', $to), $mail);
    }

    private static function when($date, string $format = 'D j M Y, H:i'): string
    {
        return $date ? $date->locale(app()->getLocale())->translatedFormat($format) : '—';
    }

    private static function warehouse(): string
    {
        $me = CompanyDetail::current();

        return $me->warehouse_address ?: trim($me->street.', '.$me->postcode.' '.$me->city, ', ');
    }

    private static function hello(?string $first): string
    {
        return $first ? __('mails.hello', ['name' => $first]) : __('mails.hello_plain');
    }

    public static function priceRequest(SubhireRequest $r, User $by): bool
    {
        $r->loadMissing(['supplier', 'lines']);
        $lang = $r->supplier->language ?: 'en';

        return self::send($r->sent_to, $lang, fn () => new Confirmation(
            subject: __('mails.subhire.request_subject', ['number' => $r->number]),
            greeting: __('mails.hello_plain'),
            intro: [__('mails.subhire.request_intro')],
            details: [
                __('mails.label.our_ref') => $r->number,
                __('mails.label.delivery') => self::when($r->delivery_at).' · '.self::warehouse(),
                __('mails.label.return_partner') => self::when($r->return_at),
                __('mails.label.days') => (string) $r->days(),
            ],
            rows: $r->lines->map(fn ($l) => [(string) $l->qty, $l->name, ''])->all(),
            next: [__('mails.subhire.request_next')],
            signer: $by,
        ));
    }

    public static function subhireConfirmation(SubhireRequest $r, User $by): bool
    {
        $r->loadMissing(['supplier', 'lines']);
        $lang = $r->supplier->language ?: 'en';

        return self::send($r->sent_to, $lang, fn () => new Confirmation(
            subject: __('mails.subhire.confirm_subject', ['number' => $r->confirm_number]),
            greeting: __('mails.hello_plain'),
            intro: [__('mails.subhire.confirm_intro')],
            details: array_filter([
                __('mails.label.our_ref') => $r->confirm_number.' ('.$r->number.')',
                __('mails.label.your_ref') => $r->their_ref,
                __('mails.label.delivery') => self::when($r->delivery_at).' · '.self::warehouse(),
                __('mails.label.return_partner') => self::when($r->return_at),
            ]),
            rows: $r->lines->map(fn ($l) => [(string) $l->qty, $l->name, Pricing::eur($l->amount_cents)])->all()
                + ($r->extra_cents ? [99 => ['', (string) ($r->extra_text ?: __('orders.extra')), Pricing::eur($r->extra_cents)]] : []),
            totals: [__('quotes.total_excl') => Pricing::eur($r->total_cents)],
            next: [__('mails.subhire.confirm_next')],
            signer: $by,
        ));
    }

    /** A change to a sub-hire the partner already confirmed: said clearly in the subject and the first line. */
    public static function subhireAdjustment(SubhireRequest $r, User $by, array $changes, ?string $message = null): bool
    {
        $r->loadMissing(['supplier', 'lines']);
        if (! $r->sent_to) {
            return false;
        }
        $lang = $r->supplier->language ?: 'en';

        return self::send($r->sent_to, $lang, function () use ($r, $by, $changes, $message) {
            $was = [];
            foreach ($changes as [$k, $a, $b]) {
                $was[] = $k === 'qty'
                    ? __('mails.subhire.change_qty', ['name' => $a, 'was' => $b[0], 'now' => $b[1]])
                    : __('mails.subhire.change_'.$k, ['was' => self::when($a), 'now' => self::when($b)]);
            }

            return new Confirmation(
                subject: __('mails.subhire.adjust_subject', ['number' => $r->confirm_number]),
                greeting: __('mails.hello_plain'),
                intro: array_values(array_filter([__('mails.subhire.adjust_intro', ['number' => $r->confirm_number]), ...array_map(fn ($w) => '• '.$w, $was ?: [__('mails.subhire.no_changes')]), $message])),
                details: array_filter([
                    __('mails.label.our_ref') => $r->confirm_number.' ('.$r->number.')',
                    __('mails.label.your_ref') => $r->their_ref,
                    __('mails.label.delivery') => self::when($r->delivery_at).' · '.self::warehouse(),
                    __('mails.label.return_partner') => self::when($r->return_at),
                ]),
                rows: $r->lines->map(fn ($l) => [(string) $l->qty, $l->name, $l->amount_cents ? Pricing::eur($l->amount_cents) : ''])->all(),
                totals: $r->total_cents ? [__('quotes.total_excl') => Pricing::eur($r->total_cents)] : [],
                next: [__('mails.subhire.adjust_next')],
                signer: $by,
            );
        });
    }

    /** The order behind a confirmed sub-hire is cancelled. */
    public static function subhireCancelled(SubhireRequest $r, string $reason, User $by): bool
    {
        $r->loadMissing(['supplier', 'lines']);

        return self::send($r->sent_to, $r->supplier->language ?: 'en', fn () => new Confirmation(
            subject: __('mails.subhire.cancel_subject', ['number' => $r->confirm_number]),
            greeting: __('mails.hello_plain'),
            intro: [__('mails.subhire.cancel_intro', ['number' => $r->confirm_number])],
            details: array_filter([__('mails.label.our_ref') => $r->confirm_number.' ('.$r->number.')', __('mails.label.your_ref') => $r->their_ref]),
            rows: $r->lines->map(fn ($l) => [(string) $l->qty, $l->name, ''])->all(),
            next: [__('mails.subhire.cancel_next')],
            signer: $by,
        ));
    }

    public static function orderConfirmation(Order $o, User $by): bool
    {
        $o->loadMissing(['lines', 'contact', 'quote']);

        return self::send($o->customer_email, $o->language, function () use ($o, $by) {
            $rows = $o->lines->map(fn ($l) => $l->kind === 'text' ? ['', '*'.$l->name.'*', ''] : [$l->qtyLabel(), $l->name, Pricing::eur($l->amount_cents)])->all();
            $totals = [];
            if ($o->discount_cents) {
                $totals[__('quotes.project_discount')] = '− '.Pricing::eur($o->discount_cents);
            }
            $totals[__('quotes.total_excl')] = Pricing::eur($o->net_cents);
            $totals[__('quotes.vat', ['pct' => $o->vatPercent()])] = Pricing::eur($o->vat_cents);
            $totals[__('quotes.total')] = Pricing::eur($o->gross_cents);
            $first = $o->contact?->first_name ?? ($o->confirmed_name ? Str::before($o->confirmed_name, ' ') : null);

            return new Confirmation(
                subject: __('mails.order.subject', ['title' => $o->title, 'number' => $o->number]),
                greeting: self::hello($first),
                intro: [__('mails.order.intro', ['title' => $o->title])],
                details: array_filter([
                    __('mails.label.order') => $o->number,
                    __('mails.label.period') => self::when($o->use_from, 'j M').' – '.self::when($o->use_until, 'j M Y'),
                    __('mails.label.pickup') => self::when($o->out_from),
                    __('mails.label.return') => self::when($o->out_until),
                    __('quotes.location') => $o->location,
                    __('mails.label.po') => $o->po_number,
                ]),
                rows: $rows, totals: $totals,
                next: [__('mails.order.next', ['address' => self::warehouse()]), __('mails.quote.terms_line')],
                button: $o->quote?->token ? [__('mails.order.button'), route('quote.public.order', $o->quote->token)] : null,
                signer: $by,
            );
        });
    }

    /** The proforma: what to pay before collection, with the bank details. */
    public static function proforma(Order $o, User $by): bool
    {
        $o->loadMissing(['contact', 'quote']);
        $bank = BankAccount::where('on_documents', true)->orderByDesc('is_main')->first();

        return self::send($o->customer_email, $o->language, function () use ($o, $by, $bank) {
            $number = str_replace('R-OC', 'R-PF', $o->number);
            $first = $o->contact?->first_name ?? ($o->confirmed_name ? Str::before($o->confirmed_name, ' ') : null);

            return new Confirmation(
                subject: __('mails.pf.subject', ['title' => $o->title, 'number' => $number]),
                greeting: self::hello($first),
                intro: [__('mails.pf.intro', ['title' => $o->title])],
                details: array_filter([
                    __('mails.label.amount') => Pricing::eur($o->gross_cents),
                    __('mails.label.iban') => $bank?->formattedIban(),
                    __('mails.label.bic') => $bank?->bic,
                    __('mails.label.beneficiary') => trim(CompanyDetail::current()->legal_name.' '.CompanyDetail::current()->legal_form),
                    __('mails.label.reference') => $number,
                ]),
                next: [__('mails.pf.next')],
                button: $o->quote?->token ? [__('mails.pf.button'), route('quote.public.proforma', $o->quote->token)] : null,
                signer: $by,
            );
        });
    }

    public static function tellSalesperson(Order $o, User $by): bool
    {
        $seller = $o->handedBy;
        $locale = $seller->locale ?: 'en';
        $mail = InLocale::run($locale, fn () => new Confirmation(
            subject: __('mails.order.staff_subject', ['title' => $o->title, 'number' => $o->number]),
            greeting: self::hello($seller->first_name),
            intro: [__('mails.order.staff_intro', ['name' => $by->name, 'number' => $o->number, 'customer' => $o->customerName(), 'email' => $o->customer_email ?: '—'])],
            button: [__('mails.order.staff_button'), route('orders.show', $o)],
        ))->locale($locale);

        return SafeMail::notify($seller, $mail);
    }
}
