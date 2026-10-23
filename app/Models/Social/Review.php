<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'external_id', 'author', 'rating', 'text', 'reply', 'reviewed_at'])]
class Review extends Model
{
    protected $table = 'social_reviews';

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
