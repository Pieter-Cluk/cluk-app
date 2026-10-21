<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Who did what, when. Used for rights changes, door codes, approvals,
 * reassignments and everything sent to customers.
 */
#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'data'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function record(string $action, ?Model $subject = null, array $data = [], ?User $actor = null): self
    {
        return static::create([
            // Only staff are actors; a member's own action is recorded without one.
            'actor_id' => ($actor ?? Auth::guard('web')->user())?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'data' => $data ?: null,
        ]);
    }
}
