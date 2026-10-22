<?php

namespace App\Models\Rental;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Models\Warehouse\Charge;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A rental order (R-OC). Made when the customer accepts a quote; asset
 * management covers every line from own stock or sub-hire, then approves
 * and the order confirmation goes to the customer.
 */
#[Fillable([
    'number', 'status', 'quote_id', 'deal_id', 'company_id', 'contact_id', 'title', 'language', 'out_from', 'out_until',
    'use_from', 'use_until', 'location', 'po_number', 'confirmed_name', 'confirmed_at', 'confirmed_online', 'customer_email',
    'handed_by_id', 'assignee_id', 'approved_at', 'approved_by_id', 'loaded_at', 'loaded_by_id', 'returned_at', 'cancelled_at', 'cancel_reason', 'reopened_at', 'reopened_by_id', 'reopen_reason', 'project_discount_bp',
    'vat_code', 'vat_rate_bp', 'subtotal_cents', 'discount_cents', 'net_cents', 'vat_cents', 'gross_cents',
])]
class Order extends Model
{
    public const OPEN = ['allocation', 'approved'];

    /** Where an open order stands, in the order work is done. */
    public const STATES = ['subhire', 'waiting', 'allocate', 'ready'];

    protected function casts(): array
    {
        return [
            'out_from' => 'datetime', 'out_until' => 'datetime', 'use_from' => 'date', 'use_until' => 'date',
            'confirmed_at' => 'datetime', 'approved_at' => 'datetime', 'loaded_at' => 'datetime', 'returned_at' => 'datetime', 'cancelled_at' => 'datetime', 'reopened_at' => 'datetime', 'confirmed_online' => 'boolean',
            'project_discount_bp' => 'integer', 'vat_rate_bp' => 'integer', 'subtotal_cents' => 'integer', 'discount_cents' => 'integer',
            'net_cents' => 'integer', 'vat_cents' => 'integer', 'gross_cents' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('position')->orderBy('id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(OrderAsset::class);
    }

    /** Costs found at the return check (cleaning, missing parts, damage). */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function subhires(): HasMany
    {
        return $this->hasMany(SubhireRequest::class)->orderBy('id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function handedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_by_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN);
    }

    public function scopeOverlapping(Builder $q, $from, $until): Builder
    {
        return $q->where('out_from', '<', $until)->where('out_until', '>', $from);
    }

    public function isInAllocation(): bool
    {
        return $this->status === 'allocation';
    }

    public function customerName(): string
    {
        return $this->company?->name ?? $this->contact?->name ?? $this->confirmed_name ?? '—';
    }

    public function vatPercent(): string
    {
        return rtrim(rtrim(number_format($this->vat_rate_bp / 100, 2, '.', ''), '0'), '.').'%';
    }

    /** Where the order stands: sub-hire to request, waiting for a partner, allocate own stock, ready to approve. */
    public function state(): string
    {
        if ($this->status !== 'allocation') {
            return $this->status;
        }
        $lines = $this->lines()->with(['item', 'assets', 'subhireLines.request'])->get();
        $waiting = false;
        $allocate = false;
        foreach ($lines as $line) {
            if (! $line->needsStock()) {
                continue;
            }
            if ($line->shortWithoutPartner($this) > 0 || $line->subhireLines->contains(fn ($s) => $s->request->status === 'draft')) {
                return 'subhire';
            }
            if ($line->subhireLines->contains(fn ($s) => $s->request->status === 'sent')) {
                $waiting = true;
            }
            if ($line->ownAllocated() < $line->ownNeeded()) {
                $allocate = true;
            }
        }

        return $waiting ? 'waiting' : ($allocate ? 'allocate' : 'ready');
    }

    public static function nextNumber(): string
    {
        $prefix = 'R-OC-'.now()->year.'-';
        $last = DB::table('orders')->where('number', 'like', $prefix.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
