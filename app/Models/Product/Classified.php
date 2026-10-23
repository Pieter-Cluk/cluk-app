<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product with a place in a catalogue (dry-hire item or sales product):
 * category, subcategory, attribute values, tags and hidden search words.
 * The product number is never touched.
 */
trait Classified
{
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function productSubcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class, 'subcategory_id');
    }

    /** "Black · NH34 · 2 m" style labels for lists and searches. */
    public function optionLabels(): array
    {
        return $this->options->sortBy(fn ($o) => [$o->attribute?->sort, $o->sort])->map(fn ($o) => $o->display())->values()->all();
    }
}
