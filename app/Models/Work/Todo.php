<?php

namespace App\Models\Work;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A task for one person: from a meeting, or added by hand. */
#[Fillable(['title', 'details', 'assignee_id', 'created_by_id', 'due_on', 'status', 'done_at', 'done_by_id', 'meeting_id', 'company_id'])]
class Todo extends Model
{
    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_on !== null && $this->due_on->lt(today());
    }

    public function canBeChangedBy(User $u): bool
    {
        return $this->assignee_id === $u->id || $this->created_by_id === $u->id || $u->hasRight('meetings.see_all');
    }
}
