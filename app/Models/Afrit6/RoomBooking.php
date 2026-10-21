<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** A meeting room booking: slot 0 = 08:00, each slot is 30 minutes. */
#[Fillable(['member_id', 'space_id', 'date', 'start_slot', 'slots', 'setup', 'note', 'cancelled_at'])]
class RoomBooking extends Model
{
    protected $table = 'afrit6_room_bookings';

    protected function casts(): array
    {
        return ['date' => 'date', 'start_slot' => 'integer', 'slots' => 'integer', 'cancelled_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    public function endSlot(): int
    {
        return $this->start_slot + $this->slots;
    }

    public function isClassroom(): bool
    {
        return $this->setup === 'classroom';
    }

    public static function slotTime(int $slot): string
    {
        [$h, $m] = array_map('intval', explode(':', config('afrit6.room.opens')));
        $minutes = $h * 60 + $m + $slot * 30;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->date->toDateString().' '.self::slotTime($this->start_slot));
    }

    public function timeRange(): string
    {
        return self::slotTime($this->start_slot).'–'.self::slotTime($this->endSlot());
    }

    public function hasStarted(): bool
    {
        return $this->startsAt()->lte(now());
    }
}
