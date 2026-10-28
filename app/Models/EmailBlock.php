<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An email address that bounced: no mails go to it while it is active (not cleared). */
#[Fillable(['email', 'reason', 'detail', 'subject', 'bounced_at', 'notify_user_id', 'cleared_at', 'cleared_by_id'])]
class EmailBlock extends Model
{
    protected function casts(): array
    {
        return ['bounced_at' => 'datetime', 'cleared_at' => 'datetime'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('cleared_at');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by_id');
    }
}
