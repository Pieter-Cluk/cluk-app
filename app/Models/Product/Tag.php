<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A short managed label (Featured, New, Outdoor…), ticked per item. */
#[Fillable(['catalogue', 'slug', 'name', 'sort', 'active'])]
class Tag extends Model
{
    use Translated;

    protected $table = 'product_tags';

    /** The two catalogues: dry-hire (rental) and sales; each has its own lists. */
    public const CATALOGUES = ['dryhire', 'sales'];

    public function scopeOf($q, string $catalogue)
    {
        return $q->where('catalogue', $catalogue);
    }

    protected function casts(): array
    {
        return ['name' => 'array', 'active' => 'boolean'];
    }
}
