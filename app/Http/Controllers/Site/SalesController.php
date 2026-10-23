<?php

namespace App\Http\Controllers\Site;

use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Tag;
use App\Models\ProductSales\ItemDocument;
use App\Models\ProductSales\SalesItem;
use App\Services\Web\CustomerAuth;
use App\Services\Website\SalesCatalogue;
use App\Services\Website\WebRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The sales catalogue on the website: browse by category or brand, search, filter, ask a quote. */
class SalesController extends SiteController
{
    private function common(string $locale): array
    {
        return [
            'locale' => $locale,
            'tree' => SalesCatalogue::tree(),
            'customer' => CustomerAuth::current(),
            'cfg' => [
                't' => __('site.sjs') + ['s_none' => __('site.sales.s_none')],
                's' => ['products' => __('site.sales.s_products'), 'categories' => __('site.sales.s_categories'), 'brands' => __('site.sales.s_brands'),
                    'view_all' => __('site.sales.view_all'), 'more' => __('site.sales.more'), 'less' => __('site.sales.less')],
                'locale' => $locale,
                'urls' => ['suggest' => route('site.sales.suggest'), 'request' => route('site.sales.request'), 'search' => route('site.sales.search')],
            ],
        ];
    }

    public function index(Request $request): View
    {
        $locale = $this->locale($request);
        $c = $this->common($locale);
        $newTag = Tag::of('sales')->where('slug', 'new')->first();
        $new = SalesCatalogue::query()->when($newTag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($newTag->id)), fn ($q) => $q->latest('id'))->latest('id')->limit(8)->get();
        $new->each(fn ($i) => $i->price_cents = \App\Services\ProductSales\CustomerPrices::priceCents($c['customer'], $i));

        return view('site.sales.home', $c + ['brands' => SalesCatalogue::brands(), 'new' => $new, 'total' => SalesCatalogue::query()->count()]);
    }

    public function category(Request $request, string $cat, ?string $sub = null): mixed
    {
        $locale = $this->locale($request);
        $category = Category::of('sales')->where('active', true)->where('slug', $cat)->first()
            ?? Category::of('sales')->where('active', true)->get()->first(fn ($c) => in_array($cat, $c->old_slugs ?? [], true));
        abort_unless($category, 404);
        if ($category->slug !== $cat) {
            return redirect()->route('site.sales.category', array_filter([$category->slug, $sub]), 301);
        }
        $subcategory = null;
        if ($sub) {
            $subcategory = $category->subcategories()->where('active', true)->where('slug', $sub)->first();
            abort_unless($subcategory, 404);
        }
        $c = $this->common($locale);
        $node = $c['tree']->firstWhere('id', $category->id);
        $tiles = ! $subcategory && $node && $node->subcategories->count() > 1 ? $node->subcategories : collect();

        return view('site.sales.listing', $c + [
            'mode' => 'category', 'category' => $category, 'subcategory' => $subcategory, 'tiles' => $tiles,
            'title' => $subcategory ? $subcategory->label($locale) : $category->label($locale),
            'list' => SalesCatalogue::listing($this->filters($request) + ['cat' => $category->id, 'sub' => $subcategory?->id], $c['customer'], $locale),
        ]);
    }

    public function search(Request $request): View
    {
        $locale = $this->locale($request);
        $c = $this->common($locale);
        $q = trim((string) $request->query('q'));

        return view('site.sales.listing', $c + [
            'mode' => 'search', 'q' => $q, 'title' => $q !== '' ? '“'.$q.'”' : __('site.sales.title'),
            'list' => SalesCatalogue::listing($this->filters($request) + ['q' => $q], $c['customer'], $locale),
        ]);
    }

    public function new(Request $request): View
    {
        $locale = $this->locale($request);
        $c = $this->common($locale);
        $tag = Tag::of('sales')->where('slug', 'new')->first();

        return view('site.sales.listing', $c + [
            'mode' => 'new', 'title' => __('site.sales.new_items'), 'intro' => __('site.sales.new_text'),
            'list' => SalesCatalogue::listing($this->filters($request) + ($tag ? ['tag_id' => $tag->id] : ['sort' => $request->query('sort', 'new')]), $c['customer'], $locale),
        ]);
    }

    public function brands(Request $request): View
    {
        $locale = $this->locale($request);

        return view('site.sales.brands', $this->common($locale) + ['brands' => SalesCatalogue::brands()]);
    }

    public function brand(Request $request, string $brand): mixed
    {
        $locale = $this->locale($request);
        $b = Brand::where('active', true)->where('slug', $brand)->first();
        if (! $b) {
            $old = Brand::findByName($brand);
            abort_unless($old && $old->active, 404);

            return redirect()->route('site.sales.brand', $old->slug, 301);
        }
        $c = $this->common($locale);

        return view('site.sales.listing', $c + [
            'mode' => 'brand', 'brand' => $b, 'title' => $b->name, 'intro' => $b->introText($locale),
            'list' => SalesCatalogue::listing($this->filters($request) + ['brand_id' => $b->id], $c['customer'], $locale),
        ]);
    }

    public function product(Request $request, string $item): mixed
    {
        $locale = $this->locale($request);
        $model = $this->find($item);
        if (SalesCatalogue::slug($model) !== $item) {
            return redirect()->route('site.sales.product', SalesCatalogue::slug($model), 301);
        }
        $c = $this->common($locale);

        return view('site.sales.product', $c + ['d' => SalesCatalogue::detail($model, $c['customer'], $locale)]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $locale = $this->locale($request);

        return response()->json(SalesCatalogue::suggest((string) $request->query('q'), CustomerAuth::current(), $locale));
    }

    public function photo(int $item, int $w): BinaryFileResponse
    {
        abort_unless(in_array($w, config('website.photo_widths'), true), 404);
        $file = SalesCatalogue::photoFile($this->find((string) $item), $w);
        abort_unless($file, 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=604800']);
    }

    public function document(int $doc): mixed
    {
        $d = ItemDocument::findOrFail($doc);
        $this->find((string) $d->sales_item_id);
        abort_unless(Storage::disk('local')->exists($d->path), 404);

        return response()->file(Storage::disk('local')->path($d->path), ['Content-Disposition' => 'inline; filename="'.Str::ascii($d->name).'"', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** A quote request from a visitor who is not signed in. */
    public function request(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        if (filled($request->input('website_url'))) {
            return response()->json(['ok' => true]);
        }
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:160'],
            'vat' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:5000'],
            'delivery' => ['nullable', 'in:pick,ship'],
            'lines' => ['required', 'array', 'min:1', 'max:'.config('website.max_lines')],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:'.config('website.max_qty')],
            'page' => ['nullable', 'string', 'max:300'],
        ]);
        $msg = WebRequests::sales($d, $locale);

        return response()->json(['ok' => true, 'ref' => 'W-'.$msg->id]);
    }

    private function filters(Request $r): array
    {
        return [
            'brands' => (array) $r->query('brands', []), 'opts' => (array) $r->query('opts', []), 'tags' => (array) $r->query('tags', []), 'subs' => (array) $r->query('subs', []),
            'stock' => $r->boolean('stock'), 'min' => $r->query('min'), 'max' => $r->query('max'), 'sort' => $r->query('sort'), 'page' => $r->integer('page', 1),
        ];
    }

    private function find(string $slug): SalesItem
    {
        $i = SalesItem::find((int) Str::before($slug, '-'));
        abort_unless($i && SalesCatalogue::visible($i), 404);

        return $i;
    }
}
