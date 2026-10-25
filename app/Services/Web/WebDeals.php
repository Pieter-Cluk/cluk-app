<?php

namespace App\Services\Web;

use App\Models\Company;
use App\Models\Equipment\Item;
use App\Models\Sales\Deal;
use App\Models\Sales\IncomingMessage;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\InLocale;
use App\Support\StaffMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A basket sent by a logged-in customer of a known company goes straight into
 * Quotes & sales: a deal for the customer's account owner, with a draft quote at
 * the customer's own prices, ready to check and send.
 */
class WebDeals
{
    /** Who looks after this customer: the account owner, else the colleague who approved the login. */
    public static function owner(CustomerAccount $a): ?User
    {
        $a->loadMissing(['company.accountOwner', 'approvedBy']);
        foreach ([$a->company?->accountOwner, $a->approvedBy] as $u) {
            if ($u?->isActive()) {
                return $u;
            }
        }

        return null;
    }

    /** A new deal for a website basket. */
    public static function deal(CustomerAccount $a, string $module, string $title, ?IncomingMessage $msg = null, ?string $from = null, ?string $until = null): Deal
    {
        $owner = self::owner($a);
        $deal = Deal::create([
            'number' => Deal::nextNumber(), 'title' => mb_substr($title, 0, 200), 'module' => $module, 'stage' => 'qualified', 'source' => 'website',
            'company_id' => $a->company_id, 'contact_id' => $a->contact_id, 'owner_id' => $owner?->id, 'incoming_id' => $msg?->id,
            'event_start' => $from, 'event_end' => $until,
        ]);
        $deal->log('system', __('web.deal.log', ['name' => $a->name(), 'email' => $a->email]));

        return $deal;
    }

    /** Dry-hire basket → deal + draft rental quote (lines at the customer's dry-hire discount). */
    public static function fromDryHire(CustomerAccount $a, IncomingMessage $msg, array $lines, string $from, string $until, ?string $note): ?Deal
    {
        $company = $a->company;
        if (! $company instanceof Company) {
            return null; // not linked to a company yet: it stays in Incoming for a colleague
        }
        $deal = DB::transaction(function () use ($a, $msg, $lines, $from, $until, $note, $company) {
            $loc = $a->locale ?: ($company->language ?: 'nl');
            $title = InLocale::run($loc, fn () => __('web.deal.title_dryhire', ['date' => Carbon::parse($from)->format('d/m/Y')]));
            $deal = self::deal($a, 'dryhire', $title, $msg, $from, $until);
            if ($note) {
                $deal->log('note', $note);
            }
            $start = Carbon::parse($from);
            $end = Carbon::parse($until);
            $quote = Quote::create([
                'number' => Quote::nextNumber(), 'version' => 1, 'status' => 'draft', 'deal_id' => $deal->id,
                'company_id' => $company->id, 'contact_id' => $a->contact_id, 'owner_id' => $deal->owner_id,
                'title' => $title, 'language' => $loc,
                'out_from' => $start->copy()->subDay()->setTime(14, 0), 'out_until' => $end->copy()->addDay()->setTime(10, 0),
                'use_from' => $start, 'use_until' => $end, 'notes' => $note ? mb_substr($note, 0, 2000) : null,
            ]);
            $items = Item::whereKey(collect($lines)->pluck('id'))->get()->keyBy('id');
            $pos = 0;
            foreach ($lines as $l) {
                if (! $i = $items[$l['id']] ?? null) {
                    continue;
                }
                $quote->lines()->create([
                    'kind' => 'item', 'item_id' => $i->id, 'number' => $i->number, 'name' => $i->name, 'qty' => $l['qty'],
                    'unit_price_cents' => (int) $i->day_price_cents, 'discount_bp' => (int) $company->rental_discount_bp, 'position' => ++$pos,
                ]);
            }
            $quote->load('company');
            $quote->recalculate();
            $deal->forceFill(['value_cents' => $quote->net_cents])->save();
            $deal->log('system', __('quotes.log.created', ['number' => $quote->number]));
            $msg->update(['status' => 'converted', 'deal_id' => $deal->id, 'company_id' => $company->id, 'contact_id' => $a->contact_id ?? $msg->contact_id, 'handled_at' => now()]);

            return $deal;
        });
        self::tell($deal, $a);

        return $deal;
    }

    /** The account owner gets an email with a link to the deal. */
    public static function tell(Deal $deal, CustomerAccount $a): void
    {
        $owner = $deal->owner;
        if (! $owner?->isActive()) {
            return;
        }
        $loc = $owner->locale ?: 'en';
        $deal->loadMissing(['quotes', 'salesQuotes']);
        $q = $deal->quotes->first() ?? $deal->salesQuotes->first();
        StaffMail::send($owner, 'web_request', InLocale::run($loc, fn () => new Confirmation(
            subject: __('web.deal.staff_subject', ['name' => $deal->company?->name ?? $a->name()]),
            greeting: __('mails.hello', ['name' => $owner->first_name]),
            intro: [__('web.deal.staff_intro', ['name' => $a->name(), 'email' => $a->email, 'deal' => $deal->number])],
            details: array_filter([__('mails.label.quote') => $q?->number, __('web.deal.value') => $q ? Pricing::eur((int) $q->net_cents) : null]),
            button: [__('web.deal.staff_button'), route('deals.show', $deal)],
        ))->locale($loc));
    }
}
