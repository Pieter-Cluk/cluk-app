<?php

namespace App\Http\Controllers\ProductSales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuote;
use App\Models\ProductSales\SalesQuoteLine;
use App\Services\ProductSales\CustomerPrices;
use App\Services\ProductSales\SalesFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Product sales: quotes with a margin check, the order confirmation with delivery dates, and the documents. */
class SalesQuoteController extends Controller
{
    public const TABS = ['open', 'approval', 'purchasing', 'confirmed', 'closed'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'open';
        $all = SalesQuote::with(['company', 'owner', 'lines'])->latest('id')->limit(500)->get();
        $groups = [
            'open' => $all->whereIn('status', ['draft', 'sent']),
            'approval' => $all->filter(fn ($q) => $q->status === 'draft' && $q->needsApproval()),
            'purchasing' => $all->filter(fn ($q) => $q->status === 'accepted'),
            'confirmed' => $all->where('status', 'confirmed'),
            'closed' => $all->whereIn('status', ['delivered', 'declined', 'cancelled']),
        ];

        return view('psales.index', ['tab' => $tab, 'quotes' => $groups[$tab]->values(), 'counts' => array_map(fn ($g) => $g->count(), $groups), 'canWork' => $request->user()->canUseModule('sales', 2)]);
    }

    /** Sales quotes start from a deal of the kind "sales" in Quotes & sales. */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('deals.create', array_filter(['module' => 'sales', 'company' => $request->query('company')]));
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id'], 'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:200'], 'language' => ['nullable', Rule::in(array_keys(config('cluk.locales')))]]);
        $company = Company::findOrFail($d['company_id']);
        $q = SalesQuote::create($d + ['number' => SalesQuote::nextNumber(), 'status' => 'draft', 'owner_id' => $request->user()->id,
            'language' => $d['language'] ?? ($company->language ?: 'nl'), 'contact_id' => $d['contact_id'] ?? $company->contacts()->orderByDesc('company_contact.is_main')->value('contacts.id'),
            'valid_until' => today()->addDays(CompanyDetail::current()->quote_valid_days ?: 30)]);
        $q->recalculate();
        AuditLog::record('psales.created', $q, ['number' => $q->number], $request->user());

        return redirect()->route('psales.show', $q);
    }

    public function show(Request $request, SalesQuote $quote): View
    {
        $quote->load(['lines.item.suppliers', 'company.contacts', 'contact', 'owner', 'approvedBy', 'requests.orders']);
        $u = $request->user();

        return view('psales.show', [
            'q' => $quote, 'seeCost' => $u->hasRight('finance.see_margins') || $u->hasRight('sales.approve_low_margin'),
            'canWork' => $u->canUseModule('sales', 2), 'canApprove' => $u->hasRight('sales.approve_low_margin'),
            'items' => SalesItem::where('active', true)->orderBy('brand')->orderBy('type')->get(['id', 'number', 'brand', 'type', 'manufacturer_code', 'list_price_cents', 'stock_qty']),
            'activity' => AuditLog::with('actor')->where('subject_type', $quote->getMorphClass())->where('subject_id', $quote->id)->latest('id')->limit(20)->get(),
        ]);
    }

    private function editable(SalesQuote $q): void
    {
        abort_unless($q->isEditable(), 409);
    }

    public function update(Request $request, SalesQuote $quote): RedirectResponse
    {
        $this->editable($quote);
        $d = $request->validate(['title' => ['required', 'string', 'max:200'], 'contact_id' => ['nullable', 'integer', 'exists:contacts,id'], 'language' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'intro' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'], 'valid_until' => ['nullable', 'date'], 'delivery_mode' => ['required', Rule::in(['together', 'asap'])],
            'lines' => ['array'], 'lines.*.qty' => ['required', 'integer', 'min:1', 'max:100000'], 'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.name' => ['nullable', 'string', 'max:200'], 'lines.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000']]);
        $quote->update(collect($d)->except('lines')->all());
        foreach ($d['lines'] ?? [] as $id => $l) {
            $line = $quote->lines()->find($id);
            if (! $line) {
                continue;
            }
            $data = ['qty' => (int) $l['qty'], 'discount_bp' => (int) round(((float) ($l['discount'] ?? 0)) * 100)];
            if ($line->kind !== 'item') {
                $data += ['name' => $l['name'] ?? $line->name, 'list_cents' => (int) round(((float) ($l['price'] ?? 0)) * 100)];
            }
            $line->update($data);
        }
        $quote->recalculate();

        return back()->with('status', __('psales.saved'));
    }

    public function addLine(Request $request, SalesQuote $quote): RedirectResponse
    {
        $this->editable($quote);
        $d = $request->validate(['item_id' => ['nullable', 'integer', 'exists:sales_items,id'], 'kind' => ['required', Rule::in(['item', 'charge', 'text'])], 'name' => ['required_unless:kind,item', 'nullable', 'string', 'max:200'], 'qty' => ['nullable', 'integer', 'min:1']]);
        $pos = (int) $quote->lines()->max('position') + 1;
        if ($d['kind'] === 'item') {
            $item = SalesItem::with('suppliers')->findOrFail($d['item_id']);
            $quote->lines()->create(['sales_item_id' => $item->id, 'kind' => 'item', 'name' => $item->name(), 'description' => $item->description, 'qty' => $d['qty'] ?? 1,
                'cost_cents' => $item->costCents(), 'position' => $pos] + CustomerPrices::lineValues($quote->company, $item));
        } else {
            $quote->lines()->create(['kind' => $d['kind'], 'name' => $d['name'], 'qty' => 1, 'position' => $pos]);
        }
        $quote->recalculate();

        return back();
    }

    public function removeLine(SalesQuote $quote, SalesQuoteLine $line): RedirectResponse
    {
        $this->editable($quote);
        abort_unless($line->sales_quote_id === $quote->id, 404);
        $line->delete();
        $quote->recalculate();

        return back();
    }

    public function approve(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless($request->user()->hasRight('sales.approve_low_margin') && $quote->isEditable(), 403);
        SalesFlow::approve($quote, $request->user(), $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null);

        return back()->with('status', __('psales.approved'));
    }

    public function send(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless(in_array($quote->status, ['draft', 'sent'], true), 409);
        $d = $request->validate(['to' => ['required', 'email', 'max:190'], 'message' => ['nullable', 'string', 'max:3000']]);
        SalesFlow::send($quote, $d['to'], $d['message'] ?? null, $request->user());

        return back()->with('status', __('psales.sent', ['to' => $d['to']]));
    }

    /** Accepted by phone or mail: staff note it. */
    public function accept(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless($quote->status === 'sent', 409);
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'po_number' => ['nullable', 'string', 'max:80']]);
        SalesFlow::accept($quote, $d['name'], $d['po_number'] ?? null, $request->user());

        return back()->with('status', __('psales.accepted'));
    }

    /** Supplier prices before the customer says yes (draft or sent quote). */
    public function requestPrices(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless($request->user()->canUseModule('sales', 2) || $request->user()->canUseModule('purchasing', 2), 403);
        abort_unless(in_array($quote->status, ['draft', 'sent'], true), 409);
        $pq = SalesFlow::requestPrices($quote, $request->user());

        return $pq ? redirect()->route('purchasing.pq', $pq)->with('status', __('psales.early_sent', ['number' => $pq->number]))
            : back()->with('status', __('psales.early_in_stock'));
    }

    /** The customer said no outside the link (phone, mail). */
    public function markDeclined(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless(in_array($quote->status, ['draft', 'sent'], true), 409);
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        SalesFlow::decline($quote, trim(($d['reason'] ?? '').' ('.__('psales.declined_by', ['name' => $request->user()->name]).')'));

        return back()->with('status', __('psales.declined_saved'));
    }

    public function confirm(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless($quote->status === 'accepted', 409);
        SalesFlow::confirm($quote, $request->user());

        return back()->with('status', __('psales.confirmed', ['number' => $quote->docCode('S-OC')]));
    }

    public function deliver(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless($quote->status === 'confirmed', 409);
        SalesFlow::deliver($quote, $request->user());

        return back()->with('status', __('psales.delivered'));
    }

    public function cancel(Request $request, SalesQuote $quote): RedirectResponse
    {
        abort_unless(in_array($quote->status, ['draft', 'sent', 'accepted'], true), 409);
        $quote->update(['status' => 'cancelled']);
        AuditLog::record('psales.cancelled', $quote, [], $request->user());

        return back()->with('status', __('psales.cancelled'));
    }

    /** The documents: quote / order confirmation, pick list, delivery note, proforma. */
    public function print(Request $request, SalesQuote $quote, string $type): View
    {
        $quote->load(['lines.item', 'company', 'contact', 'owner']);
        $customer = in_array($type, ['quo', 'oc', 'dn', 'pf'], true);
        abort_if($type === 'oc' && ! in_array($quote->status, ['confirmed', 'delivered'], true), 404);
        if ($customer) {
            app()->setLocale(array_key_exists($quote->language, config('cluk.locales')) ? $quote->language : 'en');
        }

        return view('psales.doc', ['q' => $quote, 'type' => $type, 'code' => $quote->docCode('S-'.strtoupper($type)), 'me' => CompanyDetail::current(),
            'prices' => in_array($type, ['quo', 'oc', 'pf'], true)]);
    }

    /** Pieces for a manual price request from the quote (e.g. after a change). */
    public static function toBuy(SalesQuote $q): array
    {
        return $q->lines->filter(fn ($l) => $l->kind === 'item' && $l->item && $l->toBuy() > 0)->map(fn ($l) => ['item' => $l->item, 'qty' => $l->toBuy(), 'quote_line_id' => $l->id])->values()->all();
    }
}
