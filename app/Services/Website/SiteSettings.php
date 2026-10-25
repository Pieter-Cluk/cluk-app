<?php

namespace App\Services\Website;

use App\Models\Equipment\Item;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\ProductSales\SalesItem;
use App\Models\Website\Setting;
use App\Models\Website\Slide;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * What Media › Website sets, read by the website pages. Nothing set = the
 * website keeps its built-in texts and pictures.
 */
class SiteSettings
{
    /** Texts per page that can be changed, per language (fallback: the built-in translation key). */
    public const TEXTS = [
        'home.hero_title' => 'site.home.hero_title', 'home.hero_text' => 'site.home.hero_text',
        'home.news_title' => 'site.home.news_title', 'home.news_text' => 'site.home.news_text',
        'dryhire.eyebrow' => 'site.dryhire.eyebrow', 'dryhire.title' => 'site.dryhire.title',
        'sales.title' => 'site.sales.title',
    ];

    /** The homepage tiles, with their built-in texts and picture. */
    public const TILES = [
        'dryhire' => ['t' => 'site.home.dryhire_t', 'c' => 'site.home.dryhire_c', 'img' => 'images/site/dryhire.jpg'],
        'sales' => ['t' => 'site.home.sales_t', 'c' => 'site.home.sales_c', 'img' => 'images/site/sales.jpg'],
        'factory' => ['t' => 'site.home.factory_t', 'c' => 'site.home.factory_c', 'img' => 'images/site/factory.jpg'],
        'afrit6' => ['t' => 'site.home.afrit6_t', 'c' => 'site.home.afrit6_c', 'img' => 'images/site/afrit6.jpg'],
    ];

    private static ?array $all = null;

    public static function flush(): void
    {
        self::$all = null;
        Cache::forget('web:settings');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::$all ??= Cache::remember('web:settings', 600, fn () => Setting::pluck('value', 'key')->all());

        return self::$all[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, ?int $by = null): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by_id' => $by]);
        self::flush();
    }

    /** A page text in the visitor's language: own text, else the own English text, else the built-in one. */
    public static function text(string $key, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $v = self::get($key);

        return (is_array($v) ? (($v[$locale] ?? null) ?: ($v['en'] ?? null)) : null) ?: (string) __(self::TEXTS[$key] ?? $key, [], $locale);
    }

    /** A homepage tile: [text, call to action, picture URL]. */
    public static function tile(string $tile): array
    {
        $d = self::TILES[$tile];
        $v = self::get('home.tile.'.$tile, []);
        $l = app()->getLocale();
        $pick = fn (string $f, string $fallback) => ($v[$f][$l] ?? null) ?: (($v[$f]['en'] ?? null) ?: __($fallback));

        return [
            'text' => $pick('text', $d['t']), 'cta' => $pick('cta', $d['c']),
            'img' => ! empty($v['image']) && Storage::disk('local')->exists($v['image']) ? route('site.webmedia', ['f' => basename($v['image'])]) : asset($d['img']),
        ];
    }

    /** Popular searches under the dry-hire search bar. */
    public static function popular(): array
    {
        $v = self::get('dryhire.popular');

        return is_array($v) && $v ? array_values($v) : config('website.popular');
    }

    public static function social(string $network): string
    {
        return (string) (self::get('general.social')[$network] ?? null ?: config('website.social.'.$network));
    }

    /** Slides shown today in a place, in order. */
    public static function slides(string $place): Collection
    {
        return Slide::where('place', $place)->live()->orderBy('position')->orderBy('id')->get();
    }

    public static function mediaUrl(?string $path): ?string
    {
        return $path ? route('site.webmedia', ['f' => basename($path)]) : null;
    }

    /** Where a click goes; null = nowhere. */
    public static function link(string $type, ?string $value): ?string
    {
        $value = trim((string) $value);
        $app = rtrim(config('app.url'), '/');

        return match ($type) {
            'page' => match ($value) {
                'home' => route('site.home'), 'dryhire' => route('site.dryhire'), 'sales' => route('site.sales'), 'sales_new' => route('site.sales.new'),
                'brands' => route('site.sales.brands'), 'news' => route('site.news'), 'factory' => $app.'/book?lang='.app()->getLocale(), 'afrit6' => $app.'/afrit6',
                'account' => route('site.account'), 'register' => route('site.account.register'), default => null,
            },
            'dryhire_item' => ($i = Item::where('number', $value)->first()) && DryHireCatalogue::visible($i) ? route('site.dryhire.item', DryHireCatalogue::slug($i)) : null,
            'dryhire_cat' => $value !== '' ? route('site.dryhire').'?cat='.rawurlencode($value) : null,
            'sales_item' => ($s = SalesItem::where('number', $value)->first()) ? route('site.sales.product', SalesCatalogue::slug($s)) : null,
            'sales_cat' => Category::of(SalesItem::CATALOGUE)->where('slug', $value)->exists() ? route('site.sales.category', $value) : null,
            'brand' => Brand::where('slug', $value)->exists() ? route('site.sales.brand', $value) : null,
            'url' => preg_match('#^https?://#i', $value) || str_starts_with($value, '/') ? $value : null,
            default => null,
        };
    }

    /** For the edit screen: does the link lead somewhere? */
    public static function linkOk(string $type, ?string $value): bool
    {
        return $type === 'none' || self::link($type, $value) !== null;
    }

    /** The file behind a website media link (pictures and videos are public: they are on the website). */
    public static function publicMedia(string $file): ?string
    {
        $path = 'website/'.basename($file);

        return Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }
}
