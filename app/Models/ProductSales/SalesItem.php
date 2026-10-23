<?php

namespace App\Models\ProductSales;

use App\Models\Company;
use App\Models\Product\AttributeOption;
use App\Models\Product\Brand;
use App\Models\Product\Classified;
use App\Models\Product\Subcategory;
use App\Models\Product\Tag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** A product we sell. No assets: stock is a number. */
#[Fillable(['number', 'brand', 'type', 'description', 'category', 'unit', 'list_price_cents', 'stock_qty', 'min_stock', 'photo_path', 'active', 'ean', 'on_website', 'category_id', 'subcategory_id', 'search_words', 'brand_id', 'manufacturer_code'])]
class SalesItem extends Model
{
    use Classified;

    public const CATALOGUE = 'sales';

    public const UNITS = ['piece', 'set', 'roll', 'box', 'meter'];

    protected function casts(): array
    {
        return ['list_price_cents' => 'integer', 'stock_qty' => 'integer', 'min_stock' => 'integer', 'active' => 'boolean', 'on_website' => 'boolean'];
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(AttributeOption::class, 'sales_item_options', 'sales_item_id', 'option_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'sales_item_tag', 'sales_item_id', 'tag_id')->orderBy('sort');
    }

    /** Put the product in a sales subcategory; the English category name is also kept in the old text column. */
    public function placeIn(?Subcategory $sub): void
    {
        $sub?->loadMissing('category');
        $this->forceFill(['category_id' => $sub?->category_id, 'subcategory_id' => $sub?->id, 'category' => $sub?->category->label('en')]);
    }

    public function brandRef(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /** Keep the brand text and the brand list in step: a typed brand is found (or added) in the list. */
    protected static function booted(): void
    {
        static::saving(function (SalesItem $i) {
            if ($i->isDirty('brand')) {
                $b = Brand::findOrMake($i->brand);
                $i->brand_id = $b?->id;
                $i->brand = $b?->name;
            } elseif ($i->isDirty('brand_id')) {
                $i->brand = $i->brand_id ? Brand::whereKey($i->brand_id)->value('name') : null;
            }
        });
    }

    /** How a supplier knows this product: their article number, the manufacturer's code, our number. */
    public function orderCodes(?Company $supplier = null): string
    {
        $ref = $supplier ? $this->suppliers()->whereKey($supplier->id)->first()?->pivot->supplier_ref : null;

        return implode(' · ', array_filter([
            $ref ? __('psales.items.code_supplier', ['code' => $ref]) : null,
            $this->manufacturer_code ? __('psales.items.code_mfr', ['code' => $this->manufacturer_code]) : null,
            __('psales.items.code_ours', ['code' => $this->number]),
        ]));
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'sales_item_suppliers')->withPivot(['id', 'discount_bp', 'lead_days', 'preferred', 'supplier_ref'])->withTimestamps()->orderByDesc('sales_item_suppliers.preferred');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ItemDocument::class)->latest('id');
    }

    public function name(): string
    {
        return trim($this->brand.' '.$this->type);
    }

    /** The preferred supplier (or the first one). */
    public function preferred(): ?Company
    {
        return $this->suppliers->firstWhere('pivot.preferred', true) ?? $this->suppliers->first();
    }

    /** Purchase price per unit at a supplier: list price minus their discount. */
    public function costCents(?Company $supplier = null): int
    {
        $s = $supplier ? $this->suppliers->firstWhere('id', $supplier->id) : $this->preferred();
        if (! $s) {
            return 0;
        }

        return (int) round($this->list_price_cents * (1 - $s->pivot->discount_bp / 10000));
    }

    public function leadDays(): int
    {
        return (int) ($this->preferred()?->pivot->lead_days ?? 5);
    }

    /** Pieces promised to accepted sales that are not delivered yet. */
    public function reserved(?int $exceptQuoteId = null): int
    {
        return (int) SalesQuoteLine::where('sales_item_id', $this->id)
            ->whereHas('quote', fn ($q) => $q->whereIn('status', ['accepted', 'confirmed'])->when($exceptQuoteId, fn ($w) => $w->where('id', '!=', $exceptQuoteId)))
            ->sum('from_stock');
    }

    public function freeStock(?int $exceptQuoteId = null): int
    {
        return max(0, $this->stock_qty - $this->reserved($exceptQuoteId));
    }

    public static function nextNumber(): string
    {
        $last = DB::table('sales_items')->where('number', 'like', 'S-1%')->pluck('number')->map(fn ($n) => (int) substr($n, 2))->max() ?? 100000;

        return 'S-'.($last + 1);
    }
}
