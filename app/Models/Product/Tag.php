<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A short managed label (Featured, New, Outdoor…), ticked per item. */
#[Fillable(['slug', 'name', 'sort', 'active'])]
class Tag extends Model
{
    use Translated;

    protected $table = 'product_tags';

    protected function casts(): array
    {
        return ['name' => 'array', 'active' => 'boolean'];
    }
}
