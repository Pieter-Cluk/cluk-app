<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A one-time sign-in code by email for staff without Microsoft 365. Only a hash is kept. */
#[Fillable(['user_id', 'code_hash', 'tries', 'expires_at', 'used_at', 'ip'])]
class StaffLoginCode extends Model
{
    public const MINUTES = 10;

    public const MAX_TRIES = 5;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'tries' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
