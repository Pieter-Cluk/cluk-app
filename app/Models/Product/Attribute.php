<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A property with fixed choices (length, frequency band, colour…), shown only for its subcategories. */
#[Fillable(['catalogue', 'key', 'name', 'multiple', 'unit', 'sort'])]
class Attribute extends Model
{
    use Translated;

    protected $table = 'product_attributes';

    /** The two catalogues: dry-hire (rental) and sales; each has its own lists. */
    public const CATALOGUES = ['dryhire', 'sales'];

    public function scopeOf($q, string $catalogue)
    {
        return $q->where('catalogue', $catalogue);
    }

    protected function casts(): array
    {
        return ['name' => 'array', 'multiple' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort')->orderBy('id');
    }

    public function subcategories(): BelongsToMany
    {
        return $this->belongsToMany(Subcategory::class, 'product_subcategory_attribute', 'attribute_id', 'subcategory_id');
    }
}
