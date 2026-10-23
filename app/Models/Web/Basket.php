<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A customer's basket on the website: "dryhire" (sent as a request) or "sales" (ordered on invoice). */
#[Fillable(['account_id', 'kind', 'first_day', 'last_day'])]
class Basket extends Model
{
    public const DRYHIRE = 'dryhire';

    public const SALES = 'sales';

    protected $table = 'customer_baskets';

    protected function casts(): array
    {
        return ['first_day' => 'date', 'last_day' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class, 'account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BasketLine::class, 'basket_id');
    }
}
