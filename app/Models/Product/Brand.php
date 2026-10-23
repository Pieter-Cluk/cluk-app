<?php

namespace App\Models\Product;

use App\Models\AuditLog;
use App\Models\ProductSales\SalesItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A brand in the sales catalogue: our own products, brands we distribute, or
 * other brands we resell. The website links to the slug; a changed slug keeps
 * the old one so links keep working.
 */
#[Fillable(['name', 'slug', 'old_slugs', 'kind', 'website', 'logo_path', 'intro', 'active', 'sort'])]
class Brand extends Model
{
    use Translated;

    public const KINDS = ['own', 'distributed', 'other'];

    protected function casts(): array
    {
        return ['old_slugs' => 'array', 'intro' => 'array', 'active' => 'boolean', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        // A rename also changes the brand text on its products (shown in lists, quotes and orders).
        static::updated(function (Brand $b) {
            if ($b->wasChanged('name')) {
                SalesItem::where('brand_id', $b->id)->update(['brand' => $b->name]);
            }
        });
    }

    public function salesItems(): HasMany
    {
        return $this->hasMany(SalesItem::class);
    }

    /** The brand's text for the website, in a language (English as fallback). */
    public function introText(?string $locale = null): string
    {
        return $this->label($locale, 'intro');
    }

    /** By name (any case) or slug, current or old. */
    public static function findByName(?string $name): ?self
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return self::whereRaw('lower(name) = ?', [Str::lower($name)])->first()
            ?? self::where('slug', Str::slug($name))->first()
            ?? self::whereNotNull('old_slugs')->get()->first(fn (self $b) => in_array(Str::slug($name), $b->old_slugs ?? [], true));
    }

    /** The brand with this name, made (kind "other") when it is new: typing a new brand on a product adds it to the list. */
    public static function findOrMake(?string $name): ?self
    {
        $name = Str::limit(trim((string) $name), 80, '');
        if ($name === '') {
            return null;
        }
        if ($b = self::findByName($name)) {
            return $b;
        }
        $b = self::create(['name' => $name, 'slug' => self::freeSlug($name), 'kind' => 'other', 'active' => true]);
        AuditLog::record('products.brand_added', $b, ['name' => $b->name, 'from' => 'product']);

        return $b;
    }

    public static function freeSlug(string $name, ?int $except = null): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        for ($i = 2; self::where('slug', $slug)->when($except, fn ($q) => $q->whereKeyNot($except))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
