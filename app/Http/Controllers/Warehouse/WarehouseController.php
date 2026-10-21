<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Equipment\Asset;
use App\Models\Rental\Order;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\Move;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use App\Services\Afrit6\Pricing;
use App\Services\Warehouse\Moves;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The warehouse for the office: where everything is, repairs, and charges to approve. */
class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $zones = Zone::where('active', true)->orderBy('sort')->get();
        $counts = Asset::whereNotNull('location')->where('status', '!=', Asset::RETIRED)->selectRaw('location, count(*) as n')->groupBy('location')->pluck('n', 'location');
        $storage = $zones->firstWhere('type', 'storage');
        $zoneCounts = $zones->mapWithKeys(fn (Zone $z) => [$z->code => $z->type === 'storage'
            ? Asset::where('status', Asset::AVAILABLE)->whereIn('location', Position::pluck('code'))->count()
            : (int) ($counts[$z->code] ?? 0)]);
        $zone = $zones->firstWhere('code', strtoupper((string) $request->query('zone')));
        $list = null;
        if ($zone) {
            $list = $zone->type === 'storage'
                ? Asset::with('item')->where('status', Asset::AVAILABLE)->whereIn('location', Position::pluck('code'))->orderBy('location')->limit(200)->get()
                : Asset::with('item')->where('location', $zone->code)->orderBy('code')->get();
        }

        return view('warehouse.index', [
            'zones' => $zones, 'zoneCounts' => $zoneCounts, 'zone' => $zone, 'list' => $list, 'storage' => $storage,
            'out' => Asset::where('status', Asset::OUT)->count(),
            'repair' => Asset::with('item')->where('status', Asset::REPAIR)->orderBy('code')->get(),
            'charges' => Order::with('company')->whereHas('charges', fn ($q) => $q->where('status', 'pending'))->withCount(['charges as pending' => fn ($q) => $q->where('status', 'pending')])->get(),
            'moves' => Move::with(['asset.item', 'item', 'user', 'order'])->where('kind', '!=', 'lost')->latest('id')->limit(40)->get(),
            'lost' => Move::with(['item', 'order', 'user'])->where('kind', 'lost')->latest('id')->limit(20)->get(),
            'picks' => Order::where('status', 'approved')->whereNull('loaded_at')->whereDate('out_from', '<=', today()->addDay())->count(),
            'canRelease' => $request->user()->hasRight('warehouse.release_repair'),
        ]);
    }

    public function release(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($asset->status === Asset::REPAIR && ! $asset->repair_released_at, 409);
        Moves::releaseRepair($asset, $request->user());

        return back()->with('status', __('warehouse.office.released', ['code' => $asset->code]));
    }

    // ------------------------------------------------------------------ charges

    private function canDecide(Request $request): bool
    {
        return $request->user()->canUseModule('sales', 2) || $request->user()->hasRight('orders.approve');
    }

    public function charges(Request $request, Order $order): View
    {
        return view('warehouse.charges', [
            'order' => $order->load('company'), 'charges' => Charge::with(['asset', 'decidedBy'])->where('order_id', $order->id)->orderBy('id')->get(),
            'canDecide' => $this->canDecide($request),
        ]);
    }

    /** Change one line before approval: a price, half price as a gesture, or waive it. */
    public function updateCharge(Request $request, Charge $charge): RedirectResponse
    {
        abort_unless($this->canDecide($request) && $charge->status === 'pending', 403);
        $d = $request->validate(['action' => ['required', 'in:price,half,waive'], 'amount' => ['nullable', 'numeric', 'min:0', 'max:100000']]);
        match ($d['action']) {
            'price' => $charge->update(['amount_cents' => isset($d['amount']) ? (int) round($d['amount'] * 100) : null, 'original_cents' => $charge->original_cents ?? (isset($d['amount']) ? (int) round($d['amount'] * 100) : null)]),
            'half' => $charge->update(['amount_cents' => (int) round(($charge->amount_cents ?? 0) / 2)]),
            'waive' => $charge->update(['status' => 'waived', 'decided_by_id' => $request->user()->id, 'decided_at' => now()]),
        };

        return back();
    }

    /** Approve: the lines go on a separate proforma for the customer. */
    public function approveCharges(Request $request, Order $order): RedirectResponse
    {
        abort_unless($this->canDecide($request), 403);
        $pending = Charge::where('order_id', $order->id)->where('status', 'pending')->get();
        if ($pending->contains(fn ($c) => $c->amount_cents === null)) {
            return back()->withErrors(['amount' => __('warehouse.office.price_first')]);
        }
        foreach ($pending as $c) {
            $c->update(['status' => 'approved', 'decided_by_id' => $request->user()->id, 'decided_at' => now()]);
        }
        AuditLog::record('warehouse.charges_approved', $order, ['total' => Pricing::eur((int) $pending->sum('amount_cents')), 'lines' => $pending->count()], $request->user());

        return back()->with('status', __('warehouse.office.approved', ['code' => self::code($order)]));
    }

    public static function code(Order $order): string
    {
        return str_replace('R-OC', 'R-CH', $order->number);
    }

    /** The proforma with the approved charges, in the customer's language. */
    public function proforma(Order $order): View
    {
        $charges = Charge::where('order_id', $order->id)->where('status', 'approved')->orderBy('id')->get();
        abort_if($charges->isEmpty(), 404);
        $order->load(['company', 'contact']);
        app()->setLocale(array_key_exists($order->language, config('cluk.locales')) ? $order->language : 'en');
        $net = (int) $charges->sum('amount_cents');
        $vat = (int) round($net * ($order->vat_rate_bp ?? 0) / 10000);

        return view('rental.docs.ch', ['order' => $order, 'type' => 'ch', 'code' => self::code($order), 'me' => CompanyDetail::current(),
            'charges' => $charges, 'net' => $net, 'vatCents' => $vat]);
    }
}
