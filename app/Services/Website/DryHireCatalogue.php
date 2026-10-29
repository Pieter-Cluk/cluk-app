<?php

namespace App\Services\Website;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Product\Attribute;
use App\Models\Product\Category;
use App\Models\Product\Subcategory;
use App\Models\Product\Tag;
use App\Models\Sales\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What the public website shows of the dry-hire list: the same items as the
 * platform (active items and packages with a place in the categories), with
 * names, prices, attributes, tags and photos, in the visitor's language.
 */
class DryHireCatalogue
{
    /** The items the website may show. */
    public static function query(): Builder
    {
        return Item::query()->active()
            ->whereIn('kind', config('website.dryhire_kinds'))
            ->whereNotNull('subcategory_id')
            ->whereHas('productSubcategory', fn ($q) => $q->where('active', true))
            ->when(config('website.only_ticked'), fn ($q) => $q->where('on_website', true));
    }

    public static function visible(Item $item): bool
    {
        return self::query()->whereKey($item->id)->exists();
    }

    public static function slug(Item $item): string
    {
        return $item->id.'-'.(Str::slug((string) $item->name) ?: 'item');
    }

    /** Compact data for the catalogue page, cached per language until something changes. */
    public static function data(string $locale): array
    {
        $stamp = implode('|', [
            Item::max('updated_at'), Item::count(), DB::table('equipment_item_options')->count(), DB::table('equipment_item_tag')->count(),
            Category::max('updated_at'), Subcategory::max('updated_at'), Attribute::max('updated_at'), Tag::max('updated_at'),
            DB::table('equipment_assets')->max('updated_at'), DB::table('equipment_assets')->count(),
            json_encode([config('website.only_ticked'), config('rental.factor')]),
        ]);

        return Cache::remember('site.dryhire.'.$locale.'.'.md5($stamp), 600, fn () => self::build($locale));
    }

    private static function build(string $locale): array
    {
        $items = self::query()
            ->with(['options', 'tags'])
            ->withCount(['assets as pieces' => fn ($q) => $q->where('status', '!=', Asset::RETIRED)])
            ->orderBy('name')->get();

        $cats = Category::of('dryhire')->where('active', true)->orderBy('sort')->get();
        $subs = Subcategory::where('active', true)->with('attrs:id')->orderBy('sort')->get();
        $attrs = Attribute::of('dryhire')->with(['options' => fn ($q) => $q->where('active', true)])->orderBy('sort')->get();

        $rows = $items->map(function (Item $i) {
            $qty = $i->kind === 'package' ? 0 : ($i->serial ? (int) $i->pieces : (int) $i->stock_qty);

            return array_filter([
                'id' => $i->id,
                'n' => $i->number,
                'nm' => (string) $i->name,
                'b' => (string) $i->brand,
                't' => (string) $i->type,
                'c' => $i->category_id,
                's' => $i->subcategory_id,
                'p' => (int) $i->day_price_cents,
                'u' => $i->price_unit && $i->price_unit !== 'day' ? $i->price_unit : null,
                'q' => $qty,
                'k' => $i->kind === 'package' ? 'p' : null,
                'ph' => $i->photo_path ? substr(md5($i->photo_path), 0, 8) : null,
                'o' => $i->options->pluck('id')->all(),
                'g' => $i->tags->pluck('id')->all(),
                'w' => trim((string) $i->search_words) ?: null,
                'sl' => self::slug($i),
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);
        })->values()->all();

        $used = collect($rows);
        // Fields on the item cards that are not offered as a filter (they still show on the product page).
        $noFilter = $attrs->whereIn('key', (array) config('website.dryhire_no_filter', []))->pluck('id');

        return [
            'items' => $rows,
            'cats' => $cats->filter(fn ($c) => $used->contains('c', $c->id))->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'slug' => $c->slug, 'name' => $c->label($locale)])->values()->all(),
            'subs' => $subs->filter(fn ($s) => $used->contains('s', $s->id))->map(fn ($s) => ['id' => $s->id, 'c' => $s->category_id, 'slug' => $s->slug, 'name' => $s->label($locale),
                'a' => $s->attrs->pluck('id')->diff($noFilter)->values()->all()])->values()->all(),
            'attrs' => $attrs->map(fn ($a) => ['id' => $a->id, 'key' => $a->key, 'name' => $a->label($locale),
                'o' => $a->options->map(fn ($o) => ['id' => $o->id, 'l' => $o->setRelation('attribute', $a)->display($locale)])->values()->all()])->values()->all(),
            'tags' => Tag::of('dryhire')->where('active', true)->orderBy('sort')->get()->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->label($locale)])->values()->all(),
            'factor' => config('rental.factor'),
            'stats' => [
                'products' => count($rows),
                'brands' => $used->pluck('b')->filter()->unique()->count(),
                'departments' => $used->pluck('c')->unique()->count(),
                'pieces' => $used->sum('q'),
            ],
        ];
    }

    /** Everything the product panel and the product page show. */
    public static function detail(Item $item, string $locale): array
    {
        $item->loadMissing(['productCategory', 'productSubcategory', 'options.attribute', 'tags', 'photos', 'contents']);
        $qty = $item->kind === 'package' ? 0 : $item->totalQty();
        $fmt = fn (?float $v, int $d = 2) => $v === null ? null : rtrim(rtrim(number_format($v, $d, $locale === 'en' ? '.' : ',', ''), '0'), '.,');
        $size = $item->width_m && $item->length_m && $item->height_m ? $fmt($item->width_m).' × '.$fmt($item->length_m).' × '.$fmt($item->height_m).' m' : null;
        $related = self::query()->where('subcategory_id', $item->subcategory_id)->whereKeyNot($item->id)
            ->orderByRaw('case when brand = ? then 0 else 1 end', [(string) $item->brand])->orderBy('name')->limit(6)->get(['id', 'name', 'day_price_cents']);

        return [
            'id' => $item->id,
            'number' => $item->number,
            'name' => (string) $item->name,
            'brand' => (string) $item->brand,
            'type' => (string) $item->type,
            'category' => $item->productCategory?->label($locale),
            'subcategory' => $item->productSubcategory?->label($locale),
            'description' => $item->description($locale),
            'price' => (int) $item->day_price_cents,
            'qty' => $qty,
            'weight' => $item->weight_kg ? $fmt($item->weight_kg, 1).' kg' : null,
            'size' => $size,
            'options' => $item->options->map(fn ($o) => [$o->attribute?->label($locale), $o->display($locale)])->values()->all(),
            'tags' => $item->tags->map(fn ($t) => $t->label($locale))->values()->all(),
            'photos' => $item->photos->pluck('id')->map(fn ($id) => route('site.photo', ['item' => $item->id, 'w' => 1200, 'p' => $id]))->values()->all(),
            'contents' => $item->kind === 'package' ? $item->contents->map(fn ($c) => [(int) $c->pivot->qty, (string) $c->name])->values()->all() : [],
            'related' => $related->map(fn ($r) => ['id' => $r->id, 'name' => (string) $r->name, 'p' => (int) $r->day_price_cents])->values()->all(),
            'url' => route('site.dryhire.item', self::slug($item)),
        ];
    }

    /**
     * A web-sized copy of a photo (JPEG, given width), made once and kept.
     * Falls back to the original when the server cannot resize.
     */
    public static function photoFile(Item $item, int $width, ?int $photoId = null): ?string
    {
        $disk = Storage::disk('local');
        $path = $photoId ? $item->photos()->whereKey($photoId)->value('path') : $item->photo_path;
        if (! $path || ! $disk->exists($path)) {
            return null;
        }
        $src = $disk->path($path);
        if (! function_exists('imagecreatefromstring')) {
            return $src;
        }
        $rel = 'site-photos/'.$item->id.'-'.$width.'-'.substr(md5($path.'|'.filemtime($src)), 0, 12).'.jpg';
        if ($disk->exists($rel)) {
            return $disk->path($rel);
        }
        $img = @imagecreatefromstring((string) file_get_contents($src));
        if (! $img) {
            return $src;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $nw = min($width, $w);
        $nh = max(1, (int) round($h * $nw / max(1, $w)));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // transparent PNGs on white
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $disk->makeDirectory('site-photos');
        imagejpeg($out, $disk->path($rel), 82);
        imagedestroy($img);
        imagedestroy($out);

        return $disk->path($rel);
    }

    /** Factor for the days of use, the same rule as on quotes. */
    public static function factor(int $days): float
    {
        return Quote::factorFor($days);
    }
}
