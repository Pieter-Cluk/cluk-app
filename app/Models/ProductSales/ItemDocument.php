<?php

namespace App\Models\ProductSales;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A PDF of a sales item: manual, certificate, CE declaration or datasheet. */
#[Fillable(['sales_item_id', 'kind', 'name', 'path', 'size', 'user_id'])]
class ItemDocument extends Model
{
    protected $table = 'sales_item_documents';

    public const KINDS = ['manual', 'certificate', 'ce', 'datasheet'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesItem::class, 'sales_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
