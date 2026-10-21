<?php

namespace App\Services;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Space;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Factory\Booking;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireRequest;
use App\Models\Sales\Quote;
use App\Services\Factory\Bookings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the office wall screens show. No money anywhere: anybody walking by may see it.
 */
class WallData
{
    // ------------------------------------------------------------------ today

    /** Going out and coming in between $from and $until (both days included). */
    public static function moves(Carbon $from, Carbon $until): array
    {
        $a = $from->copy()->startOfDay();
        $b = $until->copy()->endOfDay();
        $out = collect();
        $in = collect();

        $orders = Order::with(['company', 'contact', 'lines.item', 'lines.assets', 'lines.subhireLines.request'])->open()
            ->where(fn ($q) => $q->whereBetween('out_from', [$a, $b])->orWhereBetween('out_until', [$a, $b]))->get();
        foreach ($orders as $o) {
            $stock = $o->lines->filter(fn ($l) => $l->needsStock());
            $covered = $stock->filter(fn ($l) => $l->isCovered())->count();
            $pieces = $stock->sum(fn ($l) => $l->pieces());
            if ($o->out_from->between($a, $b)) {
                $st = $o->status === 'allocation' ? 'allocating' : ($o->out_from->isPast() ? 'gone' : 'to_pick');
                $out->push(['at' => $o->out_from, 'job' => $o->title, 'who' => $o->customerName(), 'what' => __('wall.pieces', ['n' => $pieces]),
                    'done' => $covered, 'total' => max(1, $stock->count()), 'prog' => __('wall.lines_covered', ['n' => $covered, 'total' => $stock->count()]), 'st' => $st, 'kind' => 'out']);
            }
            if ($o->out_until->between($a, $b) && $o->status === 'approved') {
                $in->push(['at' => $o->out_until, 'job' => __('wall.return', ['title' => $o->title]), 'who' => $o->customerName(), 'what' => __('wall.pieces', ['n' => $pieces]),
                    'done' => 0, 'total' => 1, 'prog' => '', 'st' => $o->out_until->isPast() ? 'due' : 'expected', 'kind' => 'in']);
            }
        }

        foreach (SubhireRequest::with(['supplier', 'lines', 'order'])->where('status', 'accepted')
            ->where(fn ($q) => $q->whereBetween('delivery_at', [$a, $b])->orWhereBetween('return_at', [$a, $b]))->get() as $r) {
            $what = __('wall.pieces', ['n' => $r->lines->sum('qty')]);
            if ($r->delivery_at && $r->delivery_at->between($a, $b)) {
                $in->push(['at' => $r->delivery_at, 'job' => __('wall.subhire_in'), 'who' => $r->supplier->name.' · '.$r->order->title, 'what' => $what, 'done' => 0, 'total' => 1, 'prog' => '', 'st' => $r->delivery_at->isPast() ? 'due' : 'expected', 'kind' => 'in']);
            }
            if ($r->return_at && $r->return_at->between($a, $b)) {
                $out->push(['at' => $r->return_at, 'job' => __('wall.subhire_back'), 'who' => $r->supplier->name, 'what' => $what, 'done' => 0, 'total' => 1, 'prog' => '', 'st' => 'to_pick', 'kind' => 'out']);
            }
        }

        foreach (ServiceRequest::with('member.company')->whereIn('kind', ['receive', 'send', 'packing'])
            ->whereIn('status', [ServiceRequest::PENDING, ServiceRequest::CONFIRMED, ServiceRequest::WAITING, ServiceRequest::DONE])
            ->whereBetween('date', [$a->toDateString(), $b->toDateString()])->get() as $sr) {
            $row = ['at' => $sr->date->copy()->setTime(9, 0), 'no_time' => true, 'job' => __('workspace.kinds.'.$sr->kind).' · Afrit 6', 'who' => $sr->member?->companyName() ?? '—',
                'what' => trim(($sr->colis ? __('wall.colis', ['n' => $sr->colis]) : '').($sr->pallets ? ' '.__('wall.pallets', ['n' => $sr->pallets]) : '')), 'done' => $sr->status === ServiceRequest::DONE ? 1 : 0, 'total' => 1, 'prog' => '',
                'st' => $sr->status === ServiceRequest::DONE ? 'done' : ($sr->status === ServiceRequest::PENDING ? 'to_confirm' : 'planned'), 'kind' => $sr->kind === 'receive' ? 'in' : 'out'];
            $sr->kind === 'receive' ? $in->push($row) : $out->push($row);
        }

        return ['out' => $out->sortBy('at')->values(), 'in' => $in->sortBy('at')->values()];
    }

    /** Equipment out now per category, as % of what we own. */
    public static function fleetOut(): Collection
    {
        $owned = self::ownedPerCategory();
        $used = [];
        foreach (Order::with(['lines.item.contents', 'lines.subhireLines.request'])->open()->where('out_from', '<=', now())->where('out_until', '>=', now())->get() as $o) {
            foreach ($o->lines as $l) {
                if ($l->needsStock()) {
                    self::addUse($used, $l->item, $l->ownNeeded());
                }
            }
        }

        return $owned->sortDesc()->take(5)->map(fn ($n, $cat) => $n ? (int) round(min(100, ($used[$cat] ?? 0) / $n * 100)) : 0);
    }

    /** Small facts about Afrit 6 and the Factory today. */
    public static function venues(): array
    {
        $today = today();
        $desks = Space::desks()->count();
        $taken = DeskRental::activeOn($today)->whereIn('space_id', Space::desks()->pluck('id'))->distinct()->count('space_id')
            + DeskBooking::live()->whereDate('date', $today)->distinct()->count('space_id');
        $next = Booking::whereIn('status', ['confirmed', 'option'])->whereDate('start_date', '>=', $today->copy()->subDays(3))->orderBy('start_date')->get()
            ->first(fn ($b) => $b->lastDay()->gte($today) && $b->holds());

        return [
            'desks' => min($desks, $taken).' / '.$desks,
            'room' => RoomBooking::live()->whereDate('date', $today)->count(),
            'shelf_overdue' => ServiceRequest::where('kind', 'receive')->where('receive_mode', 'shelf24')->whereIn('status', [ServiceRequest::CONFIRMED, ServiceRequest::DONE])
                ->whereDate('date', '<', $today->copy()->subDay())->where(fn ($q) => $q->whereNull('ends_on'))->count(),
            'factory' => $next,
        ];
    }

    /** What still needs a person. */
    public static function openWork(): array
    {
        return [
            'orders' => Order::where('status', 'allocation')->count(),
            'subhire' => SubhireRequest::whereIn('status', ['draft', 'sent'])->count(),
            'afrit6' => ServiceRequest::open()->count(),
            'factory' => Booking::where('status', 'request')->count(),
        ];
    }

    // --------------------------------------------------------------- planning

    /** @return array<string, int> category => pieces we own */
    private static function ownedPerCategory(): Collection
    {
        $serial = Asset::query()->whereNotIn('status', [Asset::RETIRED, Asset::REPAIR])
            ->join('equipment_items', 'equipment_items.id', '=', 'equipment_assets.item_id')
            ->where('equipment_items.kind', '!=', 'service')
            ->selectRaw('equipment_items.category as cat, count(*) as n')->groupBy('equipment_items.category')->pluck('n', 'cat');
        $counted = Item::where('serial', false)->whereIn('kind', ['item', 'case'])->where('active', true)
            ->selectRaw('category as cat, sum(stock_qty) as n')->groupBy('category')->pluck('n', 'cat');
        $all = collect();
        foreach ([$serial, $counted] as $list) {
            foreach ($list as $cat => $n) {
                $all[$cat ?: '—'] = ($all[$cat ?: '—'] ?? 0) + (int) $n;
            }
        }

        return $all->filter(fn ($n) => $n > 0);
    }

    private static function addUse(array &$used, Item $item, int $qty): void
    {
        if ($item->kind === 'package') {
            foreach ($item->contents as $c) {
                self::addUse($used, $c, $qty * (int) $c->pivot->qty);
            }

            return;
        }
        $cat = $item->category ?: '—';
        $used[$cat] = ($used[$cat] ?? 0) + $qty;
    }

    /** Heat rows: % of capacity per day for equipment categories, the Factory hall and Afrit 6 desks. */
    public static function heat(Carbon $start, int $days): array
    {
        $end = $start->copy()->addDays($days - 1)->endOfDay();
        $owned = self::ownedPerCategory()->sortDesc()->take(5);
        $use = [];
        foreach (Order::with(['lines.item.contents', 'lines.subhireLines.request'])->open()->where('out_from', '<=', $end)->where('out_until', '>=', $start)->get() as $o) {
            $per = [];
            foreach ($o->lines as $l) {
                if ($l->needsStock()) {
                    self::addUse($per, $l->item, $l->ownNeeded());
                }
            }
            for ($i = 0; $i < $days; $i++) {
                $d = $start->copy()->addDays($i);
                if ($o->out_from->copy()->startOfDay()->lte($d) && $o->out_until->copy()->endOfDay()->gte($d)) {
                    foreach ($per as $cat => $n) {
                        $use[$cat][$i] = ($use[$cat][$i] ?? 0) + $n;
                    }
                }
            }
        }
        $rows = [];
        foreach ($owned as $cat => $n) {
            $rows[] = ['label' => $cat, 'sub' => __('wall.pieces_out'), 'cells' => array_map(fn ($i) => (int) round(min(100, ($use[$cat][$i] ?? 0) / $n * 100)), range(0, $days - 1))];
        }
        $occ = Booking::occupancy($start, $end);
        $rows[] = ['label' => 'Cluk Factory', 'sub' => __('wall.venue_booked'), 'cells' => array_map(function ($i) use ($start, $occ) {
            $o = $occ[$start->copy()->addDays($i)->toDateString()] ?? null;

            return $o ? ($o['booking']->status === 'option' ? 50 : ($o['part'] === 'event' ? 100 : 70)) : 0;
        }, range(0, $days - 1))];
        $desks = max(1, Space::desks()->count());
        $deskIds = Space::desks()->pluck('id');
        $rows[] = ['label' => 'Afrit 6', 'sub' => __('wall.desks_taken'), 'cells' => array_map(function ($i) use ($start, $desks, $deskIds) {
            $d = $start->copy()->addDays($i);
            $n = DeskRental::activeOn($d)->whereIn('space_id', $deskIds)->distinct()->count('space_id') + DeskBooking::live()->whereDate('date', $d->toDateString())->distinct()->count('space_id');

            return (int) round(min(100, $n / $desks * 100));
        }, range(0, $days - 1))];

        return $rows;
    }

    /** Job lanes: quotes in option, orders, Factory bookings, as bars over the days. */
    public static function jobs(Carbon $start, int $days, int $max = 14): Collection
    {
        $end = $start->copy()->addDays($days - 1)->endOfDay();
        $jobs = collect();
        foreach (Quote::with(['company', 'contact'])->holding()->whereDoesntHave('order')->where('status', '!=', 'accepted')->where('out_from', '<=', $end)->where('out_until', '>=', $start)->get() as $q) {
            if (Bookings::forQuote($q)) {
                continue; // shown as the Factory booking
            }
            $jobs->push(['name' => $q->title, 'sub' => $q->customerName().' · '.__('wall.dryhire'), 'from' => $q->out_from, 'until' => $q->out_until, 'st' => 'opt']);
        }
        foreach (Order::with(['company', 'contact'])->open()->where('out_from', '<=', $end)->where('out_until', '>=', $start)->get() as $o) {
            $st = $o->out_until->isPast() ? 'done' : ($o->out_from->isPast() ? 'out' : 'conf');
            $jobs->push(['name' => $o->title, 'sub' => $o->customerName().' · '.__('wall.dryhire'), 'from' => $o->out_from, 'until' => $o->out_until, 'st' => $st]);
        }
        foreach (Booking::with('company')->whereIn('status', ['option', 'confirmed', 'done'])->whereDate('start_date', '<=', $end)->whereDate('start_date', '>=', $start->copy()->subDays(10))->get() as $b) {
            if ($b->lastDay()->lt($start) || ($b->status === 'option' && ! $b->holds())) {
                continue;
            }
            $st = $b->status === 'option' ? 'opt' : ($b->lastDay()->endOfDay()->isPast() ? 'done' : ($b->firstDay()->isPast() ? 'out' : 'conf'));
            $jobs->push(['name' => $b->title, 'sub' => $b->customerName().' · Cluk Factory', 'from' => $b->firstDay(), 'until' => $b->lastDay()->endOfDay(), 'st' => $st]);
        }

        return $jobs->sortBy('from')->take($max)->map(function ($j) use ($start, $days) {
            $a = max(0, (int) $start->copy()->startOfDay()->diffInDays($j['from']->copy()->startOfDay(), false));
            $b = min($days - 1, (int) $start->copy()->startOfDay()->diffInDays($j['until']->copy()->startOfDay(), false));

            return $j + ['a' => $a, 'b' => $b];
        })->filter(fn ($j) => $j['b'] >= 0 && $j['a'] < $days)->values();
    }
}
