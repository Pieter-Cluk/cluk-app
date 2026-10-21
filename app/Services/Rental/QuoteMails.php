<?php

namespace App\Services\Rental;

use App\Models\Sales\Quote;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Emails to the customer about a quote, in the quote's language, signed by the salesperson. */
class QuoteMails
{
    private static function deliver(Quote $quote, callable $build): bool
    {
        if (! $quote->sent_to) {
            return false;
        }
        $mail = InLocale::run($quote->language, $build)->locale($quote->language);

        return SafeMail::notify(Notification::route('mail', $quote->sent_to), $mail);
    }

    private static function hello(Quote $quote, ?string $name = null): string
    {
        $first = $name ? Str::before(trim($name), ' ') : $quote->contact?->first_name;

        return $first ? __('mails.hello', ['name' => $first]) : __('mails.hello_plain');
    }

    /** @return array<string, string> */
    private static function facts(Quote $quote): array
    {
        $fmt = fn ($d, $f) => $d->locale(app()->getLocale())->translatedFormat($f);

        return [
            __('mails.label.quote') => $quote->code().' · '.$quote->title,
            __('mails.label.period') => $fmt($quote->use_from, 'j M').' – '.$fmt($quote->use_until, 'j M Y'),
            __('mails.label.pickup') => $fmt($quote->out_from, 'D j M, H:i'),
            __('mails.label.return') => $fmt($quote->out_until, 'D j M, H:i'),
        ];
    }

    /** @return array{0: array<int, array{0: string, 1: string, 2: string}>, 1: array<string, string>} */
    private static function table(Quote $quote): array
    {
        $rows = $quote->lines->map(fn ($l) => $l->kind === 'text'
            ? ['', '*'.$l->name.'*', '']
            : [$l->qtyLabel(), $l->name, Pricing::eur($l->amount_cents)])->all();
        $totals = [__('quotes.total_excl') => Pricing::eur($quote->net_cents)];
        if ($quote->discount_cents) {
            $totals = [__('quotes.project_discount') => '− '.Pricing::eur($quote->discount_cents)] + $totals;
        }
        $totals[__('quotes.vat', ['pct' => $quote->vatPercent()])] = Pricing::eur($quote->vat_cents);
        $totals[__('quotes.total')] = Pricing::eur($quote->gross_cents);

        return [$rows, $totals];
    }

    public static function sent(Quote $quote, ?string $message, User $sender): bool
    {
        return self::deliver($quote, function () use ($quote, $message, $sender) {
            [$rows, $totals] = self::table($quote);
            $intro = $message ? preg_split('/\R{2,}/', trim($message)) : [__('mails.quote.sent_intro', ['title' => $quote->title])];

            return new Confirmation(
                subject: __('mails.quote.sent_subject', ['title' => $quote->title]),
                greeting: self::hello($quote),
                intro: $intro,
                details: self::facts($quote),
                next: [__('mails.quote.sent_next', ['date' => $quote->reserved_until?->locale(app()->getLocale())->translatedFormat('j F, H:i')])],
                button: [__('mails.quote.sent_button'), route('quote.public', $quote->token)],
                signer: $sender, rows: $rows, totals: $totals,
            );
        });
    }

    public static function accepted(Quote $quote): bool
    {
        return self::deliver($quote, function () use ($quote) {
            [$rows, $totals] = self::table($quote);
            $details = self::facts($quote) + array_filter([
                __('mails.label.confirmed_by') => $quote->answered_name,
                __('mails.label.po') => $quote->po_number,
                __('mails.label.when') => $quote->answered_at?->locale(app()->getLocale())->translatedFormat('j F Y, H:i'),
            ]);

            return new Confirmation(
                subject: __('mails.quote.accepted_subject', ['title' => $quote->title]),
                greeting: self::hello($quote, $quote->answered_name),
                intro: [__('mails.quote.accepted_intro', ['name' => Str::before((string) $quote->answered_name, ' '), 'code' => $quote->code()])],
                details: $details,
                next: [__('mails.quote.accepted_next'), __('mails.quote.terms_line')],
                button: [__('mails.quote.button_view'), route('quote.public', $quote->token)],
                signer: $quote->owner, rows: $rows, totals: $totals,
            );
        });
    }

    public static function change(Quote $quote): bool
    {
        return self::deliver($quote, fn () => new Confirmation(
            subject: __('mails.quote.change_subject', ['title' => $quote->title]),
            greeting: self::hello($quote, $quote->answered_name),
            intro: [__('mails.quote.change_intro', ['code' => $quote->code()])],
            details: [__('mails.label.quote') => $quote->code().' · '.$quote->title, __('mails.label.request') => (string) $quote->answer_note],
            next: [__('mails.quote.change_next', ['name' => $quote->owner?->first_name ?? 'CLUK'])],
            signer: $quote->owner,
        ));
    }

    public static function declined(Quote $quote): bool
    {
        return self::deliver($quote, fn () => new Confirmation(
            subject: __('mails.quote.declined_subject', ['title' => $quote->title]),
            greeting: self::hello($quote),
            intro: [__('mails.quote.declined_intro', ['code' => $quote->code()])],
            details: array_filter([__('mails.label.quote') => $quote->code().' · '.$quote->title, __('mails.label.reason') => __('quotes.decline_reasons.'.$quote->decline_reason), __('mails.label.request') => $quote->answer_note]),
            next: [__('mails.quote.declined_next')],
            signer: $quote->owner,
        ));
    }
}
