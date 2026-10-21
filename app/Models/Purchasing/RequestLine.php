<?php

namespace App\Models\Purchasing;

use App\Models\Company;
use App\Models\ProductSales\SalesItem;
use App\Models\ProductSales\SalesQuoteLine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['purchase_request_id', 'sales_item_id', 'sales_quote_line_id', 'name', 'qty', 'purpose', 'chosen_company_id'])]
class RequestLine extends Model
{
    protected $table = 'purchase_request_lines';

    public const PURPOSES = ['sales', 'rental', 'consumables'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class, 'sales_item_id');
    }

    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(SalesQuoteLine::class, 'sales_quote_line_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class, 'purchase_request_line_id');
    }

    public function chosen(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'chosen_company_id');
    }

    /** The cheapest reply with a price (ties: the earliest date). */
    public function cheapest(): ?Reply
    {
        return $this->replies->filter(fn ($r) => $r->net_cents !== null)->sortBy([['net_cents', 'asc'], ['delivery_date', 'asc']])->first();
    }

    public function fastest(): ?Reply
    {
        return $this->replies->filter(fn ($r) => $r->delivery_date !== null && $r->net_cents !== null)->sortBy('delivery_date')->first();
    }
}
