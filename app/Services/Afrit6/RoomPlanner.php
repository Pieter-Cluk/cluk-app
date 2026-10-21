<?php

namespace App\Services\Afrit6;

use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\Space;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Meeting room rules:
 * - bookings per half hour between opening and closing time, weekdays only;
 * - no overlaps; a classroom setup needs a 30 min reset before and after,
 *   unless a classroom booking sits right next to it;
 * - not in the past, at most N weeks ahead;
 * - each desk gives one free booking of up to 2 h per week; the rest is billed.
 */
class RoomPlanner
{
    public function __construct(public readonly Space $room) {}

    public static function forRoom(): ?self
    {
        $room = Space::room();

        return $room ? new self($room) : null;
    }

    public function slotCount(): int
    {
        return (int) config('afrit6.room.slots');
    }

    /** @return Collection<int, RoomBooking> */
    public function bookingsOn(Carbon $date, ?int $exceptId = null): Collection
    {
        return RoomBooking::live()->where('space_id', $this->room->id)
            ->whereDate('date', $date->toDateString())
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('start_slot')->get();
    }

    /**
     * What each slot of a day is: free, booked, reset or past (+ the booking).
     *
     * @return list<array{state:string, booking:?RoomBooking, time:string}>
     */
    public function dayPlan(Carbon $date): array
    {
        $plan = [];
        for ($i = 0; $i < $this->slotCount(); $i++) {
            $plan[$i] = ['state' => 'free', 'booking' => null, 'time' => RoomBooking::slotTime($i)];
        }
        $bookings = $this->bookingsOn($date);
        foreach ($bookings as $b) {
            for ($i = $b->start_slot; $i < $b->endSlot() && $i < $this->slotCount(); $i++) {
                $plan[$i] = ['state' => 'booked', 'booking' => $b, 'time' => RoomBooking::slotTime($i)];
            }
        }
        foreach ($bookings->filter->isClassroom() as $b) {
            foreach ([$b->start_slot - 1, $b->endSlot()] as $r) {
                if ($r >= 0 && $r < $this->slotCount() && $plan[$r]['state'] === 'free') {
                    $plan[$r]['state'] = 'reset';
                    $plan[$r]['booking'] = $b;
                }
            }
        }
        foreach ($plan as $i => $p) {
            if ($p['state'] === 'free' && $this->slotStart($date, $i)->lte(now())) {
                $plan[$i]['state'] = 'past';
            }
        }

        return $plan;
    }

    public function slotStart(Carbon $date, int $slot): Carbon
    {
        return Carbon::parse($date->toDateString().' '.RoomBooking::slotTime($slot));
    }

    /** Null when the booking is possible, else a translation key for the reason. */
    public function check(Carbon $date, int $start, int $slots, string $setup, ?int $exceptId = null): ?string
    {
        $end = $start + $slots;
        if ($slots < 1 || $slots > (int) config('afrit6.room.max_half_hours') || $start < 0 || $end > $this->slotCount()) {
            return 'room.err_hours';
        }
        if (! array_key_exists($setup, config('afrit6.room.setups'))) {
            return 'room.err_setup';
        }
        if ($date->isWeekend()) {
            return 'room.err_weekend';
        }
        if ($this->slotStart($date, $start)->lte(now())) {
            return 'room.err_past';
        }
        if ($date->gt(today()->addWeeks((int) config('afrit6.room.weeks_ahead')))) {
            return 'room.err_far';
        }

        $others = $this->bookingsOn($date, $exceptId);
        $covers = fn (RoomBooking $b, int $slot) => $slot >= $b->start_slot && $slot < $b->endSlot();
        $at = fn (int $slot) => $others->first(fn ($b) => $covers($b, $slot));

        // 1. The time itself must be free…
        for ($i = $start; $i < $end; $i++) {
            if ($at($i)) {
                return 'room.err_taken';
            }
        }
        // …and must not be the reset slot of a classroom next to it
        //    (unless this booking is a classroom too: then no reset is needed between them).
        foreach ($others->filter->isClassroom() as $b) {
            foreach ([$b->start_slot - 1, $b->endSlot()] as $reset) {
                if ($reset < $start || $reset >= $end) {
                    continue;
                }
                $neighbourIsUs = $setup === 'classroom' && ($reset === $start || $reset === $end - 1)
                    && ($b->endSlot() === $start || $b->start_slot === $end);
                if (! $neighbourIsUs) {
                    return 'room.err_reset';
                }
            }
        }
        // 2. A classroom needs its own resets, unless the neighbour is a classroom.
        if ($setup === 'classroom') {
            foreach ([$start - 1, $end] as $reset) {
                if ($reset < 0 || $reset >= $this->slotCount()) {
                    continue;
                }
                $n = $at($reset);
                if ($n && ! $n->isClassroom()) {
                    return 'room.err_needs_reset';
                }
            }
        }

        return null;
    }

    /**
     * Free bookings this member has in the week of $date: [allowance, used].
     * Allowance = number of desks the member rents on that day.
     *
     * @return array{0:int,1:int}
     */
    public function weekUsage(Member $member, Carbon $date, ?int $exceptId = null): array
    {
        $allowance = $member->deskCountOn($date);
        $used = RoomBooking::live()->where('member_id', $member->id)
            ->whereBetween('date', [$date->copy()->startOfWeek()->toDateString(), $date->copy()->endOfWeek()->toDateString()])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->count();

        return [$allowance, min($used, $allowance)];
    }

    /**
     * Half hours billed for each booking of a member, week by week: in each
     * week the first N bookings (N = desks) get up to 2 h free.
     *
     * @param  Collection<int, RoomBooking>  $bookings  all live bookings of the member in the weeks concerned
     * @return array<int, int> booking id => billed half hours
     */
    public static function billedHalfHours(Member $member, Collection $bookings): array
    {
        $free = (int) config('afrit6.room.free_half_hours');
        $out = [];
        $byWeek = $bookings->sortBy(fn ($b) => $b->date->toDateString().sprintf('%03d', $b->start_slot).sprintf('%09d', $b->id))
            ->groupBy(fn ($b) => $b->date->copy()->startOfWeek()->toDateString());
        foreach ($byWeek as $weekStart => $list) {
            $allowance = $member->deskCountOn(Carbon::parse($weekStart)->max($list->first()->date));
            foreach ($list->values() as $i => $b) {
                $out[$b->id] = $i < $allowance ? max(0, $b->slots - $free) : $b->slots;
            }
        }

        return $out;
    }
}
