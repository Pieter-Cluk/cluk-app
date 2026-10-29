<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A guest a member (or CLUK) expects, or a CLUK notice: shown on the entrance screen on that day. */
#[Fillable(['kind', 'member_id', 'user_id', 'date', 'time', 'name', 'company', 'on_screen', 'note', 'cancelled_at'])]
class Guest extends Model
{
    protected $table = 'afrit6_guests';

    protected function casts(): array
    {
        return ['date' => 'date', 'on_screen' => 'boolean', 'cancelled_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }
}
