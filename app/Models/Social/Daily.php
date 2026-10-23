<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'date', 'followers', 'reach', 'views', 'engagements', 'clicks'])]
class Daily extends Model
{
    protected $table = 'social_daily';

    public $timestamps = false;
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
