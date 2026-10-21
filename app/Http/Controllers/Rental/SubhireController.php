<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Rental\SubhireLine;
use App\Models\Rental\SubhireRequest;
use App\Services\Rental\OrderMails;
use App\Services\Rental\Orders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Sub-hire: price requests to partners (SHQ) and the confirmations that follow (SHC). */
class SubhireController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'shc' ? 'shc' : 'shq';
        $filter = in_array($request->query('f'), ['draft', 'sent', 'closed', 'running', 'done'], true) ? $request->query('f') : null;
        $rows = SubhireRequest::with(['supplier', 'order.company', 'lines'])
            ->when($tab === 'shq', fn ($q) => $q->whereIn('status', ['draft', 'sent', 'not_available', 'cancelled']), fn ($q) => $q->where('status', 'accepted'))
            ->when($filter === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($filter === 'sent', fn ($q) => $q->where('status', 'sent'))
            ->when($filter === 'closed', fn ($q) => $q->whereIn('status', ['not_available', 'cancelled']))
            ->when($filter === 'running', fn ($q) => $q->where('return_at', '>=', now()))
            ->when($filter === 'done', fn ($q) => $q->where('return_at', '<', now()))
            ->latest('updated_at')->paginate(40)->withQueryString();
        $counts = [
            'draft' => SubhireRequest::where('status', 'draft')->count(),
            'sent' => SubhireRequest::where('status', 'sent')->count(),
            'running' => SubhireRequest::where('status', 'accepted')->where('return_at', '>=', now())->count(),
        ];

        return view('rental.subhire.index', compact('rows', 'tab', 'filter', 'counts'));
    }

    public function show(Request $request, SubhireRequest $subhire): View
    {
        $subhire->load(['supplier', 'order.company', 'order.assignee', 'lines.orderLine', 'lines.item', 'acceptedBy']);

        return view('rental.subhire.show', [
            'r' => $subhire,
            'canWork' => $request->user()->canUseModule('purchasing', 2),
            'seeMargin' => $request->user()->hasRight('finance.see_margins'),
            'editable' => in_array($subhire->status, ['draft', 'sent'], true),
        ]);
    }

    private function guard(Request $request, SubhireRequest $r, array $statuses): void
    {
        abort_unless($request->user()->canUseModule('purchasing', 2), 403);
        abort_unless(in_array($r->status, $statuses, true), 409);
    }

    /** Type the partner's answer: their reference, dates, prices per line, extra costs. */
    public function update(Request $request, SubhireRequest $subhire): RedirectResponse
    {
        $this->guard($request, $subhire, ['draft', 'sent']);
        $d = $request->validate([
            'their_ref' => ['nullable', 'string', 'max:80'],
            'delivery_at' => ['required', 'date'],
            'return_at' => ['required', 'date', 'after:delivery_at'],
            'note' => ['nullable', 'string', 'max:2000'],
            'extra' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'extra_text' => ['nullable', 'string', 'max:200'],
            'lines' => ['array'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'lines.*.factor' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        DB::transaction(function () use ($subhire, $d) {
            $subhire->update([
                'their_ref' => $d['their_ref'] ?? null, 'delivery_at' => $d['delivery_at'], 'return_at' => $d['return_at'], 'note' => $d['note'] ?? null,
                'extra_cents' => (int) round(((float) ($d['extra'] ?? 0)) * 100), 'extra_text' => $d['extra_text'] ?? null,
                'answered_at' => $subhire->status === 'sent' && collect($d['lines'] ?? [])->contains(fn ($l) => ($l['price'] ?? '') !== '') ? ($subhire->answered_at ?? now()) : $subhire->answered_at,
            ]);
            foreach ($d['lines'] ?? [] as $id => $l) {
                $line = $subhire->lines()->whereKey($id)->first();
                if (! $line) {
                    continue;
                }
                $line->update([
                    'qty' => min((int) $l['qty'], $line->orderLine?->pieces() ?? (int) $l['qty']),
                    'price_cents' => ($l['price'] ?? '') === '' ? null : (int) round(((float) $l['price']) * 100),
                    'factor' => ($l['factor'] ?? '') === '' ? 1 : $l['factor'],
                    'discount_bp' => (int) round(((float) ($l['discount'] ?? 0)) * 100),
                ]);
            }
            $subhire->load('lines');
            $subhire->recalculate();
        });

        return back()->with('status', __('orders.flash.subhire_saved'));
    }

    public function removeLine(Request $request, SubhireRequest $subhire, SubhireLine $line): RedirectResponse
    {
        $this->guard($request, $subhire, ['draft']);
        abort_unless($line->request_id === $subhire->id, 404);
        $line->delete();
        if ($subhire->lines()->count() === 0) {
            $order = $subhire->order;
            $subhire->delete();

            return redirect()->route('orders.show', $order)->with('status', __('orders.flash.subhire_removed'));
        }
        $subhire->load('lines');
        $subhire->recalculate();

        return back()->with('status', __('orders.flash.subhire_removed'));
    }

    public function accept(Request $request, SubhireRequest $subhire): RedirectResponse
    {
        $this->guard($request, $subhire, ['sent']);
        Orders::acceptSubhire($subhire, $request->user());

        return back()->with('status', __('orders.flash.subhire_accepted', ['number' => $subhire->confirm_number]));
    }

    public function notAvailable(Request $request, SubhireRequest $subhire): RedirectResponse
    {
        $this->guard($request, $subhire, ['sent']);
        Orders::notAvailable($subhire, $request->user());

        return redirect()->route('orders.show', $subhire->order_id)->with('status', __('orders.flash.subhire_na', ['supplier' => $subhire->supplier->name]));
    }

    public function resend(Request $request, SubhireRequest $subhire): RedirectResponse
    {
        $this->guard($request, $subhire, ['sent']);
        OrderMails::priceRequest($subhire, $request->user());

        return back()->with('status', __('orders.flash.reminded', ['to' => $subhire->sent_to]));
    }

    public function cancel(Request $request, SubhireRequest $subhire): RedirectResponse
    {
        $this->guard($request, $subhire, ['draft', 'sent']);
        $subhire->update(['status' => 'cancelled']);

        return redirect()->route('orders.show', $subhire->order_id)->with('status', __('orders.flash.subhire_cancelled'));
    }
}
