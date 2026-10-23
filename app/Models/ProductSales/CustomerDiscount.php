<?php

namespace App\Models\ProductSales;

use App\Models\Company;
use App\Models\Product\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The sales discount a customer gets on a brand (brand empty = every other brand). */
#[Fillable(['company_id', 'brand_id', 'discount_bp', 'note', 'set_by_id'])]
class CustomerDiscount extends Model
{
    protected $table = 'customer_brand_discounts';

    protected function casts(): array
    {
        return ['discount_bp' => 'integer'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_id');
    }
}
