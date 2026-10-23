<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Level 2: belongs to one category for good (moving it would break the website structure). */
#[Fillable(['category_id', 'code', 'slug', 'old_slugs', 'name', 'sort', 'active'])]
class Subcategory extends Model
{
    use Translated;

    protected $table = 'product_subcategories';

    protected function casts(): array
    {
        return ['name' => 'array', 'old_slugs' => 'array', 'active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The attributes that apply to items in this subcategory. */
    public function attrs(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_subcategory_attribute', 'subcategory_id', 'attribute_id')->orderBy('sort');
    }
}
