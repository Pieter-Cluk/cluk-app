<?php

namespace App\Services\Purchasing;

use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Emails to suppliers: price requests (PQ) and purchase orders (PO), in the supplier's language (English otherwise). */
class PurchaseMails
{
    private static function send(Company $s, string $to, callable $build): bool
    {
        $locale = array_key_exists((string) $s->language, config('cluk.locales')) ? $s->language : 'en';

        return SafeMail::notify(Notification::route('mail', $to), InLocale::run($locale, $build)->locale($locale));
    }

    private static function deliverTo(): string
    {
        $me = CompanyDetail::current();

        return $me->warehouse_address ?: trim($me->street.', '.$me->postcode.' '.$me->city, ', ');
    }

    public static function request(PurchaseRequest $pq, Company $s, Collection $lines, string $to, User $by): bool
    {
        return self::send($s, $to, fn () => new Confirmation(
            subject: __('purchasing.mail.request_subject', ['number' => $pq->number]),
            greeting: __('mails.hello_plain'),
            intro: [__('purchasing.mail.request_intro')],
            details: array_filter([
                __('mails.label.our_ref') => $pq->number,
                __('purchasing.mail.needed') => $pq->needed_by?->locale(app()->getLocale())->translatedFormat('j F Y'),
                __('purchasing.mail.deliver_to') => self::deliverTo(),
            ]),
            rows: $lines->map(fn ($l) => [(string) $l->qty, $l->name.($l->item ? ' · '.$l->item->number : ''), ''])->values()->all(),
            next: [__('purchasing.mail.request_next')],
            signer: $by,
        ));
    }

    public static function order(PurchaseOrder $po, string $to, User $by): bool
    {
        return self::send($po->supplier, $to, fn () => new Confirmation(
            subject: __('purchasing.mail.order_subject', ['number' => $po->number]),
            greeting: __('mails.hello_plain'),
            intro: [__('purchasing.mail.order_intro')],
            details: [__('mails.label.our_ref') => $po->number, __('purchasing.mail.deliver_to') => self::deliverTo()],
            rows: $po->lines->map(fn ($l) => [(string) $l->qty, $l->name.($l->delivery_date ? ' · '.$l->delivery_date->format('d/m/Y') : ''), Pricing::eur($l->amount_cents)])->all(),
            totals: [__('purchasing.total_excl') => Pricing::eur($po->total_cents)],
            next: [__('purchasing.mail.order_next')],
            signer: $by,
        ));
    }
}
