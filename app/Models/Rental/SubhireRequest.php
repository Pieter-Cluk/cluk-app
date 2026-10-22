<?php

namespace App\Models\Rental;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sub-hire from a partner: the price request (SHQ) and, once accepted,
 * the confirmation (SHC). One per partner per order.
 */
#[Fillable([
    'number', 'confirm_number', 'order_id', 'supplier_id', 'status', 'delivery_at', 'return_at', 'sent_to', 'sent_at',
    'their_ref', 'note', 'extra_cents', 'extra_text', 'total_cents', 'answered_at', 'accepted_at', 'accepted_by_id', 'created_by_id',
    'confirmed_snapshot', 'adjusted_at', 'adjust_count', 'received_at', 'received_by_id', 'in_photos', 'in_note', 'handed_back_at', 'handed_back_by_id', 'out_photos', 'out_note',
])]
class SubhireRequest extends Model
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'not_available', 'cancelled'];

    protected function casts(): array
    {
        return ['delivery_at' => 'datetime', 'return_at' => 'datetime', 'sent_at' => 'datetime', 'answered_at' => 'datetime', 'accepted_at' => 'datetime', 'confirmed_snapshot' => 'array', 'adjusted_at' => 'datetime', 'adjust_count' => 'integer', 'received_at' => 'datetime', 'handed_back_at' => 'datetime', 'in_photos' => 'array', 'out_photos' => 'array', 'extra_cents' => 'integer', 'total_cents' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubhireLine::class, 'request_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function handedBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_back_by_id');
    }

    /** Dates and quantities as they stand now, to compare with what the partner confirmed. */
    public function snapshot(): array
    {
        $this->loadMissing('lines');

        return [
            'delivery_at' => $this->delivery_at?->toIso8601String(), 'return_at' => $this->return_at?->toIso8601String(),
            'lines' => $this->lines->mapWithKeys(fn ($l) => [(string) $l->id => ['name' => $l->name, 'qty' => $l->qty]])->all(),
        ];
    }

    /** Confirmed before (has an SHC number): any new message to the partner is an adjustment. */
    public function wasConfirmed(): bool
    {
        return $this->confirm_number !== null;
    }

    /** What changed since the partner's last confirmation: [label, was, now] rows. */
    public function changes(): array
    {
        $old = $this->confirmed_snapshot;
        if (! $old) {
            return [];
        }
        $now = $this->snapshot();
        $rows = [];
        foreach (['delivery_at', 'return_at'] as $k) {
            if (($old[$k] ?? null) !== $now[$k]) {
                $rows[] = [$k, $old[$k] ? Carbon::parse($old[$k]) : null, $now[$k] ? Carbon::parse($now[$k]) : null];
            }
        }
        foreach ($old['lines'] ?? [] as $id => $l) {
            $n = $now['lines'][$id]['qty'] ?? 0;
            if ($n !== $l['qty']) {
                $rows[] = ['qty', $l['name'], [$l['qty'], $n]];
            }
        }
        foreach ($now['lines'] as $id => $l) {
            if (! isset($old['lines'][$id])) {
                $rows[] = ['qty', $l['name'], [0, $l['qty']]];
            }
        }

        return $rows;
    }

    public function code(): string
    {
        return $this->confirm_number ?? $this->number;
    }

    /** Days the partner charges: from delivery to return, both included. */
    public function days(): int
    {
        if (! $this->delivery_at || ! $this->return_at) {
            return 1;
        }

        return max(1, (int) $this->delivery_at->copy()->startOfDay()->diffInDays($this->return_at->copy()->startOfDay()) + 1);
    }

    public function recalculate(): void
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            $line->amount_cents = (int) round($line->qty * (int) $line->price_cents * (float) $line->factor * (1 - $line->discount_bp / 10000));
            $line->save();
            $sum += $line->amount_cents;
        }
        $this->total_cents = $sum + $this->extra_cents;
        $this->save();
    }

    /** Has the partner's price been typed in for every line? */
    public function hasPrices(): bool
    {
        return $this->lines->isNotEmpty() && $this->lines->every(fn ($l) => $l->price_cents !== null);
    }

    /** What we charge the customer for these pieces (their share of the order lines). */
    public function revenueCents(): int
    {
        return (int) $this->lines->sum(function ($l) {
            $ol = $l->orderLine;

            return $ol && (float) $ol->qty > 0 ? (int) round($ol->amount_cents * $l->qty / (float) $ol->qty) : 0;
        });
    }

    public static function nextNumber(string $kind = 'SHQ'): string
    {
        $prefix = $kind.'-'.now()->year.'-';
        $col = $kind === 'SHC' ? 'confirm_number' : 'number';
        $last = DB::table('subhire_requests')->where($col, 'like', $prefix.'%')->pluck($col)
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
