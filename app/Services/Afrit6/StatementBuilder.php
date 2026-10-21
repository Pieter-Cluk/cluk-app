<?php

namespace App\Services\Afrit6;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Member;
use Illuminate\Support\Carbon;

/**
 * The bill of one member for one contract month.
 * The contract month follows the start day of the member's first desk
 * (a desk from the 14th runs 14 Oct – 13 Nov), otherwise the calendar month.
 *
 * Lines: desks (pro rata when not the whole month), storage per day,
 * one-off services, extra meeting room time beyond the free weekly slots.
 */
class StatementBuilder
{
    public function __construct(public readonly Member $member) {}

    public function anchorDay(): int
    {
        $first = $this->member->deskRentals()->orderBy('starts_on')->value('starts_on');

        return $first ? Carbon::parse($first)->day : 1;
    }

    /** @return array{0:Carbon,1:Carbon} first and last day of the contract month around $day */
    public function period(?Carbon $day = null): array
    {
        $day = ($day ?? today())->copy()->startOfDay();
        $start = $this->startIn($day->year, $day->month);
        if ($start->gt($day)) {
            $prev = $day->copy()->startOfMonth()->subMonth();
            $start = $this->startIn($prev->year, $prev->month);
        }
        $next = $start->copy()->startOfMonth()->addMonth();

        return [$start, $this->startIn($next->year, $next->month)->subDay()];
    }

    private function startIn(int $year, int $month): Carbon
    {
        $first = Carbon::create($year, $month, 1)->startOfDay();

        return $first->copy()->day(min($this->anchorDay(), $first->daysInMonth));
    }

    /**
     * @return array{start:Carbon, end:Carbon, lines:list<array{type:string, text:string, qty:float, unit_cents:int, amount_cents:int}>, total_cents:int}
     */
    public function build(?Carbon $day = null, ?string $locale = null): array
    {
        [$start, $end] = $this->period($day);
        $locale ??= $this->member->locale;
        $periodDays = self::days($start, $end);
        $d = fn (Carbon $c) => $c->locale($locale)->translatedFormat('j M');
        $lines = [];

        // Desks.
        $rentals = DeskRental::with('space')->where('member_id', $this->member->id)
            ->where('starts_on', '<=', $end->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $start->toDateString()))
            ->orderBy('starts_on')->get();
        foreach ($rentals as $r) {
            $from = $r->starts_on->max($start);
            $to = $r->ends_on ? $r->ends_on->min($end) : $end->copy();
            $days = self::days($from, $to);
            $full = $days >= $periodDays;
            $amount = $full ? $r->monthly_price_cents : (int) round($r->monthly_price_cents * $days / $periodDays);
            $lines[] = [
                'type' => 'desk',
                'text' => $full
                    ? __('workspace.bill.desk', ['desk' => $r->space->code], $locale)
                    : __('workspace.bill.desk_part', ['desk' => $r->space->code, 'from' => $d($from), 'to' => $d($to), 'days' => $days, 'of' => $periodDays], $locale),
                'qty' => $full ? 1 : round($days / $periodDays, 4),
                'unit_cents' => $r->monthly_price_cents,
                'amount_cents' => $amount,
            ];
        }

        // Desks booked for a morning, afternoon or day.
        $days = DeskBooking::live()->with('space')->where('member_id', $this->member->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->get();
        foreach ($days as $db) {
            $lines[] = [
                'type' => 'desk_day',
                'text' => __('workspace.bill.desk_day', ['desk' => $db->space->code, 'date' => $d($db->date), 'part' => __('workspace.days.parts.'.$db->part, [], $locale)], $locale),
                'qty' => 1,
                'unit_cents' => $db->price_cents,
                'amount_cents' => $db->price_cents,
            ];
        }

        // Storage per day, and one-off services.
        $requests = ServiceRequest::where('member_id', $this->member->id)
            ->whereIn('kind', ['shelf', 'pallet', 'packing', 'receive', 'send'])
            ->whereIn('status', [ServiceRequest::CONFIRMED, ServiceRequest::DONE, ServiceRequest::WAITING])
            ->where('date', '<=', $end->toDateString())
            ->orderBy('date')->get()->filter->isBillable();
        foreach ($requests as $q) {
            if ($q->isStorage()) {
                if ($q->ends_on && $q->ends_on->lt($start)) {
                    continue;
                }
                $from = $q->date->max($start);
                $to = $q->ends_on ? $q->ends_on->min($end) : $end->copy();
                $days = self::days($from, $to);
                $unit = Pricing::dayPrice($q->kind);
                $lines[] = [
                    'type' => $q->kind,
                    'text' => __('workspace.bill.'.$q->kind, ['qty' => $q->qty, 'from' => $d($from), 'to' => $d($to), 'days' => $days], $locale),
                    'qty' => $q->qty * $days,
                    'unit_cents' => $unit,
                    'amount_cents' => $q->qty * $days * $unit,
                ];
            } elseif ($q->date->gte($start) && $q->amount_cents > 0) {
                $lines[] = [
                    'type' => $q->kind,
                    'text' => __('workspace.bill.service', ['what' => __('workspace.kinds.'.$q->kind, [], $locale), 'date' => $d($q->date), 'count' => self::counts($q->colis, $q->pallets, $locale)], $locale),
                    'qty' => 1,
                    'unit_cents' => $q->amount_cents,
                    'amount_cents' => $q->amount_cents,
                ];
            }
        }

        // Extra meeting room time.
        $bookings = RoomBooking::live()->where('member_id', $this->member->id)
            ->whereBetween('date', [$start->copy()->startOfWeek()->toDateString(), $end->copy()->endOfWeek()->toDateString()])
            ->get();
        $billed = RoomPlanner::billedHalfHours($this->member, $bookings);
        $halfHours = $bookings->filter(fn ($b) => $b->date->between($start, $end))->sum(fn ($b) => $billed[$b->id] ?? 0);
        if ($halfHours > 0) {
            $unit = Pricing::price('room_half_hour');
            $lines[] = [
                'type' => 'room',
                'text' => __('workspace.bill.room', ['hours' => rtrim(rtrim(number_format($halfHours / 2, 1, ',', ''), '0'), ',')], $locale),
                'qty' => $halfHours,
                'unit_cents' => $unit,
                'amount_cents' => $halfHours * $unit,
            ];
        }

        return [
            'start' => $start,
            'end' => $end,
            'lines' => $lines,
            'total_cents' => array_sum(array_column($lines, 'amount_cents')),
        ];
    }

    /** Calendar days from $a to $b, both included (safe across summer/winter time). */
    public static function days(Carbon $a, Carbon $b): int
    {
        return intdiv(strtotime($b->toDateString().' 00:00:00 UTC') - strtotime($a->toDateString().' 00:00:00 UTC'), 86400) + 1;
    }

    public static function counts(int $colis, int $pallets, ?string $locale = null): string
    {
        $parts = [];
        if ($colis) {
            $parts[] = trans_choice('workspace.colis', $colis, ['n' => $colis], $locale);
        }
        if ($pallets) {
            $parts[] = trans_choice('workspace.pallets', $pallets, ['n' => $pallets], $locale);
        }

        return $parts ? implode(' + ', $parts) : '—';
    }
}
