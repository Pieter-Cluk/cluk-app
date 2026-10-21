<?php

namespace App\Models\Sales;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A sales opportunity, from first request to won or lost.
 * Stages: new → qualified → proposal → negotiation → won / lost (or disqualified).
 */
#[Fillable([
    'number', 'title', 'module', 'stage', 'source', 'company_id', 'contact_id', 'owner_id', 'event_start', 'event_end',
    'location', 'value_cents', 'check_date', 'check_budget', 'check_decider', 'lost_reason', 'competitor', 'close_note',
    'closed_at', 'closed_by_id', 'incoming_id',
])]
class Deal extends Model
{
    public const OPEN = ['new', 'qualified', 'proposal', 'negotiation'];

    public const CLOSED = ['won', 'lost', 'disqualified'];

    public const MODULES = ['dryhire', 'factory', 'sales', 'afrit6'];

    public const SOURCES = ['website', 'email', 'linkedin', 'facebook', 'instagram', 'whatsapp', 'phone', 'visit', 'referral', 'other'];

    public const PROBABILITY = ['new' => 10, 'qualified' => 25, 'proposal' => 50, 'negotiation' => 75, 'won' => 100, 'lost' => 0, 'disqualified' => 0];

    public const LOST_REASONS = ['price', 'competitor', 'not_available', 'cancelled', 'no_budget', 'no_response', 'other_solution', 'too_late'];

    protected function casts(): array
    {
        return [
            'event_start' => 'date', 'event_end' => 'date', 'closed_at' => 'datetime', 'value_cents' => 'integer',
            'check_date' => 'boolean', 'check_budget' => 'boolean', 'check_decider' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealActivity::class)->latest('id');
    }

    public function incoming(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class, 'incoming_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('stage', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN, true);
    }

    public function qualified(): bool
    {
        return $this->check_date && $this->check_budget && $this->check_decider;
    }

    public function probability(): int
    {
        return self::PROBABILITY[$this->stage] ?? 0;
    }

    public function weightedCents(): int
    {
        return (int) round($this->value_cents * $this->probability() / 100);
    }

    /** Who the deal is for: the company, else the contact (private person). */
    public function customerName(): string
    {
        return $this->company?->name ?? $this->contact?->name ?? '—';
    }

    public static function nextNumber(): string
    {
        $prefix = 'D-'.now()->year.'-';
        $last = DB::table('deals')->where('number', 'like', $prefix.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function log(string $kind, string $body, ?User $by = null): DealActivity
    {
        return $this->activities()->create(['kind' => $kind, 'body' => $body, 'user_id' => $by?->id ?? auth('web')->id()]);
    }
}
