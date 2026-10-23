<?php

namespace App\Services\Website;

use App\Models\Equipment\Item;
use App\Models\Sales\IncomingMessage;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Requests sent from the website land in Deals › Incoming, with a copy to the visitor. */
class WebRequests
{
    /**
     * A dry-hire request: lines (item id => qty), dates of use and contact details.
     * Only items the website shows are taken; the price is today's day price.
     */
    public static function dryHire(array $d, string $locale): IncomingMessage
    {
        $from = Carbon::parse($d['first_day'])->startOfDay();
        $until = Carbon::parse($d['last_day'])->startOfDay();
        $days = (int) $from->diffInDays($until) + 1;
        $factor = DryHireCatalogue::factor($days);

        $qty = collect($d['lines'] ?? [])->mapWithKeys(fn ($l) => [(int) $l['id'] => (int) $l['qty']])->filter(fn ($q) => $q > 0);
        $items = DryHireCatalogue::query()->whereKey($qty->keys())->get()->keyBy('id');
        $lines = $qty->filter(fn ($q, $id) => $items->has($id))->map(fn ($q, $id) => [
            'item_id' => $id, 'number' => $items[$id]->number, 'name' => (string) $items[$id]->name, 'qty' => $q, 'day_price_cents' => (int) $items[$id]->day_price_cents,
        ])->values();
        $perDay = $lines->sum(fn ($l) => $l['qty'] * $l['day_price_cents']);
        $estimate = (int) round($perDay * $factor);

        $date = fn (Carbon $c) => $c->locale($locale)->translatedFormat('D j M Y');
        $nf = fn (float $f) => $locale === 'en' ? (string) $f : str_replace('.', ',', (string) $f);

        // The text staff read in Incoming is always in English (the visitor's language is noted).
        $body = InLocale::run('en', fn () => implode("\n", array_filter([
            __('site.request.heading'),
            __('site.request.use', ['from' => $from->format('D j M Y'), 'until' => $until->format('D j M Y'), 'd' => $days, 'f' => $factor]),
            '',
            ...$lines->map(fn ($l) => $l['day_price_cents'] > 0
                ? __('site.request.line', ['qty' => $l['qty'], 'name' => $l['name'], 'number' => $l['number'], 'price' => Pricing::eur($l['day_price_cents'])])
                : __('site.request.line_on_request', ['qty' => $l['qty'], 'name' => $l['name'], 'number' => $l['number']]))->all(),
            '',
            $perDay ? __('site.request.per_day', ['amount' => Pricing::eur($perDay)]).' · '.__('site.request.estimate', ['amount' => Pricing::eur($estimate)]) : null,
            ! empty($d['company']) ? __('site.request.company', ['v' => $d['company']]) : null,
            ! empty($d['vat']) ? __('site.request.vat', ['v' => $d['vat']]) : null,
            __('site.request.language', ['v' => config('cluk.locales.'.$locale, $locale)]),
            ! empty($d['message']) ? "\n".__('site.request.message')."\n".$d['message'] : null,
        ], fn ($v) => $v !== null)));

        $msg = new IncomingMessage([
            'channel' => 'website', 'module' => 'dryhire', 'received_at' => now(),
            'subject' => InLocale::run('en', fn () => __('site.request.subject', ['n' => $lines->count()])).' · '.$from->format('j M').' – '.$until->format('j M Y'),
            'body' => Str::limit($body, 60000, ''),
            'payload' => [
                'type' => 'dryhire', 'locale' => $locale, 'first_day' => $from->toDateString(), 'last_day' => $until->toDateString(),
                'days' => $days, 'factor' => $factor, 'lines' => $lines->all(), 'per_day_cents' => $perDay, 'estimate_cents' => $estimate,
                'vat' => $d['vat'] ?? null, 'page' => $d['page'] ?? null,
            ],
            'from_name' => $d['name'], 'from_email' => Str::lower($d['email']), 'from_phone' => $d['phone'] ?? null, 'company_name' => $d['company'] ?? null,
        ]);
        $msg->matchKnown();
        $msg->save();

        $mail = InLocale::run($locale, fn () => new Confirmation(
            subject: __('site.request.mail_subject', ['dates' => $date($from).' – '.$date($until)]),
            greeting: ($first = Str::before(trim($d['name']), ' ')) !== '' ? __('mails.hello', ['name' => $first]) : __('mails.hello_plain'),
            intro: [__('site.request.mail_intro')],
            details: [
                __('site.request.mail_dates') => $date($from).' – '.$date($until).' ('.$days.' · '.$nf($factor).')',
                __('site.request.mail_ref') => 'W-'.$msg->id,
            ],
            next: [__('site.request.mail_next')],
            rows: $lines->map(fn ($l) => [$l['qty'], $l['name'], $l['day_price_cents'] ? Pricing::eur($l['qty'] * $l['day_price_cents']) : '–'])->all(),
            totals: $perDay ? [__('site.request.mail_per_day') => Pricing::eur($perDay), __('site.request.mail_estimate') => Pricing::eur($estimate)] : [],
            team: __('site.request.mail_team'),
        ))->locale($locale);
        SafeMail::notify(Notification::route('mail', $msg->from_email), $mail);

        return $msg;
    }
}
