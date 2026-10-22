<?php

namespace App\Http\Controllers\Equipment;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Dry-hire equipment: items, packages, cases and fixed-price services. */
class ItemController extends Controller
{
    public const FILTERS = ['all', 'item', 'package', 'case', 'service'];

    public function index(Request $request): View
    {
        $kind = in_array($request->query('kind'), self::FILTERS, true) ? $request->query('kind') : 'all';
        $cat = (string) $request->query('cat', '');
        $q = trim((string) $request->query('q', ''));
        $showInactive = $request->boolean('inactive');
        $onlyWeb = $request->boolean('web');

        $base = Item::query()->when(! $showInactive, fn ($w) => $w->where('active', true))->when($onlyWeb, fn ($w) => $w->where('on_website', true));
        $counts = ['all' => (clone $base)->count()];
        foreach (array_slice(self::FILTERS, 1) as $k) {
            $counts[$k] = (clone $base)->where('kind', $k)->count();
        }

        $items = (clone $base)
            ->when($kind !== 'all', fn ($w) => $w->where('kind', $kind))
            ->when($cat !== '', fn ($w) => $w->where('category', $cat))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $w->where(fn ($x) => $x->whereRaw('lower(number) like ?', [$like])->orWhereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(brand, \'\')) like ?', [$like])->orWhereRaw('lower(coalesce(type, \'\')) like ?', [$like])
                    ->orWhereHas('assets', fn ($a) => $a->whereRaw('lower(coalesce(serial_number, \'\')) like ?', [$like])->orWhereRaw('lower(code) like ?', [$like])));
            })
            ->with(['assets', 'contents.assets'])
            ->orderBy('category')->orderBy('number')
            ->paginate(50)->withQueryString();

        return view('equipment.index', ['onlyWeb' => $onlyWeb,
            'items' => $items, 'kind' => $kind, 'cat' => $cat, 'q' => $q, 'counts' => $counts, 'showInactive' => $showInactive,
            'categories' => Item::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'canEdit' => $request->user()->canUseModule('dryhire', 2),
        ]);
    }

    public function show(Request $request, Item $item): View
    {
        $item->load(['assets', 'kit', 'contents.assets', 'photos']);
        $user = $request->user();

        return view('equipment.show', [
            'item' => $item,
            'canEdit' => $user->canUseModule('dryhire', 2),
            'canMoney' => $user->hasRight('finance.see_margins'),
            'usedIn' => Item::whereHas('children', fn ($q) => $q->whereKey($item->id))->orderBy('number')->get(),
            'options' => Item::where('id', '!=', $item->id)->whereIn('kind', ['item', 'case'])->orderBy('number')->get(['id', 'number', 'name']),
            'activity' => AuditLog::with('actor')->where('subject_type', $item->getMorphClass())->where('subject_id', $item->id)->latest('id')->limit(20)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('equipment.form', ['item' => new Item(['kind' => $request->query('kind', 'item'), 'serial' => true, 'unit' => 'pcs', 'in_search' => true, 'active' => true])]);
    }

    public function edit(Item $item): View
    {
        return view('equipment.form', ['item' => $item]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['photos' => ['nullable', 'array', 'max:12'], 'photos.*' => Images::rule()]);
        $item = new Item;
        $item->fill($this->validated($request, $item))->save();
        foreach ($request->file('photos', []) as $i => $file) {
            try {
                $item->photos()->create(['path' => Images::store($file, 'equipment', 1600), 'position' => $i]);
            } catch (\Throwable) {
                // not a picture: skipped, the item itself is saved
            }
        }
        $item->syncMainPhoto();
        AuditLog::record('equipment.item_created', $item, ['number' => $item->number]);

        return redirect()->route('equipment.show', $item)->with('status', __('equipment.flash.created'));
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $item->fill($this->validated($request, $item));
        if ($item->isDirty('serial') && ! $item->serial && $item->assets()->exists()) {
            throw ValidationException::withMessages(['serial' => __('equipment.errors.has_assets')]);
        }
        $changes = array_keys($item->getDirty());
        $item->save();
        if ($changes) {
            AuditLog::record('equipment.item_updated', $item, ['changes' => array_fill_keys(array_diff($changes, ['updated_at']), true)]);
        }

        return redirect()->route('equipment.show', $item)->with('status', __('equipment.flash.saved'));
    }

    private function validated(Request $request, Item $item): array
    {
        $request->merge(['number' => strtoupper(trim((string) $request->input('number')))]);
        $d = $request->validate([
            'number' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9._\-\/]*$/', Rule::unique('equipment_items', 'number')->ignore($item->id)],
            'kind' => ['required', Rule::in(Item::KINDS)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'subcategory' => ['nullable', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:12'],
            'serial' => ['nullable', 'boolean'],
            'stock_qty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'day_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_unit' => ['nullable', Rule::in(Item::PRICE_UNITS)],
            'catalog_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width_m' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'length_m' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'height_m' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'origin' => ['nullable', 'string', 'size:2'],
            'hs_code' => ['nullable', 'string', 'max:20'],
            'desc' => ['nullable', 'array'],
            'desc.*' => ['nullable', 'string', 'max:2000'],
            'in_search' => ['nullable', 'boolean'],
            'on_website' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
        $serial = in_array($d['kind'], ['item', 'case'], true) && ! empty($d['serial']);

        return [
            'number' => $d['number'], 'kind' => $d['kind'], 'name' => $d['name'],
            'category' => $d['category'] ?? null, 'subcategory' => $d['subcategory'] ?? null,
            'brand' => $d['brand'] ?? null, 'type' => $d['type'] ?? null, 'unit' => $d['unit'],
            'serial' => $serial, 'stock_qty' => $serial ? 0 : (int) ($d['stock_qty'] ?? 0),
            'day_price_cents' => (int) round(((float) ($d['day_price'] ?? 0)) * 100),
            'price_unit' => $d['kind'] === 'service' ? ($d['price_unit'] ?? 'event') : null,
            'catalog_price_cents' => isset($d['catalog_price']) ? (int) round($d['catalog_price'] * 100) : null,
            'weight_kg' => $d['weight_kg'] ?? null, 'width_m' => $d['width_m'] ?? null, 'length_m' => $d['length_m'] ?? null, 'height_m' => $d['height_m'] ?? null,
            'origin' => isset($d['origin']) ? strtoupper($d['origin']) : null, 'hs_code' => $d['hs_code'] ?? null,
            'descriptions' => array_filter(array_intersect_key($d['desc'] ?? [], config('cluk.locales'))) ?: null,
            'in_search' => (bool) ($d['in_search'] ?? false), 'on_website' => (bool) ($d['on_website'] ?? false), 'active' => (bool) ($d['active'] ?? false),
        ];
    }

    /** Add accessories (kit) or contents (package, case). */
    public function addComponent(Request $request, Item $item): RedirectResponse
    {
        $d = $request->validate([
            'child_id' => ['required', 'integer', 'exists:equipment_items,id', Rule::notIn([$item->id])],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        $role = in_array($item->kind, ['package', 'case'], true) ? 'content' : 'kit';
        $child = Item::findOrFail($d['child_id']);
        if ($child->kind === 'service' || $child->kind === 'package') {
            throw ValidationException::withMessages(['child_id' => __('equipment.errors.component_kind')]);
        }
        if ($this->contains($child, $item->id)) {
            throw ValidationException::withMessages(['child_id' => __('equipment.errors.loop')]);
        }
        $item->children()->syncWithoutDetaching([$child->id => ['qty' => $d['qty'], 'role' => $role]]);
        AuditLog::record('equipment.item_updated', $item, ['changes' => [$role => true]]);

        return back()->with('status', __('equipment.flash.saved'));
    }

    /** Would adding $child under $parentId create a loop (child already contains the parent)? */
    private function contains(Item $child, int $parentId, int $depth = 0): bool
    {
        if ($child->id === $parentId) {
            return true;
        }

        return $depth < 6 && $child->children->contains(fn ($c) => $this->contains($c, $parentId, $depth + 1));
    }

    public function removeComponent(Item $item, Item $child): RedirectResponse
    {
        $item->children()->detach($child->id);
        AuditLog::record('equipment.item_updated', $item, ['changes' => ['kit' => true]]);

        return back()->with('status', __('equipment.flash.saved'));
    }

    /** Add pieces: a number of new assets, optionally with serial numbers (one per line). */
    public function addAssets(Request $request, Item $item): RedirectResponse
    {
        abort_unless($item->serial, 404);
        $canMoney = $request->user()->hasRight('finance.see_margins');
        $d = $request->validate([
            'count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'serials' => ['nullable', 'string', 'max:20000'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:60'],
        ]);
        $serials = collect(preg_split('/\R/', (string) ($d['serials'] ?? '')))->map(fn ($s) => trim($s))->filter()->unique()->values();
        $count = max((int) ($d['count'] ?? 0), $serials->count());
        if ($count < 1) {
            throw ValidationException::withMessages(['count' => __('equipment.errors.count')]);
        }
        $dupes = $serials->filter(fn ($s) => $item->assets()->where('serial_number', $s)->exists());
        if ($dupes->isNotEmpty()) {
            throw ValidationException::withMessages(['serials' => __('equipment.errors.serial_exists', ['serials' => $dupes->implode(', ')])]);
        }

        $codes = DB::transaction(function () use ($item, $count, $serials, $d, $canMoney) {
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $asset = $item->assets()->create([
                    'code' => Asset::nextCode($item), 'serial_number' => $serials[$i] ?? null, 'status' => Asset::AVAILABLE,
                    'location' => $d['location'] ?? null, 'purchase_date' => $d['purchase_date'] ?? null,
                    'purchase_price_cents' => $canMoney && isset($d['purchase_price']) ? (int) round($d['purchase_price'] * 100) : null,
                    'supplier' => $canMoney ? ($d['supplier'] ?? null) : null,
                ]);
                $codes[] = $asset->code;
            }

            return $codes;
        });
        AuditLog::record('equipment.assets_added', $item, ['count' => $count, 'codes' => $codes[0].($count > 1 ? ' – '.end($codes) : '')]);

        return redirect()->route('equipment.show', $item)->with('status', trans_choice('equipment.flash.assets_added', $count, ['count' => $count]))
            ->with('print', $codes);
    }

    public function photo(Item $item): Response
    {
        abort_unless($item->photo_path && Storage::disk('local')->exists($item->photo_path), 404);

        return response()->file(Storage::disk('local')->path($item->photo_path), ['Cache-Control' => 'private, max-age=86400']);
    }
}
