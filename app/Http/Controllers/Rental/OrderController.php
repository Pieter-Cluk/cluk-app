<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Equipment\Asset;
use App\Models\Rental\Order;
use App\Models\Rental\OrderAsset;
use App\Models\Rental\OrderLine;
use App\Models\User;
use App\Services\Afrit6\Pricing;
use App\Services\Rental\OrderChanges;
use App\Services\Rental\Orders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Order allocation: confirmed orders, covered line by line from own stock
 * or sub-hire, then approved. The assignee works on it; whoever the order
 * is with can hand it on following the reassignment rule.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $tab = in_array($request->query('tab'), ['done', 'out'], true) ? $request->query('tab') : 'open';
        $who = in_array($request->query('who'), ['me', 'all'], true) ? $request->query('who') : 'all';
        $state = in_array($request->query('state'), Order::STATES, true) ? $request->query('state') : null;

        $orders = Order::with(['company', 'contact', 'assignee', 'handedBy'])
            ->when($tab === 'open', fn ($q) => $q->where('status', 'allocation'))
            ->when($tab === 'out', fn ($q) => $q->where('status', 'approved')->whereNotNull('loaded_at')->whereNull('returned_at'))
            ->when($tab === 'done', fn ($q) => $q->whereIn('status', ['approved', 'cancelled'])->where(fn ($w) => $w->whereNull('loaded_at')->orWhereNotNull('returned_at')->orWhere('status', 'cancelled')))
            ->when($who === 'me', fn ($q) => $q->where('assignee_id', $me->id))
            ->orderBy(match ($tab) { 'open' => 'out_from', 'out' => 'out_until', default => 'updated_at' }, $tab === 'done' ? 'desc' : 'asc')
            ->limit(200)->get();
        $states = $orders->mapWithKeys(fn (Order $o) => [$o->id => $o->state()]);
        $counts = collect(Order::STATES)->mapWithKeys(fn ($s) => [$s => $tab === 'open' ? $states->filter(fn ($x) => $x === $s)->count() : 0]);
        if ($state) {
            $orders = $orders->filter(fn ($o) => $states[$o->id] === $state)->values();
        }

        return view('rental.orders.index', compact('orders', 'states', 'counts', 'tab', 'who', 'state'));
    }

    public function show(Request $request, Order $order): View
    {
        $order->load(['lines.item.contents', 'lines.assets.asset', 'lines.subhireLines.request.supplier', 'subhires.supplier', 'subhires.lines', 'company', 'contact', 'quote', 'deal', 'handedBy', 'assignee', 'approvedBy']);
        $avail = Orders::availability($order);
        $me = $request->user();

        return view('rental.orders.show', [
            'order' => $order,
            'avail' => $avail,
            'state' => $order->state(),
            'canWork' => $this->canWork($me, $order),
            'canSubhire' => $me->canUseModule('purchasing', 2),
            'canApprove' => $me->hasRight('orders.approve'),
            'canChange' => $this->canChange($me, $order),
            'canTransport' => $this->canTransport($me, $order),
            'lockingPf' => $order->lockingPreInvoice(),
            'suppliers' => Company::active()->where('is_supplier', true)->orderBy('name')->get(['id', 'name', 'supplies']),
            'assignees' => User::where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get()
                ->filter(fn (User $u) => $u->canUseModule('dryhire', 2) && $me->canHandTaskTo($u))->values(),
            'canReassign' => $order->assignee === null || $me->canReassignFrom($order->assignee),
        ]);
    }

    private function canWork(User $me, Order $order): bool
    {
        return $order->isInAllocation() && $me->canUseModule('dryhire', 2)
            && ($order->assignee_id === null || $me->canReassignFrom($order->assignee) || $me->canUseModule('dryhire', 3));
    }

    /** Guard + take an unassigned order on the first action. */
    private function work(Request $request, Order $order): User
    {
        $me = $request->user();
        abort_unless($this->canWork($me, $order), 403);
        if ($order->assignee_id === null) {
            $order->update(['assignee_id' => $me->id]);
        }

        return $me;
    }

    private function line(Order $order, OrderLine $line): OrderLine
    {
        abort_unless($line->order_id === $order->id, 404);

        return $line;
    }

    public function assign(Request $request, Order $order): RedirectResponse
    {
        $me = $request->user();
        abort_unless($order->isInAllocation() && ($order->assignee === null || $me->canReassignFrom($order->assignee)), 403);
        $target = User::findOrFail($request->validate(['user_id' => ['required', 'integer']])['user_id']);
        if (! $me->canHandTaskTo($target) || ! $target->canUseModule('dryhire', 2)) {
            throw ValidationException::withMessages(['user_id' => __('orders.errors.cannot_assign')]);
        }
        $from = $order->assignee?->name;
        $order->update(['assignee_id' => $target->id]);
        AuditLog::record('order.assigned', $order, ['from' => $from, 'to' => $target->name], $me);

        return back()->with('status', __('orders.flash.assigned', ['name' => $target->name]));
    }

    public function autoAllocate(Request $request, Order $order): RedirectResponse
    {
        $this->work($request, $order);
        Orders::autoAllocate($order);

        return back()->with('status', __('orders.flash.allocated'));
    }

    public function allocateLine(Request $request, Order $order, OrderLine $line): RedirectResponse
    {
        $this->work($request, $order);
        $line = $this->line($order, $line);
        abort_unless($line->needsStock(), 404);
        $line->load(['item', 'assets', 'subhireLines.request']);

        if ($line->bySerial() && $request->filled('code')) {
            // Add one piece by its code (typed or scanned).
            $asset = Asset::where('code', strtoupper(trim((string) $request->input('code'))))->where('item_id', $line->item_id)->first();
            $free = Orders::availability($order)->freeAssets($line->item)->pluck('id');
            if (! $asset || ! $free->contains($asset->id) || $order->assets()->where('asset_id', $asset->id)->exists()) {
                throw ValidationException::withMessages(['code' => __('orders.errors.asset_not_free', ['code' => $request->input('code')])]);
            }
            if ($line->ownAllocated() >= $line->ownNeeded()) {
                throw ValidationException::withMessages(['code' => __('orders.errors.line_full')]);
            }
            OrderAsset::create(['order_id' => $order->id, 'order_line_id' => $line->id, 'asset_id' => $asset->id]);
        } elseif (! $line->bySerial() && $request->has('own_qty')) {
            $qty = (int) $request->validate(['own_qty' => ['required', 'integer', 'min:0', 'max:100000']])['own_qty'];
            $free = (int) Orders::availability($order)->free($line->item);
            $others = (int) $order->lines()->where('item_id', $line->item_id)->whereKeyNot($line->id)->sum('own_qty');
            if ($qty > $line->ownNeeded() || $qty > $free - $others) {
                throw ValidationException::withMessages(['own_qty' => __('orders.errors.too_many', ['free' => max(0, min($line->ownNeeded(), $free - $others))])]);
            }
            $line->update(['own_qty' => $qty]);
        } else {
            Orders::autoAllocateLine($order, $line);
        }

        return back()->with('status', __('orders.flash.allocated'));
    }

    public function releaseAsset(Request $request, Order $order, OrderAsset $orderAsset): RedirectResponse
    {
        $this->work($request, $order);
        abort_unless($orderAsset->order_id === $order->id, 404);
        $orderAsset->delete();

        return back()->with('status', __('orders.flash.released'));
    }

    public function clearLine(Request $request, Order $order, OrderLine $line): RedirectResponse
    {
        $this->work($request, $order);
        Orders::clearLine($this->line($order, $line));

        return back()->with('status', __('orders.flash.released'));
    }

    public function addSubhire(Request $request, Order $order, OrderLine $line): RedirectResponse
    {
        $me = $this->work($request, $order);
        abort_unless($me->canUseModule('purchasing', 2), 403);
        $line = $this->line($order, $line);
        abort_unless($line->needsStock(), 404);
        $d = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_supplier', true)],
            'qty' => ['required', 'integer', 'min:1', 'max:'.$line->pieces()],
        ]);
        $request = Orders::addSubhire($order, $line, Company::findOrFail($d['supplier_id']), (int) $d['qty'], $me);

        return back()->with('status', __('orders.flash.subhire_added', ['supplier' => $request->supplier->name]));
    }

    public function sendRequests(Request $request, Order $order): RedirectResponse
    {
        $me = $this->work($request, $order);
        abort_unless($me->canUseModule('purchasing', 2), 403);
        $missing = Orders::sendRequests($order, $me);
        if ($missing) {
            return back()->withErrors(['supplier' => __('orders.errors.no_email', ['names' => implode(', ', $missing)])]);
        }

        return back()->with('status', __('orders.flash.requests_sent'));
    }

    public function approve(Request $request, Order $order): RedirectResponse
    {
        $me = $this->work($request, $order);
        abort_unless($me->hasRight('orders.approve'), 403);
        $sent = Orders::approve($order, $me);

        return back()->with('status', $sent ? __('orders.flash.approved', ['email' => $order->customer_email]) : __('orders.flash.approved_no_mail'));
    }

    /** Who may change a won order: dry-hire or sales from level 2. */
    private function canChange(User $me, Order $order): bool
    {
        return in_array($order->status, ['allocation', 'approved'], true) && ! $order->returned_at && ($me->canUseModule('dryhire', 2) || $me->canUseModule('sales', 2));
    }

    /** Logistics change: other out / use times after the quote was won. */
    public function times(Request $request, Order $order): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->canChange($me, $order), 403);
        $d = $request->validate([
            'out_from' => ['required', 'date'], 'out_until' => ['required', 'date', 'after:out_from'],
            'use_from' => ['required', 'date'], 'use_until' => ['required', 'date', 'after_or_equal:use_from'],
            'move_subhire' => ['boolean'],
        ]);
        $released = OrderChanges::changeTimes($order, $d, $request->boolean('move_subhire'), $me);
        $adjust = $order->subhires()->where('status', 'accepted')->get()->filter(fn ($r) => $r->changes())->count();

        return back()->with('status', __('orders.change.times_saved').($released ? ' '.__('orders.change.released', ['list' => implode(', ', $released)]) : '').($adjust ? ' '.trans_choice('orders.change.tell_partners', $adjust, ['n' => $adjust]) : ''));
    }

    /** Who may set transport: as for changes, and also after the return, until the pre-invoice is with the accountant. */
    private function canTransport(User $me, Order $order): bool
    {
        return in_array($order->status, ['allocation', 'approved'], true) && ($me->canUseModule('dryhire', 2) || $me->canUseModule('sales', 2))
            && ! $order->lockingPreInvoice();
    }

    /** Logistics and transport price after the quote was won. */
    public function transport(Request $request, Order $order): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->canTransport($me, $order), 403);
        $d = $request->validate([
            'logistics' => ['required', Rule::in(Order::logisticsOptions())],
            'transport' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'transport_note' => ['nullable', 'string', 'max:300'],
        ]);
        $before = ['logistics' => $order->logistics, 'transport' => Pricing::eur($order->transportCharged()), 'note' => $order->transport_note];
        $order->fill(['logistics' => $d['logistics'], 'transport_cents' => (int) round(((float) ($d['transport'] ?? 0)) * 100), 'transport_note' => $d['transport_note'] ?? null]);
        $order->refreshTotals();
        $order->save();
        $after = ['logistics' => $order->logistics, 'transport' => Pricing::eur($order->transportCharged()), 'note' => $order->transport_note];
        if ($before !== $after) {
            AuditLog::record('order.transport', $order, ['before' => $before, 'after' => $after, 'total' => Pricing::eur($order->net_cents)], $me);
        }

        return redirect()->to(route('orders.show', $order).'#logistics')->with('status', __('orders.logistics.saved', ['total' => Pricing::eur($order->net_cents)]));
    }

    /** The customer changes or cancels: back to a new quote version. */
    public function reopen(Request $request, Order $order): RedirectResponse
    {
        $me = $request->user();
        abort_unless($this->canChange($me, $order) && ! $order->loaded_at, 403);
        $quote = OrderChanges::reopen($order, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'], $me);

        return redirect()->route('quotes.show', $quote)->with('status', __('orders.change.reopened', ['code' => $quote->code()]));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $me = $request->user();
        abort_unless(($order->isInAllocation() && $me->canUseModule('dryhire', 3)) || ($order->status === 'reopened' && ($me->canUseModule('dryhire', 2) || $me->canUseModule('sales', 2))), 403);
        Orders::cancel($order, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'], $me);

        return back()->with('status', __('orders.flash.cancelled'));
    }

    public function print(Order $order): View
    {
        $order->load(['lines', 'company', 'contact', 'handedBy']);
        app()->setLocale($order->language);

        return view('rental.orders.print', ['order' => $order, 'me' => CompanyDetail::current()]);
    }
}
