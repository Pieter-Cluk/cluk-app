<?php

namespace App\Models\Factory;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A crew task for a Cluk Factory booking. */
#[Fillable(['booking_id', 'kind', 'title', 'due_at', 'assignee_id', 'done_at', 'done_by_id', 'note'])]
class Task extends Model
{
    protected $table = 'factory_tasks';

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('done_at');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_at->isPast();
    }

    /** Standard tasks are named in the reader's language. */
    public function label(): string
    {
        return $this->kind ? __('factory.task_kinds.'.$this->kind) : (string) $this->title;
    }

    /** May $me act on it: nobody has it yet, or it is mine, or I am above the person who has it. */
    public function canBeMovedBy(User $me): bool
    {
        return $this->assignee === null || $me->canReassignFrom($this->assignee);
    }
}
