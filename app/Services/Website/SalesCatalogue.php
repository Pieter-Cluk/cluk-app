<?php

namespace App\Services\Website;

use App\Models\Product\Attribute;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\Product\Subcategory;
use App\Models\Product\Tag;
use App\Models\ProductSales\SalesItem;
use App\Models\Web\CustomerAccount;
use App\Services\ProductSales\CustomerPrices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The sales catalogue on the website: categories, brands, search, filters with
 * counts, and prices (list price for everyone, the customer's own price when
 * signed in). Products without a list price are shown as "price on request".
 */
class SalesCatalogue
{
    public const PER_PAGE = 48;

    /** The products the website may show. */
    public static function query(): Builder
    {
        return SalesItem::query()->where('active', true)
            ->when(config('website.only_ticked'), fn ($q) => $q->where('on_website', true));
    }

    public static function slug(SalesItem $i): string
    {
        return $i->id.'-'.(Str::slug($i->name()) ?: 'product');
    }

    public static function visible(SalesItem $i): bool
    {
        return self::query()->whereKey($i->id)->exists();
    }

    /** Active sales categories with their active subcategories and product counts. */
    public static function tree(): Collection
    {
        $counts = self::query()->select('subcategory_id', DB::raw('count(*) as n'))->groupBy('subcategory_id')->pluck('n', 'subcategory_id');

        return Category::of('sales')->where('active', true)->orderBy('sort')
            ->with(['subcategories' => fn ($q) => $q->where('active', true)])->get()
            ->map(function (Category $c) use ($counts) {
                $c->setRelation('subcategories', $c->subcategories->each(fn ($s) => $s->n = (int) ($counts[$s->id] ?? 0))->filter(fn ($s) => $s->n > 0)->values());
                $c->n = $c->subcategories->sum('n');

                return $c;
            })->filter(fn ($c) => $c->n > 0)->values();
    }

    /** Brands that have products on the website: own and distributed first. */
    public static function brands(): Collection
    {
        $counts = self::query()->whereNotNull('brand_id')->select('brand_id', DB::raw('count(*) as n'))->groupBy('brand_id')->pluck('n', 'brand_id');

        return Brand::where('active', true)->whereKey($counts->keys())->get()
            ->each(fn ($b) => $b->n = (int) $counts[$b->id])
            ->sortBy(fn ($b) => [array_search($b->kind, Brand::KINDS, true), (int) $b->sort, Str::lower($b->name)])->values();
    }

    /** Words of a search, folded. */
    public static function words(?string $q): array
    {
        return array_values(array_filter(preg_split('/\s+/', Str::lower(Str::ascii(trim((string) $q)))) ?: [], fn ($w) => $w !== ''));
    }

    /** Products matching every word somewhere (name, codes, EAN, description, search words, category). */
    public static function searchQuery(Builder $q, array $words): Builder
    {
        foreach ($words as $w) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $w).'%';
            $q->where(fn ($x) => $x->whereRaw('lower(brand) like ?', [$like])->orWhereRaw('lower(type) like ?', [$like])
                ->orWhereRaw('lower(number) like ?', [$like])->orWhereRaw('lower(manufacturer_code) like ?', [$like])
                ->orWhereRaw('lower(ean) like ?', [$like])->orWhereRaw('lower(description) like ?', [$like])
                ->orWhereRaw('lower(search_words) like ?', [$like])->orWhereRaw('lower(category) like ?', [$like]));
        }

        return $q;
    }

    /** Ranking: exact type or code first, then names that start with the words. */
    public static function score(SalesItem $i, array $words): int
    {
        $s = 0;
        $type = Str::lower(Str::ascii((string) $i->type));
        $codes = array_map(fn ($c) => Str::lower((string) $c), [$i->number, $i->manufacturer_code, $i->ean]);
        $brand = Str::lower(Str::ascii((string) $i->brand));
        foreach ($words as $w) {
            $s += match (true) {
                in_array($w, $codes, true), $type === $w => 100,
                str_starts_with($type, $w) => 60,
                str_starts_with($brand, $w) => 40,
                str_contains($type, $w) => 30,
                default => 10,
            };
        }

        return $s;
    }

    /**
     * One listing (a category, a brand, new items or a search) with its filters.
     * $f: q, cat, sub, brand (slug), brands[], opts[], tags[], stock, min, max, sort, page.
     */
    public static function listing(array $f, ?CustomerAccount $who, string $locale): array
    {
        $words = self::words($f['q'] ?? null);
        $base = self::query()->with(['options', 'tags'])
            ->when($f['cat'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
            ->when($f['sub'] ?? null, fn ($q, $s) => $q->where('subcategory_id', $s))
            ->when($f['brand_id'] ?? null, fn ($q, $b) => $q->where('brand_id', $b))
            ->when($f['tag_id'] ?? null, fn ($q, $t) => $q->whereHas('tags', fn ($x) => $x->whereKey($t)));
        if ($words) {
            self::searchQuery($base, $words);
        }
        $all = $base->get();
        foreach ($all as $i) {
            $i->price_cents = CustomerPrices::priceCents($who, $i);
            $i->score = $words ? self::score($i, $words) : 0;
            $i->opt_ids = $i->options->pluck('id')->all();
            $i->tag_ids = $i->tags->pluck('id')->all();
        }

        $brands = array_filter(array_map('intval', (array) ($f['brands'] ?? [])));
        $opts = array_filter(array_map('intval', (array) ($f['opts'] ?? [])));
        $tags = array_filter(array_map('intval', (array) ($f['tags'] ?? [])));
        $subs = array_filter(array_map('intval', (array) ($f['subs'] ?? [])));
        $stock = ! empty($f['stock']);
        $min = isset($f['min']) && $f['min'] !== '' ? (int) round((float) $f['min'] * 100) : null;
        $max = isset($f['max']) && $f['max'] !== '' ? (int) round((float) $f['max'] * 100) : null;

        $optAttr = $opts ? DB::table('product_attribute_options')->whereIn('id', $opts)->pluck('attribute_id', 'id')->all() : [];
        $byAttr = [];
        foreach ($opts as $o) {
            $byAttr[$optAttr[$o] ?? 0][] = $o;
        }
        $tests = [
            'subs' => fn ($i) => ! $subs || in_array($i->subcategory_id, $subs, true),
            'brands' => fn ($i) => ! $brands || in_array($i->brand_id, $brands, true),
            'opts' => fn ($i) => collect($byAttr)->every(fn ($ids) => (bool) array_intersect($ids, $i->opt_ids)),
            'tags' => fn ($i) => ! $tags || (bool) array_intersect($tags, $i->tag_ids),
            'stock' => fn ($i) => ! $stock || $i->stock_qty > 0,
            'price' => fn ($i) => ($min === null || ($i->price_cents && $i->price_cents >= $min)) && ($max === null || ($i->price_cents && $i->price_cents <= $max)),
        ];
        // Each filter's counts ignore that filter itself, so a ticked box never shows 0.
        $without = fn (string $skip) => $all->filter(fn ($i) => collect($tests)->except($skip)->every(fn ($t) => $t($i)));
        $list = $all->filter(fn ($i) => collect($tests)->every(fn ($t) => $t($i)));

        $sort = $f['sort'] ?? ($words ? 'rel' : 'rel');
        $list = match ($sort) {
            'pa' => $list->sortBy(fn ($i) => $i->price_cents ?: PHP_INT_MAX),
            'pd' => $list->sortByDesc('price_cents'),
            'az' => $list->sortBy(fn ($i) => Str::lower($i->name())),
            'new' => $list->sortByDesc('id'),
            default => $words ? $list->sortByDesc(fn ($i) => [$i->score, -$i->id]) : $list->sortBy(fn ($i) => [$i->stock_qty > 0 ? 0 : 1, Str::lower($i->name())]),
        };

        $page = max(1, (int) ($f['page'] ?? 1));
        $paged = new LengthAwarePaginator($list->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(), $list->count(), self::PER_PAGE, $page);

        // facets
        $fBrands = $without('brands')->groupBy('brand_id')->filter(fn ($g, $k) => $k)->map->count();
        $brandRows = Brand::whereKey($fBrands->keys()->merge($brands))->get()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'n' => (int) ($fBrands[$b->id] ?? 0), 'on' => in_array($b->id, $brands, true)])
            ->sortBy([['n', 'desc'], ['name', 'asc']])->values();
        $fSubs = $without('subs')->groupBy('subcategory_id')->map->count();
        $subRows = Subcategory::whereKey($fSubs->keys()->merge($subs))->where('active', true)->orderBy('sort')->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->label($locale), 'n' => (int) ($fSubs[$s->id] ?? 0), 'on' => in_array($s->id, $subs, true)]);
        $optCount = [];
        foreach ($without('opts') as $i) {
            foreach ($i->opt_ids as $o) {
                $optCount[$o] = ($optCount[$o] ?? 0) + 1;
            }
        }
        $attrRows = Attribute::of('sales')->orderBy('sort')->with('options')->get()->map(fn ($a) => [
            'id' => $a->id, 'name' => $a->label($locale),
            'options' => $a->options->filter(fn ($o) => isset($optCount[$o->id]) || in_array($o->id, $opts, true))
                ->map(fn ($o) => ['id' => $o->id, 'name' => $o->setRelation('attribute', $a)->display($locale), 'n' => $optCount[$o->id] ?? 0, 'on' => in_array($o->id, $opts, true)])->values(),
        ])->filter(fn ($a) => $a['options']->isNotEmpty())->values();
        $tagCount = [];
        foreach ($without('tags') as $i) {
            foreach ($i->tag_ids as $t) {
                $tagCount[$t] = ($tagCount[$t] ?? 0) + 1;
            }
        }
        $tagRows = Tag::of('sales')->where('active', true)->orderBy('sort')->get()->filter(fn ($t) => isset($tagCount[$t->id]) || in_array($t->id, $tags, true))
            ->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->label($locale), 'n' => $tagCount[$t->id] ?? 0, 'on' => in_array($t->id, $tags, true)])->values();
        $prices = $without('price')->pluck('price_cents')->filter();

        return [
            'items' => $paged, 'total' => $list->count(), 'words' => $words, 'sort' => $sort,
            'facets' => [
                'subs' => $subRows->count() > 1 || $subs ? $subRows : collect(),
                'brands' => $brandRows, 'attrs' => $attrRows, 'tags' => $tagRows,
                'stock' => ['n' => $without('stock')->where('stock_qty', '>', 0)->count(), 'on' => $stock],
                'price' => ['min' => $prices->min(), 'max' => $prices->max(), 'from' => $f['min'] ?? '', 'to' => $f['max'] ?? ''],
            ],
            'active' => $brands || $opts || $tags || $subs || $stock || $min !== null || $max !== null,
        ];
    }

    /** Suggestions while typing: products, categories and brands. */
    public static function suggest(string $q, ?CustomerAccount $who, string $locale): array
    {
        $words = self::words($q);
        if (! $words || mb_strlen(implode('', $words)) < 2) {
            return ['products' => [], 'categories' => [], 'brands' => [], 'total' => 0];
        }
        $found = self::searchQuery(self::query(), $words)->limit(400)->get();
        $products = $found->sortByDesc(fn ($i) => self::score($i, $words))->take(6)->values()
            ->map(fn ($i) => ['name' => $i->name(), 'code' => $i->manufacturer_code ?: $i->number, 'price' => CustomerPrices::priceCents($who, $i),
                'photo' => $i->photo_path ? route('site.sales.photo', ['item' => $i->id, 'w' => 480]).'?v='.substr(md5($i->photo_path), 0, 8) : null,
                'url' => route('site.sales.product', self::slug($i))]);
        $needle = Str::lower(Str::ascii(implode(' ', $words)));
        $cats = Subcategory::where('active', true)->whereHas('category', fn ($c) => $c->of('sales')->where('active', true))->with('category')->get()
            ->filter(fn ($s) => str_contains(Str::lower(Str::ascii($s->label($locale))), $needle) || str_contains(Str::lower(Str::ascii($s->category->label($locale))), $needle))
            ->take(4)->map(fn ($s) => ['name' => $s->category->label($locale).($s->slug !== 'general' ? ' › '.$s->label($locale) : ''),
                'url' => route('site.sales.category', array_filter([$s->category->slug, $s->slug !== 'general' ? $s->slug : null]))])->values();
        $brands = self::brands()->filter(fn ($b) => str_contains(Str::lower(Str::ascii($b->name)), $needle))->take(4)
            ->map(fn ($b) => ['name' => $b->name, 'n' => $b->n, 'url' => route('site.sales.brand', $b->slug)])->values();

        return ['products' => $products, 'categories' => $cats, 'brands' => $brands, 'total' => $found->count()];
    }

    /** Everything the product page shows. */
    public static function detail(SalesItem $i, ?CustomerAccount $who, string $locale): array
    {
        $i->loadMissing(['productCategory', 'productSubcategory', 'options.attribute', 'tags', 'documents', 'brandRef']);
        $related = self::query()->where('subcategory_id', $i->subcategory_id)->whereKeyNot($i->id)
            ->orderByRaw('case when brand_id = ? then 0 else 1 end', [(int) $i->brand_id])->orderBy('type')->limit(8)->get();

        return [
            'item' => $i,
            'price' => CustomerPrices::priceCents($who, $i),
            'discount' => CustomerPrices::discountBp($who, $i),
            'options' => $i->options->map(fn ($o) => [$o->attribute?->label($locale), $o->display($locale)])->values()->all(),
            'tags' => $i->tags->filter->active->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->label($locale)])->values()->all(),
            'documents' => $i->documents->map(fn ($d) => ['id' => $d->id, 'kind' => $d->kind, 'name' => $d->name])->values()->all(),
            'related' => $related->each(fn ($r) => $r->price_cents = CustomerPrices::priceCents($who, $r)),
        ];
    }

    /** Web-sized photo (same rules as the dry-hire photos). */
    public static function photoFile(SalesItem $i, int $width): ?string
    {
        return Photos::web($i->photo_path, 'sales-'.$i->id, $width);
    }
}
