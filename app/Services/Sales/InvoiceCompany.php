<?php

namespace App\Services\Sales;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Finance\PreInvoice;
use App\Models\ProductSales\SalesQuote;
use App\Models\Rental\Order;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Services\Factory\Bookings;
use App\Services\Finance\PreInvoices;
use Illuminate\Support\Facades\DB;

/**
 * The customer accepted, but wants the invoice on another company: change who is invoiced without a new quote.
 * The yes and the prices stay; the quote, its order or venue booking, the deal and a pre-invoice still being checked
 * follow. VAT follows the new company (e.g. reverse charge). Not possible once the invoice exists (pre-invoice sent
 * or paid): then a credit note and a new invoice.
 */
class InvoiceCompany
{
    /** Why it cannot change any more, or null. */
    public static function blocked(Quote|SalesQuote $q): ?string
    {
        $locked = self::preInvoices($q)->first(fn (PreInvoice $pf) => in_array($pf->status, ['sent', 'paid'], true));

        return $locked ? __('quotes.bill_to.locked', ['number' => $locked->number]) : null;
    }

    /** The contact who gets the invoice: the billing contact, else the main contact, else the first one. */
    public static function contactFor(Company $company): ?Contact
    {
        $all = $company->contacts()->get();

        return $all->first(fn ($c) => $c->pivot->is_billing) ?? $all->first(fn ($c) => $c->pivot->is_main) ?? $all->first();
    }

    public static function change(Quote|SalesQuote $q, Company $company, User $by): void
    {
        $was = $q->company;
        $contact = self::contactFor($company);
        $set = ['company_id' => $company->id, 'contact_id' => $contact?->id];

        DB::transaction(function () use ($q, $set, $company, $contact, $by, $was) {
            self::apply($q, $company, $contact);

            if ($q instanceof Quote) {
                if ($booking = Bookings::forQuote($q)) {
                    $booking->update($set);
                } elseif ($order = Order::where('quote_id', $q->id)->first()) {
                    $order->forceFill($set + ['vat_code' => $q->vat_code, 'vat_rate_bp' => $q->vat_rate_bp]);
                    $order->refreshTotals();
                    $order->save();
                }
            }
            $q->deal?->update($set);
            if (! $company->is_customer) {
                $company->forceFill(['is_customer' => true])->save();
            }

            // A pre-invoice still being checked is made again for the new company.
            foreach (self::preInvoices($q)->whereIn('status', ['check', 'back']) as $pf) {
                if ($src = PreInvoices::source($pf)) {
                    PreInvoices::make($pf->source_type, $src->fresh(), $by);
                }
            }

            $data = ['from' => $was?->name, 'to' => $company->name, 'contact' => $contact?->name, 'vat' => $q->vatPercent()];
            AuditLog::record('quote.bill_to', $q, $data, $by);
            $q->deal?->log('system', __('quotes.bill_to.log', ['code' => $q instanceof Quote ? $q->code() : $q->number, 'from' => $was?->name ?? '—', 'to' => $company->name]), $by);
        });
    }

    /** Put a quote on a company: company, contact and the VAT that goes with that company. */
    public static function apply(Quote|SalesQuote $q, Company $company, ?Contact $contact): void
    {
        $q->forceFill(['company_id' => $company->id, 'contact_id' => $contact?->id]);
        $rate = $q->setRelation('company', $company)->setRelation('contact', $contact)->vatRateNow();
        $q->forceFill(['vat_code' => $rate?->code, 'vat_rate_bp' => $rate?->rate_bp ?? 0]);
        $q->vat_cents = (int) round($q->net_cents * $q->vat_rate_bp / 10000);
        $q->gross_cents = $q->net_cents + $q->vat_cents;
        $q->save();
    }

    /**
     * The deal moved to another company (or contact): its draft quotes follow straight away. Quotes already sent keep
     * what the customer saw; a new version takes the deal's company, an accepted one is changed with change().
     */
    public static function followDeal(\App\Models\Sales\Deal $deal, User $by): int
    {
        if (! $deal->company_id) {
            return 0;
        }
        $company = $deal->company()->first();
        // The deal's contact if they belong to the new company, else that company's billing or main contact.
        $contact = $deal->contact_id && $company->contacts()->whereKey($deal->contact_id)->exists() ? Contact::find($deal->contact_id) : self::contactFor($company);
        $n = 0;
        foreach ([$deal->quotes()->where('status', 'draft')->get(), $deal->salesQuotes()->where('status', 'draft')->get()] as $list) {
            foreach ($list as $q) {
                if ($q->company_id !== $company->id || $q->contact_id !== $contact?->id) {
                    $was = $q->company?->name;
                    self::apply($q, $company, $contact);
                    AuditLog::record('quote.bill_to', $q, ['from' => $was, 'to' => $company->name, 'contact' => $contact?->name, 'via' => 'deal'], $by);
                    $n++;
                }
            }
        }

        return $n;
    }

    /** Pre-invoices made from this quote (rental: the order and its charges; Factory: the booking; sales: the quote). */
    private static function preInvoices(Quote|SalesQuote $q)
    {
        if ($q instanceof SalesQuote) {
            return PreInvoice::where('source_type', 'sales')->where('source_id', $q->id)->where('status', '!=', 'cancelled')->get();
        }
        if ($booking = Bookings::forQuote($q)) {
            return PreInvoice::where('source_type', 'factory')->where('source_id', $booking->id)->where('status', '!=', 'cancelled')->get();
        }
        $order = Order::where('quote_id', $q->id)->first();

        return $order ? PreInvoice::whereIn('source_type', ['rental', 'charges'])->where('source_id', $order->id)->where('status', '!=', 'cancelled')->get() : collect();
    }
}
