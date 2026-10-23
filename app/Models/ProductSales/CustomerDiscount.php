<?php

namespace App\Models\ProductSales;

use App\Models\Company;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a company's standard sales conditions:
 * - default %: no brand, category or product;
 * - brand % or sales-category %;
 * - fixed net price for one product (net_cents).
 */
#[Fillable(['company_id', 'brand_id', 'category_id', 'sales_item_id', 'discount_bp', 'net_cents', 'note', 'set_by_id'])]
class CustomerDiscount extends Model
{
    protected $table = 'customer_brand_discounts';

    protected function casts(): array
    {
        return ['discount_bp' => 'integer', 'net_cents' => 'integer'];
    }

    /** default, brand, category or product */
    public function scope(): string
    {
        return $this->sales_item_id ? 'product' : ($this->brand_id ? 'brand' : ($this->category_id ? 'category' : 'default'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class, 'sales_item_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_id');
    }
}
