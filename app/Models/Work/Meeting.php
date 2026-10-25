<?php

namespace App\Models\Work;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Meeting notes: pasted text, a summary and the tasks that came out of it. */
#[Fillable(['title', 'held_on', 'kind', 'company_id', 'created_by_id', 'attendees', 'notes', 'summary', 'decisions', 'proposals', 'analysed_by', 'analysed_at'])]
class Meeting extends Model
{
    public const KINDS = ['internal', 'customer'];

    protected function casts(): array
    {
        return ['held_on' => 'date', 'attendees' => 'array', 'decisions' => 'array', 'proposals' => 'array', 'analysed_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class)->orderBy('status')->orderByRaw('due_on is null')->orderBy('due_on');
    }

    /** @return Collection<int, User> */
    public function attendeeUsers(): Collection
    {
        return User::whereIn('id', $this->attendees ?? [])->orderBy('first_name')->get();
    }

    /** Who may see it: who wrote it, who was there, who got a task from it, or who may see all. */
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        if ($u->hasRight('meetings.see_all')) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('created_by_id', $u->id)->orWhereJsonContains('attendees', $u->id)
            ->orWhereHas('todos', fn ($t) => $t->where('assignee_id', $u->id)));
    }

    public function canBeSeenBy(User $u): bool
    {
        return static::whereKey($this->id)->visibleTo($u)->exists();
    }

    public function canBeEditedBy(User $u): bool
    {
        return $this->created_by_id === $u->id || in_array($u->id, $this->attendees ?? [], true) || $u->hasRight('meetings.see_all');
    }
}
