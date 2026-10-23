<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\OrderLine;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\Move;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use App\Services\Warehouse\Carriers;
use App\Services\Warehouse\Checks;
use App\Services\Warehouse\Moves;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The warehouse app on the crew's phone (or the PC at the test bench):
 * scan the piece, then where it goes. Picking, returns, cleaning and the check.
 */
class PhoneController extends Controller
{
    public function home(Request $request): View
    {
        $until = today()->addDays((int) config('warehouse.pick_days'))->endOfDay();

        return view('warehouse.phone.home', [
            'picks' => Order::with('company')->where('status', 'approved')->whereNull('loaded_at')->where('out_from', '<=', $until)->orderBy('out_from')->get(),
            'returns' => Order::with('company')->where('status', 'approved')->whereNotNull('loaded_at')->whereNull('returned_at')->orderBy('out_until')->get(),
            'route' => Asset::with('item')->whereIn('status', [Asset::CHECK, Asset::REPAIR])->get()->groupBy('location'),
            'zones' => Zone::where('active', true)->orderBy('sort')->get()->keyBy('code'),
        ]);
    }

    // ------------------------------------------------------------------ scan and move

    private function pending(Request $request): ?Asset
    {
        $id = $request->session()->get('w.pending');

        return $id ? Asset::with('item')->find($id) : null;
    }

    /** The case (dolly, box…) that is "open": scanned pieces go in it, a scanned place moves it. */
    private function openCarrier(Request $request): ?Asset
    {
        $id = $request->session()->get('w.carrier');

        return $id ? Asset::with(['item', 'packed.item', 'outOrder'])->find($id) : null;
    }

    /** A case that is stored (not on its way through cleaning or the check) works as a dolly or box. */
    private static function asCase(?array $found): ?Asset
    {
        $a = ($found['type'] ?? null) === 'asset' ? $found['model'] : null;

        return $a && $a->isContainer() && ($a->status === Asset::AVAILABLE || $a->status === Asset::OUT) ? $a : null;
    }

    public function scan(Request $request): View
    {
        $pending = $this->pending($request);
        $here = null;
        if ($code = $request->session()->get('w.here')) {
            $all = Asset::with('item')->withCount('packed')->where('location', $code)->whereNull('case_id')->orderBy('code')->limit(80)->get();
            $here = ['code' => $code, 'label' => Moves::label($code), 'carriers' => $all->filter->isContainer()->values(), 'assets' => $all->reject->isContainer()->values()];
        }
        $next = [];
        if ($pending) {
            $zone = Moves::zoneOf($pending->location);
            $next = $pending->status === Asset::OUT ? ['INBOUND'] : ($zone?->next ?? []);
            $next = collect($next)->map(fn ($c) => $c === Zone::RACKS ? __('warehouse.zone_names.RACKS') : Moves::label($c))->all();
        }

        return view('warehouse.phone.scan', ['pending' => $pending, 'next' => $next, 'here' => $here, 'scanned' => $request->query('scanned'),
            'carrier' => $this->openCarrier($request), 'mode' => $request->session()->get('w.carrier_mode', 'add')]);
    }

    public function scanPost(Request $request): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:200']])['code'];
        $found = Moves::resolve($code);
        if (! $found) {
            return redirect()->route('w.scan')->withErrors(['code' => __('warehouse.errors.unknown', ['code' => mb_strimwidth($code, 0, 40, '…')])]);
        }
        $request->session()->forget('w.here');
        // A label printed in advance, scanned for the first time: the piece is found and now in stock.
        if ($found['type'] === 'asset' && $found['model']->status === Asset::LABEL) {
            $asset = $found['model'];
            $asset->update(['status' => Asset::AVAILABLE]);
            Move::create(['asset_id' => $asset->id, 'item_id' => $asset->item_id, 'kind' => 'register', 'user_id' => $request->user()->id]);
            if ($open = $this->openCarrier($request)) {
                Carriers::pack($asset->fresh('item'), $open, $request->user());

                return redirect()->route('w.scan')->with('status', __('warehouse.scan.registered_in', ['code' => $asset->code, 'name' => $asset->item->name, 'carrier' => $open->code]));
            }
            $request->session()->put('w.pending', $asset->id);

            return redirect()->route('w.scan')->with('status', __('warehouse.scan.registered', ['code' => $asset->code, 'name' => $asset->item->name]));
        }
        $open = $this->openCarrier($request);
        // A dolly or box: put the waiting piece in it, or open it (scan pieces to fill it, a place to move it).
        if ($carrier = self::asCase($found)) {
            if ($pending = $this->pending($request)) {
                Carriers::pack($pending, $carrier, $request->user());
                $request->session()->forget('w.pending');
                $request->session()->put('w.carrier', $carrier->id);
                $request->session()->put('w.carrier_mode', 'add');

                return redirect()->route('w.scan')->with('status', __('warehouse.carriers.packed', ['code' => $pending->code, 'carrier' => $carrier->code]));
            }
            $request->session()->put('w.carrier', $carrier->id);
            $request->session()->put('w.carrier_mode', 'add');

            return redirect()->route('w.scan');
        }
        if ($found['type'] === 'asset' && $open && $open->id !== $found['model']->id && $request->session()->get('w.carrier_mode') === 'remove') {
            $piece = $found['model'];
            if ($piece->case_id !== $open->id) {
                return redirect()->route('w.scan')->withErrors(['code' => __('warehouse.errors.carrier_not_in', ['code' => $piece->code, 'carrier' => $open->code])]);
            }
            Carriers::unpack($piece, $request->user());

            return redirect()->route('w.scan')->with('status', __('warehouse.carriers.taken_out', ['code' => $piece->code]));
        }
        if ($found['type'] === 'asset' && $open && $open->id !== $found['model']->id) {
            Carriers::pack($found['model'], $open, $request->user());

            return redirect()->route('w.scan')->with('status', __('warehouse.carriers.packed', ['code' => $found['model']->code, 'carrier' => $open->code]));
        }
        if ($found['type'] !== 'asset' && $open) {
            $n = Carriers::move($open, $found['model'], $request->user());
            $request->session()->forget('w.carrier');

            return redirect()->route('w.scan')->with('status', trans_choice('warehouse.carriers.moved', $n, ['n' => $n, 'carrier' => $open->code, 'place' => Moves::label($found['model']->code)]));
        }
        if ($found['type'] === 'asset') {
            $asset = $found['model'];
            // Scanned the piece that waits for its next step: open that step.
            if ($asset->location && ($z = Moves::zoneOf($asset->location))) {
                $open = Check::where('asset_id', $asset->id)->whereNull('finished_at')->latest('id')->first();
                if ($z->type === 'cleaning' && ! $open?->cleaned_at) {
                    return redirect()->route('w.clean', $asset);
                }
                if ($z->type === 'bench' && ! Check::where('asset_id', $asset->id)->latest('id')->first()?->finished_at) {
                    return redirect()->route('w.check', $asset);
                }
            }
            $request->session()->put('w.pending', $asset->id);

            return redirect()->route('w.scan')->with('status', __('warehouse.scan.picked_up', ['code' => $asset->code, 'name' => $asset->item->name, 'place' => Moves::label($asset->location)]));
        }
        $place = $found['model'];
        $pending = $this->pending($request);
        if (! $pending) {
            $request->session()->put('w.here', $place->code);

            return redirect()->route('w.scan');
        }
        $next = Moves::move($pending, $place, $request->user());
        $request->session()->forget('w.pending');
        $msg = __('warehouse.scan.moved', ['code' => $pending->code, 'place' => Moves::label($place->code)]);
        if ($pending->fresh()->status === Asset::AVAILABLE && $place instanceof Position) {
            $msg = __('warehouse.scan.stored', ['code' => $pending->code, 'place' => $place->code]);
        }

        return match ($next) {
            'clean' => redirect()->route('w.clean', $pending)->with('status', $msg),
            'check' => redirect()->route('w.check', $pending)->with('status', $msg),
            default => redirect()->route('w.scan')->with('status', $msg),
        };
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['w.pending', 'w.here', 'w.carrier', 'w.carrier_mode']);

        return redirect()->route('w.scan');
    }

    /** Stop filling the dolly or box at the return: next pieces come in loose (or scan another dolly). */
    public function returnCarrierDone(Request $request, Order $order): RedirectResponse
    {
        $request->session()->forget('w.ret_carrier');

        return back();
    }

    /** Add or take out: what scanning a piece does while a case is open. */
    public function carrierMode(Request $request): RedirectResponse
    {
        $request->session()->put('w.carrier_mode', $request->validate(['mode' => ['required', 'in:add,remove']])['mode']);

        return redirect()->route('w.scan');
    }

    /** Take a piece out of the open dolly or box. */
    public function carrierOut(Request $request, Asset $asset): RedirectResponse
    {
        Carriers::unpack($asset, $request->user());

        return redirect()->route('w.scan')->with('status', __('warehouse.carriers.taken_out', ['code' => $asset->code]));
    }

    // ------------------------------------------------------------------ picking

    public function pick(Order $order): View
    {
        abort_unless($order->status === 'approved', 404);

        return view('warehouse.phone.pick', ['order' => $order->load('company'), 'lines' => Moves::pickLines($order), 'picked' => Moves::isPicked($order)]);
    }

    public function pickScan(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === 'approved' && ! $order->loaded_at, 409);
        $found = Moves::resolve((string) $request->validate(['code' => ['required', 'string', 'max:200']])['code']);
        if ($case = self::asCase($found)) {
            $n = Carriers::pick($order, $case, $request->user());

            return back()->with('status', trans_choice('warehouse.carriers.picked', $n, ['n' => $n, 'carrier' => $found['model']->code]));
        }
        if (($found['type'] ?? null) !== 'asset') {
            throw ValidationException::withMessages(['code' => __('warehouse.errors.scan_a_piece')]);
        }
        Moves::pick($order, $found['model'], $request->user());

        return back()->with('status', __('warehouse.pick.picked', ['code' => $found['model']->code]));
    }

    public function pickCount(Request $request, Order $order, OrderLine $line): RedirectResponse
    {
        abort_unless($line->order_id === $order->id && ! $line->bySerial() && $order->status === 'approved', 404);
        $d = $request->validate(['delta' => ['required', 'integer', 'between:-1000,1000'], 'field' => ['required', Rule::in(['picked_qty', 'returned_qty'])]]);
        abort_if($d['field'] === 'picked_qty' && $order->loaded_at, 409);
        abort_if($d['field'] === 'returned_qty' && ! $order->loaded_at, 409);
        Moves::count($line, $d['field'], $d['delta'], $request->user());
        if ($d['field'] === 'returned_qty') {
            Moves::maybeReturned($order->fresh());
        }

        return back();
    }

    public function load(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === 'approved' && ! $order->loaded_at, 409);
        Moves::load($order, $request->user());

        return redirect()->route('w.home')->with('status', __('warehouse.pick.loaded', ['order' => $order->number]));
    }

    // ------------------------------------------------------------------ returns

    public function returns(Order $order): View
    {
        abort_unless($order->loaded_at, 404);

        [$forOrder, $carrierId] = session('w.ret_carrier', [null, null]);

        return view('warehouse.phone.return', ['order' => $order->load('company'), 'lines' => Moves::pickLines($order),
            'retCarrier' => $forOrder === $order->id ? Asset::with('item')->withCount('packed')->find($carrierId) : null]);
    }

    public function returnScan(Request $request, Order $order): RedirectResponse
    {
        $found = Moves::resolve((string) $request->validate(['code' => ['required', 'string', 'max:200']])['code']);
        if (($case = self::asCase($found)) && $case->out_order_id === $order->id && $case->status === Asset::OUT) {
            Carriers::returnIn($order, $case, $request->user());
            $request->session()->put('w.ret_carrier', [$order->id, $found['model']->id]);

            return back()->with('status', __('warehouse.carriers.returned', ['carrier' => $found['model']->code]));
        }
        if (($found['type'] ?? null) !== 'asset') {
            throw ValidationException::withMessages(['code' => __('warehouse.errors.scan_a_piece')]);
        }
        $asset = $found['model'];
        if ($asset->out_order_id !== $order->id || $asset->status !== Asset::OUT) {
            throw ValidationException::withMessages(['code' => __('warehouse.errors.not_out_with', ['code' => $asset->code, 'order' => $order->number])]);
        }
        $inbound = Zone::where('type', 'inbound')->where('active', true)->orderBy('sort')->firstOrFail();
        // The dolly or box scanned last for this return gets the piece.
        [$forOrder, $carrierId] = $request->session()->get('w.ret_carrier', [null, null]);
        $into = $forOrder === $order->id ? Asset::find($carrierId) : null;
        Moves::move($asset, $inbound, $request->user(), $into);

        return back()->with('status', __('warehouse.ret.in', ['code' => $asset->code, 'place' => $inbound->label()]).($into ? ' '.__('warehouse.carriers.in_it', ['carrier' => $into->code]) : ''));
    }

    public function returnClose(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->loaded_at, 409);
        $n = Moves::closeReturn($order, $request->user());

        return redirect()->route('w.home')->with('status', trans_choice('warehouse.ret.closed', $n, ['n' => $n, 'order' => $order->number]));
    }

    // ------------------------------------------------------------------ cleaning and the check

    public function clean(Asset $asset): View
    {
        $check = Check::openFor($asset->load('item'));

        return view('warehouse.phone.clean', ['asset' => $asset, 'check' => $check]);
    }

    public function cleanSave(Request $request, Asset $asset): RedirectResponse
    {
        $d = $request->validate(['dirt' => ['required', 'integer', 'between:1,3'], 'photos' => ['array', 'max:6'], 'photos.*' => Images::rule()]);
        $check = Check::openFor($asset);
        Checks::addPhotos($check, $request->file('photos', []), 'clean');
        if ($request->boolean('photo_only')) {
            return back()->withInput();
        }
        Checks::clean($asset, (int) $d['dirt'], $request->user());
        $request->session()->put('w.pending', $asset->id);

        return redirect()->route('w.scan')->with('status', __('warehouse.clean.done', ['code' => $asset->code]));
    }

    public function check(Asset $asset): View
    {
        $asset->load('item.kit');
        $check = Check::openFor($asset);

        return view('warehouse.phone.check', [
            'asset' => $asset, 'check' => $check, 'tests' => Checks::tests($asset),
            'stockItems' => Item::where('serial', false)->where('active', true)->where('kind', 'item')->orderBy('name')->limit(500)->get(['id', 'number', 'name', 'stock_qty']),
        ]);
    }

    public function checkSave(Request $request, Asset $asset): RedirectResponse
    {
        $d = $request->validate([
            'tests' => ['array'], 'tests.*' => [Rule::in(['ok', 'fail'])],
            'missing' => ['array'], 'missing.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'extra_item' => ['nullable', 'string', 'max:60'], 'extra_qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'damage_type' => ['array'], 'damage_type.*' => ['nullable', Rule::in(config('warehouse.damage_types'))],
            'damage_note' => ['array'], 'damage_note.*' => ['nullable', 'string', 'max:200'],
            'photos' => ['array', 'max:8'], 'photos.*' => Images::rule(),
        ]);
        $check = Check::openFor($asset);
        Checks::addPhotos($check, $request->file('photos', []), 'check');
        if ($request->boolean('photo_only')) {
            return back()->withInput();
        }
        $missing = array_map('intval', array_filter($d['missing'] ?? []));
        if ($d['extra_item'] ?? null) {
            $extra = Item::where('number', strtoupper(trim(explode(' ', $d['extra_item'])[0])))->first();
            if (! $extra) {
                throw ValidationException::withMessages(['extra_item' => __('warehouse.errors.no_item', ['code' => $d['extra_item']])]);
            }
            $missing[$extra->id] = ($missing[$extra->id] ?? 0) + (int) ($d['extra_qty'] ?? 1);
        }
        $damages = [];
        foreach ($d['damage_type'] ?? [] as $i => $type) {
            if ($type) {
                $damages[] = ['type' => $type, 'note' => $d['damage_note'][$i] ?? null];
            }
        }
        $check = Checks::finish($asset, $d['tests'] ?? [], $missing, $damages, $request->boolean('reset'), $request->user());
        $request->session()->put('w.pending', $asset->id);

        return redirect()->route('w.scan')->with('status', __($check->result === 'ok' ? 'warehouse.check.ok' : 'warehouse.check.repair', ['code' => $asset->code]));
    }

    /** A photo of a check, for staff. */
    public function photo(Check $check, int $i)
    {
        $path = $check->photos[$i]['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }
}
