<?php

namespace App\Http\Controllers\Equipment;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Services\Warehouse\Carriers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** One piece: its card, editing, retiring and the QR labels. */
class AssetController extends Controller
{
    public function show(Request $request, Asset $asset): View
    {
        $asset->load('item');
        $user = $request->user();

        return view('equipment.asset', [
            'asset' => $asset,
            'canEdit' => $user->canUseModule('dryhire', 2),
            'canMoney' => $user->hasRight('finance.see_margins'),
            'canRetire' => $user->canUseModule('dryhire', 3) || $user->hasRight('warehouse.write_off'),
            'activity' => AuditLog::with('actor')->where('subject_type', $asset->getMorphClass())->where('subject_id', $asset->id)->latest('id')->limit(30)->get(),
        ]);
    }

    /** From the office: put this piece on a rack place or in a case; for a case, move it with its contents. */
    public function place(Request $request, Asset $asset): RedirectResponse
    {
        $place = $request->validate(['place' => ['required', 'string', 'max:120']])['place'];
        try {
            $where = Carriers::place($asset->load('item'), $place, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['place' => $e->validator->errors()->first()]);
        }
        AuditLog::record('equipment.placed', $asset->item, ['codes' => $asset->code, 'place' => $where]);

        return back()->with('status', trans_choice('equipment.place.done', 1, ['n' => 1, 'place' => $where]));
    }

    /** A scanned label of a counted item opens the item card. */
    public function byNumber(string $number): RedirectResponse
    {
        return redirect()->route('equipment.show', Item::where('number', strtoupper($number))->firstOrFail());
    }

    /** A scanned QR code opens the asset card. */
    public function byCode(string $code): RedirectResponse
    {
        $asset = Asset::where('code', strtoupper($code))->firstOrFail();

        return redirect()->route('equipment.assets.show', $asset);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $canMoney = $request->user()->hasRight('finance.see_margins');
        $d = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:80', Rule::unique('equipment_assets', 'serial_number')->where('item_id', $asset->item_id)->ignore($asset->id)],
            'status' => ['required', Rule::in([Asset::AVAILABLE, Asset::OUT, Asset::REPAIR])],
            'location' => ['nullable', 'string', 'max:60'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'warranty_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        abort_if($asset->status === Asset::RETIRED, 404);
        $values = [
            'serial_number' => $d['serial_number'] ?? null, 'status' => $d['status'], 'location' => $d['location'] ?? null,
            'purchase_date' => $d['purchase_date'] ?? null, 'warranty_until' => $d['warranty_until'] ?? null, 'note' => $d['note'] ?? null,
        ];
        if ($canMoney) {
            $values['purchase_price_cents'] = isset($d['purchase_price']) ? (int) round($d['purchase_price'] * 100) : null;
            $values['supplier'] = $d['supplier'] ?? null;
        }
        $asset->fill($values);
        $changes = array_keys($asset->getDirty());
        $asset->save();
        if ($changes) {
            AuditLog::record('equipment.asset_updated', $asset, ['changes' => array_fill_keys($changes, true)]);
        }

        return back()->with('status', __('equipment.flash.saved'));
    }

    /** Sold, lost, stolen or broken: the piece stays in the history but can no longer be rented. */
    public function retire(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()->canUseModule('dryhire', 3) || $request->user()->hasRight('warehouse.write_off'), 403);
        $d = $request->validate(['reason' => ['required', Rule::in(Asset::RETIRE_REASONS)]]);
        abort_if($asset->status === Asset::RETIRED, 404);
        $asset->update(['status' => Asset::RETIRED, 'retired_reason' => $d['reason'], 'retired_at' => now()]);
        AuditLog::record('equipment.asset_retired', $asset, ['reason' => $d['reason']]);

        return back()->with('status', __('equipment.flash.retired'));
    }

    /**
     * Square QR labels (Dymo 1933083, 25 × 25 mm): ?assets=1,2,3 or ?item=5 (all pieces).
     * The browser prints one label per page on the LabelWriter.
     */
    public function labels(Request $request): View
    {
        // Counted items: N labels with the item's own QR code (opens the item card).
        if ($request->query('bulk')) {
            $item = Item::where('serial', false)->findOrFail((int) $request->query('bulk'));
            $qty = max(1, min(500, (int) $request->query('qty', 1)));

            return view('equipment.labels', ['assets' => collect(), 'bulk' => ['item' => $item, 'qty' => $qty]]);
        }
        $raw = $request->query('assets', '');
        $ids = array_values(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string) $raw))));
        $codes = array_filter(explode(',', (string) $request->query('codes', '')));
        $assets = Asset::with('item')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when($codes, fn ($q) => $q->whereIn('code', $codes))
            ->when($request->query('item'), fn ($q, $item) => $q->where('item_id', (int) $item))
            ->when(! $ids && ! $codes && ! $request->query('item'), fn ($q) => $q->whereRaw('1 = 0'))
            ->where('status', '!=', Asset::RETIRED)->orderBy('code')->limit(500)->get();
        abort_if($assets->isEmpty(), 404);
        Asset::whereIn('id', $assets->pluck('id'))->update(['label_printed_at' => now()]);

        return view('equipment.labels', ['assets' => $assets]);
    }
}
