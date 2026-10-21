<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\ProductSales\SalesItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Purchasing\RequestLine;
use App\Services\Purchasing\Purchasing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Purchasing: price requests (PQ) to compare suppliers, and the purchase orders (PO) that follow. */
class PurchasingController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'po' ? 'po' : 'pq';
        $filt = (string) $request->query('f');
        $pqs = PurchaseRequest::with(['suppliers', 'lines', 'salesQuote.company'])->latest('id')->limit(300)->get();
        $pos = PurchaseOrder::with(['supplier', 'lines', 'request.salesQuote.company'])->latest('id')->limit(300)->get();
        $pqState = fn (PurchaseRequest $r) => match (true) {
            in_array($r->status, ['ordered', 'cancelled'], true) => 'closed',
            $r->status === 'sent' && $r->repliedCount() >= max(1, $r->suppliers->count()) => 'reply',
            $r->status === 'sent' && $r->repliedCount() > 0 => 'reply',
            default => 'wait',
        };
        $poState = fn (PurchaseOrder $o) => match (true) {
            $o->invoiceDiffers() => 'invoice',
            in_array($o->status, ['received', 'cancelled'], true) => 'closed',
            default => 'run',
        };
        $rows = $tab === 'pq' ? $pqs->filter(fn ($r) => ! $filt || $pqState($r) === $filt) : $pos->filter(fn ($o) => ! $filt || $poState($o) === $filt);

        return view('purchasing.index', [
            'tab' => $tab, 'filt' => $filt, 'rows' => $rows->values(), 'pqState' => $pqState, 'poState' => $poState,
            'tiles' => [
                'reply' => $pqs->filter(fn ($r) => $pqState($r) === 'reply')->count(),
                'wait' => $pqs->filter(fn ($r) => $pqState($r) === 'wait')->count(),
                'late' => $pqs->filter(fn ($r) => $r->isLate())->count(),
                'run' => $pos->filter(fn ($o) => $poState($o) === 'run')->count(),
                'po_late' => $pos->filter(fn ($o) => $o->isLate())->count(),
                'invoice' => $pos->filter(fn ($o) => $poState($o) === 'invoice')->count(),
            ],
            'counts' => ['pq' => $pqs->count(), 'po' => $pos->count()],
            'canWork' => $request->user()->canUseModule('purchasing', 2),
        ]);
    }

    /** A price request for stock or the rental fleet, without a sale. */
    public function create(Request $request): View
    {
        return view('purchasing.create', ['items' => SalesItem::with('suppliers')->where('active', true)->orderBy('brand')->orderBy('type')->get(),
            'low' => SalesItem::where('active', true)->where('min_stock', '>', 0)->whereColumn('stock_qty', '<', 'min_stock')->pluck('id')->all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate(['qty' => ['array'], 'qty.*' => ['nullable', 'integer', 'min:0', 'max:100000'], 'purpose' => ['array'], 'purpose.*' => [Rule::in(RequestLine::PURPOSES)],
            'needed_by' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:500'], 'send' => ['boolean']]);
        $lines = [];
        foreach (array_filter($d['qty'] ?? []) as $id => $qty) {
            $item = SalesItem::with('suppliers')->find($id);
            if ($item && $item->suppliers->isNotEmpty()) {
                $lines[] = ['item' => $item, 'qty' => (int) $qty, 'purpose' => $d['purpose'][$id] ?? 'sales'];
            }
        }
        if (! $lines) {
            return back()->withInput()->withErrors(['qty' => __('purchasing.errors.no_lines')]);
        }
        $pq = Purchasing::create($lines, $request->user(), null, isset($d['needed_by']) ? Carbon::parse($d['needed_by']) : null, $d['note'] ?? null);
        if ($request->boolean('send')) {
            Purchasing::send($pq, $request->user());
        }

        return redirect()->route('purchasing.pq', $pq)->with('status', __('purchasing.created', ['number' => $pq->number]));
    }

    public function pq(Request $request, PurchaseRequest $pq): View
    {
        $pq->load(['lines.item.suppliers', 'lines.replies', 'lines.quoteLine', 'suppliers', 'salesQuote.company', 'orders.supplier', 'createdBy']);

        return view('purchasing.pq', ['pq' => $pq, 'canWork' => $request->user()->canUseModule('purchasing', 2), 'seeMargin' => $request->user()->hasRight('finance.see_margins')]);
    }

    public function send(Request $request, PurchaseRequest $pq): RedirectResponse
    {
        abort_unless($pq->status === 'draft', 409);
        $missing = Purchasing::send($pq, $request->user());

        return back()->with('status', $missing ? __('purchasing.sent_missing', ['names' => implode(', ', $missing)]) : __('purchasing.sent'));
    }

    public function replies(Request $request, PurchaseRequest $pq): RedirectResponse
    {
        abort_unless(in_array($pq->status, ['draft', 'sent'], true), 409);
        $d = $request->validate(['r' => ['array'], 'r.*.*.disc' => ['nullable', 'numeric', 'min:0', 'max:100'], 'r.*.*.net' => ['nullable', 'numeric', 'min:0'], 'r.*.*.date' => ['nullable', 'date'],
            'choose' => ['array'], 'choose.*' => ['nullable', 'integer']]);
        Purchasing::saveReplies($pq, $d['r'] ?? [], $d['choose'] ?? [], $request->user());
        if ($request->boolean('order')) {
            $orders = Purchasing::order($pq->fresh(), $request->user());

            return back()->with('status', __('purchasing.ordered', ['numbers' => collect($orders)->pluck('number')->implode(', ')]));
        }

        return back()->with('status', __('purchasing.saved'));
    }

    public function cancel(Request $request, PurchaseRequest $pq): RedirectResponse
    {
        abort_unless(in_array($pq->status, ['draft', 'sent'], true), 409);
        $pq->update(['status' => 'cancelled']);
        AuditLog::record('purchase.cancelled', $pq, [], $request->user());

        return back();
    }

    public function po(Request $request, PurchaseOrder $po): View
    {
        $po->load(['lines.item', 'lines.quoteLine.quote', 'supplier', 'request.salesQuote.company', 'createdBy']);

        return view('purchasing.po', ['po' => $po, 'canWork' => $request->user()->canUseModule('purchasing', 2)]);
    }

    public function confirm(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_unless(in_array($po->status, ['sent', 'confirmed'], true), 409);
        $d = $request->validate(['their_ref' => ['nullable', 'string', 'max:80'], 'dates' => ['array'], 'dates.*' => ['nullable', 'date']]);
        Purchasing::confirm($po->load('lines.quoteLine'), $d['their_ref'] ?? null, $d['dates'] ?? [], $request->user());

        return back()->with('status', __('purchasing.confirmed'));
    }

    public function receive(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_unless(in_array($po->status, ['sent', 'confirmed'], true), 409);
        $d = $request->validate(['qty' => ['array'], 'qty.*' => ['nullable', 'integer', 'min:0']]);
        Purchasing::receive($po->load('lines.item'), $d['qty'] ?? [], $request->user());

        return back()->with('status', __('purchasing.received'));
    }

    public function invoice(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $d = $request->validate(['amount' => ['required', 'numeric', 'min:0', 'max:10000000'], 'accept' => ['boolean']]);
        Purchasing::invoice($po, (int) round($d['amount'] * 100), $request->boolean('accept'), $request->user());

        return back()->with('status', __('purchasing.invoice_saved'));
    }

    public function printPq(PurchaseRequest $pq): View
    {
        return view('purchasing.doc', ['doc' => $pq->load(['lines.item', 'suppliers']), 'kind' => 'pq', 'me' => CompanyDetail::current()]);
    }

    public function printPo(PurchaseOrder $po): View
    {
        $po->load(['lines.item', 'supplier']);
        $loc = array_key_exists((string) $po->supplier->language, config('cluk.locales')) ? $po->supplier->language : 'en';
        app()->setLocale($loc);

        return view('purchasing.doc', ['doc' => $po, 'kind' => 'po', 'me' => CompanyDetail::current()]);
    }
}
