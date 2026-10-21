<?php

namespace App\Services\ProductSales;

use App\Models\ProductSales\SalesQuote;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Notification;

/** Emails to the customer about a sale, in the quote's language. */
class SalesMails
{
    private static function deliver(SalesQuote $q, callable $build): bool
    {
        if (! $q->sent_to) {
            return false;
        }
        $loc = array_key_exists($q->language, config('cluk.locales')) ? $q->language : 'en';

        return SafeMail::notify(Notification::route('mail', $q->sent_to), InLocale::run($loc, $build)->locale($loc));
    }

    private static function hello(SalesQuote $q): string
    {
        return $q->contact?->first_name ? __('mails.hello', ['name' => $q->contact->first_name]) : __('mails.hello_plain');
    }

    private static function table(SalesQuote $q, bool $dates = false): array
    {
        $rows = $q->lines->map(fn ($l) => $l->kind === 'text' ? ['', '*'.$l->name.'*', ''] : [(string) $l->qty, $l->name.($dates && $l->delivery_date ? ' · '.__('psales.delivery_on', ['date' => $l->delivery_date->format('d/m/Y')]) : ''), Pricing::eur($l->amount_cents)])->all();

        return [$rows, [__('psales.total_excl') => Pricing::eur($q->net_cents), __('quotes.vat', ['pct' => $q->vatPercent()]) => Pricing::eur($q->vat_cents), __('quotes.total') => Pricing::eur($q->gross_cents)]];
    }

    public static function sent(SalesQuote $q, ?string $message, User $by): bool
    {
        return self::deliver($q, function () use ($q, $message, $by) {
            [$rows, $totals] = self::table($q);

            return new Confirmation(
                subject: __('psales.mail.sent_subject', ['title' => $q->title]),
                greeting: self::hello($q),
                intro: $message ? preg_split('/\R{2,}/', trim($message)) : [__('psales.mail.sent_intro')],
                details: [__('mails.label.quote') => $q->number.' · '.$q->title, __('psales.valid_until') => $q->valid_until?->format('d/m/Y') ?? '—'],
                next: [__('psales.mail.sent_next')],
                button: [__('psales.mail.button'), route('psales.public', $q->token)],
                signer: $by, rows: $rows, totals: $totals,
            );
        });
    }

    public static function accepted(SalesQuote $q): bool
    {
        return self::deliver($q, function () use ($q) {
            [$rows, $totals] = self::table($q);

            return new Confirmation(
                subject: __('psales.mail.accepted_subject', ['number' => $q->number]),
                greeting: self::hello($q),
                intro: [__('psales.mail.accepted_intro')],
                details: array_filter([__('mails.label.quote') => $q->number, __('quotes.po') => $q->po_number]),
                next: [__('psales.mail.accepted_next')],
                signer: $q->owner, rows: $rows, totals: $totals,
            );
        });
    }

    public static function confirmed(SalesQuote $q, User $by): bool
    {
        return self::deliver($q, function () use ($q, $by) {
            [$rows, $totals] = self::table($q, true);

            return new Confirmation(
                subject: __('psales.mail.oc_subject', ['number' => $q->docCode('S-OC')]),
                greeting: self::hello($q),
                intro: [__('psales.mail.oc_intro')],
                details: array_filter([__('psales.oc') => $q->docCode('S-OC'), __('quotes.po') => $q->po_number,
                    __('psales.delivery') => __('psales.delivery_modes.'.$q->delivery_mode)]),
                next: [__('psales.mail.oc_next')],
                button: [__('psales.mail.button_oc'), route('psales.public', $q->token)],
                signer: $by, rows: $rows, totals: $totals,
            );
        });
    }
}
