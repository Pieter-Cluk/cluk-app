<?php

namespace App\Models\Equipment;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rental product. Serial items have assets (one per piece, own QR label);
 * counted items (cables, safeties) only have a stock quantity.
 * A package is sold as one line but made of items; a case holds items.
 */
#[Fillable([
    'number', 'kind', 'category', 'subcategory', 'brand', 'type', 'name', 'unit', 'serial', 'stock_qty',
    'day_price_cents', 'price_unit', 'catalog_price_cents', 'weight_kg', 'width_m', 'length_m', 'height_m', 'origin', 'hs_code',
    'descriptions', 'photo_path', 'in_search', 'active',
])]
class Item extends Model
{
    protected $table = 'equipment_items';

    public const KINDS = ['item', 'package', 'case', 'service'];

    public const PRICE_UNITS = ['month', 'half_day', 'day', 'hour', 'coli', 'pallet', 'event_day', 'event'];

    protected function casts(): array
    {
        return [
            'serial' => 'boolean', 'in_search' => 'boolean', 'active' => 'boolean', 'descriptions' => 'array',
            'stock_qty' => 'integer', 'day_price_cents' => 'integer', 'catalog_price_cents' => 'integer',
            'weight_kg' => 'float', 'width_m' => 'float', 'length_m' => 'float', 'height_m' => 'float',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ItemPhoto::class)->orderBy('position')->orderBy('id');
    }

    /** The first photo is the main one (shown in lists and on quotes). */
    public function syncMainPhoto(): void
    {
        $this->forceFill(['photo_path' => $this->photos()->value('path')])->save();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class)->orderBy('code');
    }

    /** Accessories that go with every piece (kit) or what a package/case contains. */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'equipment_components', 'parent_id', 'child_id')->withPivot(['qty', 'role'])->orderBy('number');
    }

    public function kit(): BelongsToMany
    {
        return $this->children()->wherePivot('role', 'kit');
    }

    public function contents(): BelongsToMany
    {
        return $this->children()->wherePivot('role', 'content');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function volume(): ?float
    {
        return $this->width_m && $this->length_m && $this->height_m ? round($this->width_m * $this->length_m * $this->height_m, 4) : null;
    }

    /** Description in the wanted language, falling back to English, then to any. */
    public function description(?string $locale = null): ?string
    {
        $d = array_filter($this->descriptions ?? []);

        return $d[$locale ?? app()->getLocale()] ?? $d['en'] ?? (reset($d) ?: null);
    }

    /** Pieces we own: assets that are not retired, or the counted stock. */
    public function totalQty(): int
    {
        if (in_array($this->kind, ['package', 'service'], true)) {
            return 0;
        }

        return $this->serial ? $this->assets->where('status', '!=', Asset::RETIRED)->count() : $this->stock_qty;
    }

    /** Pieces in the warehouse and rentable now (planning per date comes with order allocation). */
    public function availableQty(): int
    {
        if ($this->kind === 'service') {
            return 0;
        }
        if ($this->kind === 'package') {
            $min = null;
            foreach ($this->contents as $c) {
                $n = intdiv($c->availableQty(), max(1, (int) $c->pivot->qty));
                $min = $min === null ? $n : min($min, $n);
            }

            return (int) $min;
        }

        return $this->serial ? $this->assets->where('status', Asset::AVAILABLE)->count() : $this->stock_qty;
    }

    /** Short prefix for asset codes: "LT" from "LT-SPIIDER", else the first letters. */
    public function codePrefix(): string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', explode('-', $this->number)[0]));

        return substr($p !== '' ? $p : 'EQ', 0, 4);
    }

    /** Weight of a package or case = own weight + contents. */
    public function totalWeight(): ?float
    {
        $w = (float) ($this->weight_kg ?? 0);
        foreach ($this->contents as $c) {
            $w += (float) ($c->weight_kg ?? 0) * (int) $c->pivot->qty;
        }

        return $w > 0 ? round($w, 2) : null;
    }
}
