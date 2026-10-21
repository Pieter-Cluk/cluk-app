<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message on a request, from staff (user_id) or from the member (member_id). */
#[Fillable(['request_id', 'user_id', 'member_id', 'body'])]
class RequestMessage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'afrit6_request_messages';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fromStaff(): bool
    {
        return $this->user_id !== null;
    }
}
