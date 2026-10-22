<?php

namespace App\Models\ProductSales;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** A product we sell. No assets: stock is a number. */
#[Fillable(['number', 'brand', 'type', 'description', 'category', 'unit', 'list_price_cents', 'stock_qty', 'min_stock', 'photo_path', 'active', 'ean'])]
class SalesItem extends Model
{
    public const CATEGORIES = ['Audio', 'Light', 'Video', 'Connectors', 'Rigging', 'Consumables', 'Cases', 'Other'];

    public const UNITS = ['piece', 'set', 'roll', 'box', 'meter'];

    protected function casts(): array
    {
        return ['list_price_cents' => 'integer', 'stock_qty' => 'integer', 'min_stock' => 'integer', 'active' => 'boolean'];
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
