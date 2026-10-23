<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Level 1 of the catalogue (Audio, Lighting…). The website links to the slug, never to the name. */
#[Fillable(['code', 'slug', 'old_slugs', 'name', 'sort', 'active'])]
class Category extends Model
{
    use Translated;

    protected $table = 'product_categories';

    protected function casts(): array
    {
        return ['name' => 'array', 'old_slugs' => 'array', 'active' => 'boolean'];
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class)->orderBy('sort')->orderBy('id');
    }
}
