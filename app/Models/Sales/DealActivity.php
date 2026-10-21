<?php

namespace App\Models\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line in the deal's history: a note, call, email, meeting or a stage change. */
#[Fillable(['deal_id', 'user_id', 'kind', 'body'])]
class DealActivity extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = ['note', 'call', 'email', 'meeting'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
