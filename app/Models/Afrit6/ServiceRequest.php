<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request from a member (storage, packing, goods in/out, desks) or a staff
 * task (room reset). It is also the task the Afrit 6 team works on.
 */
#[Fillable([
    'member_id', 'kind', 'status', 'status_before_wait', 'date', 'ends_on', 'qty', 'colis', 'pallets',
    'receive_mode', 'then_send', 'from_text', 'carrier', 'note', 'photos', 'scans', 'space_id', 'from_space_id',
    'room_booking_id', 'amount_cents', 'assignee_id', 'decided_by_id', 'decline_reason', 'done_at',
])]
class ServiceRequest extends Model
{
    protected $table = 'afrit6_requests';

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const WAITING = 'waiting';

    public const DONE = 'done';

    public const DECLINED = 'declined';

    public const STORAGE = ['shelf', 'pallet'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'ends_on' => 'date', 'then_send' => 'boolean', 'done_at' => 'datetime',
            'qty' => 'integer', 'colis' => 'integer', 'pallets' => 'integer', 'amount_cents' => 'integer', 'photos' => 'array', 'scans' => 'array',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function fromSpace(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'from_space_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'room_booking_id');
    }

    /** The exact shelf or pallet places of a storage request. */
    public function places(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Warehouse\Position::class, 'afrit6_storage_places', 'request_id', 'position_id')->withPivot('assigned_by_id')->withTimestamps()->orderBy('code');
    }

    /** Storage that runs today (confirmed or done, not ended): its places belong to the member. */
    public function scopeRunningStorage(Builder $q): Builder
    {
        return $q->whereIn('kind', self::STORAGE)
            ->where(fn ($w) => $w->whereIn('status', [self::CONFIRMED, self::DONE])->orWhere(fn ($x) => $x->where('status', self::WAITING)->where('status_before_wait', self::CONFIRMED)))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RequestMessage::class, 'request_id')->orderBy('id');
    }

    public function scopeForMembers(Builder $q): Builder
    {
        return $q->whereIn('kind', config('afrit6.member_kinds'));
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [self::PENDING, self::CONFIRMED, self::WAITING]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::CONFIRMED, self::WAITING], true);
    }

    public function isStorage(): bool
    {
        return in_array($this->kind, self::STORAGE, true);
    }

    /** Counts on the bill: confirmed or done (storage only while it runs). */
    public function isBillable(): bool
    {
        return in_array($this->status, [self::CONFIRMED, self::DONE], true)
            || ($this->status === self::WAITING && $this->status_before_wait === self::CONFIRMED);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && ! $this->isStorage() && $this->date->lt(today());
    }

    /** The task status shown to staff: pending, confirmed, waiting, overdue, done, declined. */
    public function taskStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }
}
