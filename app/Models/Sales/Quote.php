<?php

namespace App\Models\Sales;

use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Contact;
use App\Models\Rental\Order;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A rental quote (R-QUO). Draft → sent → accepted / declined / change asked.
 * A new version replaces the previous one under the same number.
 */
#[Fillable([
    'number', 'version', 'status', 'deal_id', 'company_id', 'contact_id', 'owner_id', 'title', 'language',
    'out_from', 'out_until', 'use_from', 'use_until', 'location', 'intro', 'notes', 'project_discount_bp',
    'vat_code', 'vat_rate_bp', 'subtotal_cents', 'discount_cents', 'net_cents', 'vat_cents', 'gross_cents',
    'token', 'sent_at', 'sent_by_id', 'sent_to', 'valid_until', 'reserved_until', 'answered_at', 'answered_name',
    'po_number', 'answered_ip', 'decline_reason', 'answer_note',
])]
class Quote extends Model
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'declined', 'change', 'replaced'];

    public const DECLINE_REASONS = ['price', 'competitor', 'cancelled', 'other'];

    protected function casts(): array
    {
        return [
            'out_from' => 'datetime', 'out_until' => 'datetime', 'use_from' => 'date', 'use_until' => 'date',
            'sent_at' => 'datetime', 'valid_until' => 'date', 'reserved_until' => 'datetime', 'answered_at' => 'datetime',
            'version' => 'integer', 'project_discount_bp' => 'integer', 'vat_rate_bp' => 'integer',
            'subtotal_cents' => 'integer', 'discount_cents' => 'integer', 'net_cents' => 'integer', 'vat_cents' => 'integer', 'gross_cents' => 'integer',
        ];
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position')->orderBy('id');
    }

    /** Quotes that hold equipment: accepted, or sent and still within the option time. */
    public function scopeHolding(Builder $q): Builder
    {
        return $q->where(fn ($q) => $q->where('status', 'accepted')
            ->orWhere(fn ($q) => $q->whereIn('status', ['sent', 'change'])->where('reserved_until', '>=', now())));
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Can the customer still answer? */
    public function isOpenForCustomer(): bool
    {
        return in_array($this->status, ['sent', 'change'], true) && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->status === 'sent' && $this->valid_until !== null && $this->valid_until->lt(today());
    }

    /** What to show: the status, or "expired" for a sent quote past its date. */
    public function displayStatus(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function code(): string
    {
        return $this->number.($this->version > 1 ? ' · v'.$this->version : '');
    }

    /** Calendar days the customer uses the equipment (both ends included). */
    public function usedDays(): int
    {
        return max(1, (int) $this->use_from->diffInDays($this->use_until) + 1);
    }

    public function outDays(): int
    {
        return max(1, (int) $this->out_from->copy()->startOfDay()->diffInDays($this->out_until->copy()->startOfDay()) + 1);
    }

    /** The factor for the days used: 1 day 1, a week 1.5, two weeks 2.5, three 3, then + 0.5 per week. */
    public static function factorFor(int $days): float
    {
        $f = config('rental.factor');
        if ($days <= 1) {
            return (float) $f['day'];
        }
        $weeks = (int) ceil($days / 7);

        return (float) match (true) {
            $weeks === 1 => $f['week'],
            $weeks === 2 => $f['two_weeks'],
            default => $f['three_weeks'] + ($weeks - 3) * $f['per_extra_week'],
        };
    }

    public function defaultFactor(): float
    {
        return self::factorFor($this->usedDays());
    }

    public function customerName(): string
    {
        return $this->company?->name ?? $this->contact?->name ?? '—';
    }

    /** Is this the newest version of its number? */
    public function isLatest(): bool
    {
        return ! static::where('number', $this->number)->where('version', '>', $this->version)->exists();
    }

    public static function nextNumber(): string
    {
        $prefix = 'R-QUO-'.now()->year.'-';
        $last = DB::table('quotes')->where('number', 'like', $prefix.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Recalculate every line and the totals (drafts only: sent versions are frozen). */
    public function recalculate(): void
    {
        $factor = $this->defaultFactor();
        $sub = 0;
        foreach ($this->lines()->with('item')->get() as $line) {
            $line->amount_cents = $line->computeAmount($factor);
            $line->save();
            $sub += $line->amount_cents;
        }
        $discount = (int) round($sub * $this->project_discount_bp / 10000);
        $net = $sub - $discount;
        $rate = $this->vatRateNow();
        $this->forceFill([
            'subtotal_cents' => $sub, 'discount_cents' => $discount, 'net_cents' => $net,
            'vat_code' => $rate?->code, 'vat_rate_bp' => $rate?->rate_bp ?? 0,
            'vat_cents' => (int) round($net * ($rate?->rate_bp ?? 0) / 10000),
        ]);
        $this->gross_cents = $this->net_cents + $this->vat_cents;
        $this->save();
    }

    /** The customer's VAT rate on the first day of use (private person: the Belgian default). */
    public function vatRateNow(): ?VatRate
    {
        $day = $this->use_from?->toDateString();

        return $this->company
            ? $this->company->vatRate($day)
            : VatRate::forCode((string) CompanyDetail::current()->vat_default_be, $day);
    }

    public function vatPercent(): string
    {
        return rtrim(rtrim(number_format($this->vat_rate_bp / 100, 2, '.', ''), '0'), '.').'%';
    }

    /** Totals for logistics and insurance. */
    public function logistics(): array
    {
        $kg = 0.0;
        $m3 = 0.0;
        $insured = 0;
        foreach ($this->lines as $line) {
            if (! $line->item) {
                continue;
            }
            $qty = (float) $line->qty;
            $item = $line->item;
            if ($item->kind === 'package') {
                foreach ($item->contents as $c) {
                    $n = $qty * (int) $c->pivot->qty;
                    $kg += $n * (float) $c->weight_kg;
                    $m3 += $n * (float) $c->volume();
                    $insured += (int) round($n * (int) $c->catalog_price_cents);
                }
            } else {
                $kg += $qty * (float) $item->weight_kg;
                $m3 += $qty * (float) $item->volume();
                $insured += (int) round($qty * (int) $item->catalog_price_cents);
            }
        }

        return ['kg' => $kg, 'm3' => $m3, 'insured_cents' => $insured];
    }

    public function setPeriod(Carbon $outFrom, Carbon $outUntil, Carbon $useFrom, Carbon $useUntil): void
    {
        $this->fill(['out_from' => $outFrom, 'out_until' => $outUntil, 'use_from' => $useFrom, 'use_until' => $useUntil]);
    }
}
