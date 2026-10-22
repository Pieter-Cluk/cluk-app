<?php

namespace App\Services\Rental;

use App\Models\AuditLog;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Notifications\QuoteAnswered;
use App\Services\Afrit6\Pricing;
use App\Services\Factory\Bookings;
use App\Support\StaffMail;

/** What happens when the customer accepts, asks for a change or declines. */
class QuoteAnswer
{
    /** Deal notes are written in the salesperson's language, not the customer's. */
    private static function loc(Quote $quote, ?User $by = null): string
    {
        return $by?->locale ?: ($quote->owner?->locale ?: config('app.locale'));
    }

    public static function accept(Quote $quote, string $name, ?string $po, ?string $ip, ?User $by = null): void
    {
        $quote->forceFill([
            'status' => 'accepted', 'answered_at' => now(), 'answered_name' => $name, 'po_number' => $po ?: null, 'answered_ip' => $ip,
        ])->save();
        // A Cluk Factory quote confirms the venue booking; a rental quote becomes an order.
        if ($booking = Bookings::forQuote($quote)) {
            Bookings::confirm($booking, $by);
        } else {
            Orders::fromQuote($quote);
        }
        AuditLog::record('quote.accepted', $quote, array_filter(['name' => $name, 'po' => $po, 'total' => Pricing::eur($quote->net_cents), 'by_staff' => $by?->name]), $by);

        if ($deal = $quote->deal) {
            $deal->log('stage', __('quotes.log.accepted', ['code' => $quote->code(), 'name' => $name, 'po' => $po ?: '—'], self::loc($quote, $by)), $by);
            if ($deal->isOpen()) {
                $deal->forceFill(['stage' => 'won', 'closed_at' => now(), 'closed_by_id' => $by?->id ?? $deal->owner_id, 'value_cents' => $quote->net_cents])->save();
                if ($deal->company && ! $deal->company->is_customer) {
                    $deal->company->forceFill(['is_customer' => true])->save();
                }
            }
        }
        if (! $by) {
            if (! $booking) {
                QuoteMails::accepted($quote); // a venue booking gets its booking confirmation instead
            }
            self::tellOwner($quote, 'accepted');
        }
    }

    public static function askChange(Quote $quote, string $name, string $message, ?string $ip): void
    {
        $quote->forceFill(['status' => 'change', 'answered_at' => now(), 'answered_name' => $name ?: null, 'answer_note' => $message, 'answered_ip' => $ip])->save();
        AuditLog::record('quote.change', $quote, ['name' => $name]);
        $quote->deal?->log('email', __('quotes.log.change', ['code' => $quote->code(), 'name' => $name ?: '—'], self::loc($quote))."\n".$message);
        QuoteMails::change($quote);
        self::tellOwner($quote, 'change');
    }

    public static function decline(Quote $quote, string $reason, ?string $note, ?string $ip): void
    {
        $quote->forceFill(['status' => 'declined', 'answered_at' => now(), 'decline_reason' => $reason, 'answer_note' => $note, 'answered_ip' => $ip, 'reserved_until' => null])->save();
        AuditLog::record('quote.declined', $quote, ['reason' => $reason]);
        $quote->deal?->log('email', __('quotes.log.declined', ['code' => $quote->code(), 'reason' => __('quotes.decline_reasons.'.$reason, [], self::loc($quote))], self::loc($quote)).($note ? "\n".$note : ''));
        QuoteMails::declined($quote);
        self::tellOwner($quote, 'declined');
    }

    private static function tellOwner(Quote $quote, string $what): void
    {
        $owner = $quote->owner ?? $quote->deal?->owner;
        if ($owner) {
            StaffMail::send($owner, 'quote_answered', (new QuoteAnswered($quote, $what))->locale($owner->locale ?: 'en'));
        }
    }
}
