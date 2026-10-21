<?php

namespace App\Models\Factory;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A Cluk Factory booking. The hall is taken from the first build-up day to
 * the last breakdown day by options that still run and by confirmed events.
 */
#[Fillable([
    'number', 'status', 'source', 'title', 'event_type', 'start_date', 'days', 'buildup_days', 'breakdown_days', 'size', 'level',
    'guests', 'extras', 'note', 'language', 'req_company', 'req_vat', 'req_name', 'req_email', 'req_phone', 'company_id',
    'contact_id', 'deal_id', 'quote_id', 'owner_id', 'option_until', 'confirmed_at', 'decline_reason', 'checks',
])]
class Booking extends Model
{
    protected $table = 'factory_bookings';

    public const STATUSES = ['request', 'option', 'confirmed', 'done', 'declined', 'cancelled'];

    public const CHECKS = ['contract', 'deposit', 'catering', 'timing', 'security'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'option_until' => 'datetime', 'confirmed_at' => 'datetime',
            'extras' => 'array', 'checks' => 'array', 'days' => 'integer', 'buildup_days' => 'integer', 'breakdown_days' => 'integer', 'guests' => 'integer',
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

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** First day on site (build-up) and last day on site (breakdown). */
    public function firstDay(): Carbon
    {
        return $this->start_date->copy()->subDays($this->buildup_days);
    }

    public function lastEventDay(): Carbon
    {
        return $this->start_date->copy()->addDays(max(1, $this->days) - 1);
    }

    public function lastDay(): Carbon
    {
        return $this->lastEventDay()->addDays($this->breakdown_days);
    }

    /** Does this booking hold the hall? Confirmed and done always; options while they run. */
    public function holds(): bool
    {
        return in_array($this->status, ['confirmed', 'done'], true)
            || ($this->status === 'option' && $this->option_until && $this->option_until->isFuture());
    }

    public function isOptionExpired(): bool
    {
        return $this->status === 'option' && $this->option_until && $this->option_until->isPast();
    }

    public function displayStatus(): string
    {
        return $this->isOptionExpired() ? 'expired' : $this->status;
    }

    /** Bookings that hold the hall somewhere in [$from, $until]. */
    public static function holding(Carbon $from, Carbon $until): Collection
    {
        return static::query()
            ->where(fn (Builder $q) => $q->whereIn('status', ['confirmed', 'done'])->orWhere(fn ($q) => $q->where('status', 'option')->where('option_until', '>', now())))
            ->whereDate('start_date', '<=', $until->copy()->addDays(5)->toDateString())
            ->whereDate('start_date', '>=', $from->copy()->subDays(10)->toDateString())
            ->get()
            ->filter(fn (self $b) => $b->firstDay()->lte($until) && $b->lastDay()->gte($from))->values();
    }

    /** Day (Y-m-d) => ['booking' => Booking, 'part' => buildup|event|breakdown] for a period. */
    public static function occupancy(Carbon $from, Carbon $until, bool $withRequests = false): array
    {
        $list = static::holding($from, $until);
        if ($withRequests) {
            $list = $list->merge(static::where('status', 'request')->whereBetween('start_date', [$from->copy()->subDays(5), $until->copy()->addDays(5)])->get());
        }
        $days = [];
        foreach ($list as $b) {
            for ($d = $b->firstDay(); $d->lte($b->lastDay()); $d->addDay()) {
                $part = $d->lt($b->start_date) ? 'buildup' : ($d->gt($b->lastEventDay()) ? 'breakdown' : 'event');
                $days[$d->toDateString()] ??= ['booking' => $b, 'part' => $part];
            }
        }

        return $days;
    }

    /** Is the hall free for this booking's days (other bookings only)? Returns the clashing days. */
    public function clashes(): array
    {
        $occ = static::occupancy($this->firstDay(), $this->lastDay());
        $clash = [];
        for ($d = $this->firstDay(); $d->lte($this->lastDay()); $d->addDay()) {
            $o = $occ[$d->toDateString()] ?? null;
            if ($o && $o['booking']->id !== $this->id) {
                $clash[] = $d->toDateString();
            }
        }

        return $clash;
    }

    public function sizeItem(): ?Item
    {
        return Item::where('number', config('factory.sizes.'.$this->size.'.2'))->first();
    }

    public function levelItem(): ?Item
    {
        $no = config('factory.levels.'.$this->level);

        return $no ? Item::where('number', $no)->first() : null;
    }

    /** Starting price excl. VAT (cents): space × event days, plus Advanced. All-in: null (custom). */
    public function estimateCents(): ?int
    {
        if ($this->level === 'allin') {
            return null;
        }

        return (int) ($this->sizeItem()?->day_price_cents ?? 0) * max(1, $this->days) + (int) ($this->levelItem()?->day_price_cents ?? 0);
    }

    public function customerName(): string
    {
        return $this->company?->name ?? $this->req_company ?? $this->contact?->name ?? $this->req_name ?? '—';
    }

    public static function nextNumber(): string
    {
        $prefix = 'F-'.now()->year.'-';
        $last = DB::table('factory_bookings')->where('number', 'like', $prefix.'%')->pluck('number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
