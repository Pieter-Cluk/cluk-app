<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product\Brand;
use App\Models\ProductSales\SalesItem;
use App\Services\Media\Translator;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › Product categories › Brands: our own brands, brands we distribute
 * and other brands we resell, with logo and text for the website. The website
 * links to the slug; a changed slug keeps the old one. A brand in use can't be
 * deleted, only switched off.
 */
class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $kind = in_array($request->query('k'), Brand::KINDS, true) ? $request->query('k') : null;

        return view('settings.brands', [
            'brands' => Brand::withCount('salesItems')->when($kind, fn ($q) => $q->where('kind', $kind))->orderBy('sort')->orderBy('name')->get(),
            'kind' => $kind,
            'counts' => Brand::selectRaw('kind, count(*) n')->groupBy('kind')->pluck('n', 'kind'),
            'canTranslate' => Translator::available(),
        ]);
    }

    private function rules(?Brand $b = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('brands', 'name')->ignore($b?->id)],
            'slug' => ['nullable', 'string', 'max:90', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'kind' => ['required', Rule::in(Brand::KINDS)],
            'website' => ['nullable', 'url', 'max:190'],
            'intro' => ['nullable', 'array'], 'intro.*' => ['nullable', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
            'translate' => ['nullable', 'boolean'],
            'logo' => array_merge(['nullable'], Images::rule()),
        ];
    }

    /** Texts per language; empty ones filled by Claude when asked. */
    private function intro(array $d): ?array
    {
        $intro = array_filter(array_intersect_key($d['intro'] ?? [], config('cluk.locales')), 'filled');
        if (! empty($d['translate']) && $intro && count($intro) < count(config('cluk.locales')) && Translator::available()) {
            $src = $intro['en'] ?? reset($intro);
            foreach ((new Translator)->translate(['intro' => $src]) ?? [] as $loc => $f) {
                $intro[$loc] ??= $f['intro'] ?? null;
            }
        }

        return array_filter($intro) ?: null;
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate($this->rules());
        $b = Brand::create(['name' => trim($d['name']), 'slug' => Brand::freeSlug($d['slug'] ?: $d['name']), 'kind' => $d['kind'], 'website' => $d['website'] ?? null,
            'intro' => $this->intro($d), 'sort' => $d['sort'] ?? 100, 'active' => true]);
        if ($request->hasFile('logo')) {
            $b->update(['logo_path' => Images::store($request->file('logo'), 'brands', 600, true)]);
        }
        AuditLog::record('products.brand_added', $b, ['name' => $b->name, 'kind' => $b->kind], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $d = $request->validate($this->rules($brand));
        $slug = $d['slug'] ?: $brand->slug;
        if ($slug !== $brand->slug) {
            if (Brand::where('slug', $slug)->whereKeyNot($brand->id)->exists()) {
                throw ValidationException::withMessages(['slug' => __('products.errors.slug_taken')]);
            }
            $brand->old_slugs = array_values(array_unique(array_merge($brand->old_slugs ?? [], [$brand->slug])));
        }
        $brand->fill(['name' => trim($d['name']), 'slug' => $slug, 'kind' => $d['kind'], 'website' => $d['website'] ?? null, 'intro' => $this->intro($d),
            'sort' => $d['sort'] ?? $brand->sort, 'active' => (bool) ($d['active'] ?? false)]);
        if ($request->hasFile('logo')) {
            Images::delete($brand->logo_path);
            $brand->logo_path = Images::store($request->file('logo'), 'brands', 600, true);
        }
        $changes = array_keys($brand->getDirty());
        $brand->save();
        AuditLog::record('products.brand_saved', $brand, ['name' => $brand->name, 'changed' => implode(', ', $changes)], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        if (SalesItem::where('brand_id', $brand->id)->exists()) {
            throw ValidationException::withMessages(['brand' => __('products.errors.brand_in_use')]);
        }
        Images::delete($brand->logo_path);
        AuditLog::record('products.brand_deleted', null, ['name' => $brand->name], $request->user());
        $brand->delete();

        return back()->with('status', __('products.flash.deleted'));
    }

    /** Move every product of one brand to another (to merge two spellings of the same brand). */
    public function merge(Request $request, Brand $brand): RedirectResponse
    {
        $d = $request->validate(['into' => ['required', 'integer', 'exists:brands,id', Rule::notIn([$brand->id])]]);
        $into = Brand::findOrFail($d['into']);
        $n = SalesItem::where('brand_id', $brand->id)->update(['brand_id' => $into->id, 'brand' => $into->name]);
        $into->update(['old_slugs' => array_values(array_unique(array_merge($into->old_slugs ?? [], [$brand->slug], $brand->old_slugs ?? [])))]);
        AuditLog::record('products.brand_merged', $into, ['from' => $brand->name, 'into' => $into->name, 'items' => $n], $request->user());
        Images::delete($brand->logo_path);
        $brand->delete();

        return back()->with('status', __('products.flash.merged', ['n' => $n, 'into' => $into->name]));
    }

    public function logo(Brand $brand)
    {
        abort_unless($brand->logo_path && Storage::disk('local')->exists($brand->logo_path), 404);

        return response()->file(Storage::disk('local')->path($brand->logo_path), ['Cache-Control' => 'public, max-age=604800']);
    }
}
