<?php

namespace App\Services\Web;

use App\Models\Finance\PreInvoice;
use App\Models\ProductSales\SalesQuote;
use App\Models\Rental\Order;
use App\Models\Sales\Quote;
use App\Models\Web\CustomerAccount;
use App\Services\Afrit6\Pricing;

/**
 * What a logged-in customer sees of their company in My CLUK: quotes (to open
 * and accept online), orders and invoices with their payment status. Every
 * login of the company sees the same; without a linked company only their own
 * website orders show.
 */
class AccountDocs
{
    public static function for(CustomerAccount $a, int $limit = 30): array
    {
        $cid = $a->company_id;
        if (! $cid) {
            return ['quotes' => [], 'orders' => [], 'invoices' => []];
        }
        $rental = Quote::where('company_id', $cid)->whereIn('status', ['sent', 'change', 'accepted', 'declined'])->whereNotNull('token')
            ->latest('sent_at')->limit($limit)->get()
            ->map(fn (Quote $q) => ['ref' => $q->code(), 'title' => $q->title, 'date' => $q->sent_at?->toDateString(), 'net_cents' => (int) $q->net_cents,
                'status' => $q->displayStatus(), 'open' => $q->isOpenForCustomer(), 'url' => route('quote.public', $q->token), 'kind' => 'rental']);
        $sales = SalesQuote::where('company_id', $cid)->whereNotIn('status', ['draft', 'cancelled'])->whereNotNull('token')
            ->latest('id')->limit($limit)->get()
            ->map(fn (SalesQuote $q) => ['ref' => $q->code(), 'title' => $q->title, 'date' => ($q->sent_at ?? $q->created_at)?->toDateString(), 'net_cents' => (int) $q->net_cents,
                'status' => $q->status, 'open' => $q->status === 'sent', 'url' => route('psales.public', $q->token), 'kind' => 'sales']);
        $orders = Order::with('quote')->where('company_id', $cid)->whereIn('status', ['allocation', 'approved'])->latest('use_from')->limit($limit)->get()
            ->map(fn (Order $o) => ['ref' => $o->number, 'title' => $o->title, 'from' => $o->use_from->toDateString(), 'until' => $o->use_until->toDateString(), 'po' => $o->po_number,
                'net_cents' => (int) $o->net_cents, 'status' => $o->returned_at ? 'returned' : ($o->loaded_at ? 'out' : ($o->status === 'approved' ? 'confirmed' : 'preparing')),
                'url' => $o->quote?->token && $o->status === 'approved' ? route('quote.public.order', $o->quote->token) : null]);
        $invoices = PreInvoice::where('company_id', $cid)->whereIn('status', ['sent', 'paid'])->whereNotNull('invoice_number')->latest('invoice_date')->limit($limit)->get()
            ->map(fn (PreInvoice $p) => ['id' => $p->id, 'ref' => $p->invoice_number, 'date' => $p->invoice_date?->toDateString(), 'due' => $p->due_date?->toDateString(),
                'gross' => Pricing::eur((int) $p->gross_cents), 'gross_cents' => (int) $p->gross_cents,
                'status' => $p->status === 'paid' ? 'paid' : ($p->isOverdue() ? 'late' : 'open'), 'xml' => (bool) $p->ubl_path]);

        return ['quotes' => $rental->concat($sales)->sortByDesc('date')->values()->all(), 'orders' => $orders->all(), 'invoices' => $invoices->all()];
    }

    /** An invoice of the customer's own company, or nothing. */
    public static function invoice(CustomerAccount $a, int $id): ?PreInvoice
    {
        return $a->company_id ? PreInvoice::where('company_id', $a->company_id)->whereIn('status', ['sent', 'paid'])->whereNotNull('invoice_number')->find($id) : null;
    }
}
