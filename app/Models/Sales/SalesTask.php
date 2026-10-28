<?php

namespace App\Models\Sales;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A commercial task made by the app (customer card, open quote, website activity). */
#[Fillable(['kind', 'key', 'company_id', 'contact_id', 'quote_id', 'owner_id', 'level', 'data', 'status', 'snoozed_until', 'handled_by_id', 'handled_at'])]
class SalesTask extends Model
{
    public const KINDS = ['card', 'quote', 'web', 'mail'];

    /** Most urgent first. */
    public const LEVELS = ['red', 'orange', 'yellow', 'blue'];

    protected function casts(): array
    {
        return ['data' => 'array', 'snoozed_until' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Open now: open, or snoozed until a moment that has passed. */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('status', 'open')->orWhere(fn ($s) => $s->where('status', 'snoozed')->where('snoozed_until', '<=', now())));
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderByRaw("case level when 'red' then 0 when 'orange' then 1 when 'yellow' then 2 else 3 end")->orderBy('created_at');
    }
}
