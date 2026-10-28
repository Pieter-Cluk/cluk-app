<?php

namespace App\Http\Controllers;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Space;
use App\Models\Equipment\Asset;
use App\Models\Factory\Booking;
use App\Models\Factory\Task;
use App\Models\Finance\PreInvoice;
use App\Models\Member;
use App\Models\Rental\Order;
use App\Models\Sales\Deal;
use App\Models\Sales\IncomingMessage;
use App\Models\Sales\Quote;
use App\Models\Sales\SalesTask;
use App\Models\User;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\Delivery;
use App\Models\Web\CustomerAccount;
use App\Models\Work\Todo;
use App\Services\Finance\PreInvoices;
use App\Services\WallData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The start page: today's work, the numbers that matter and what needs attention. Each block only for who may see it. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $u = $request->user();
        $money = $u->hasRight('finance.see_margins') || $u->canUseModule('sales', 2);
        $type = in_array($request->query('type'), ['dryhire', 'factory', 'afrit6'], true) ? $request->query('type') : 'all';
        $tab = in_array($request->query('tab'), ['out', 'in', 'booking'], true) ? $request->query('tab') : 'all';
        $who = $request->query('who') === 'all' ? 'all' : 'me';

        return view('dashboard', [
            'user' => $u, 'money' => $money, 'type' => $type, 'tab' => $tab, 'who' => $who,
            'kpi' => $this->kpis($u, $money),
            'tasks' => self::tasks($u, $who),
            'motion' => $this->motion($u, $type, $tab),
            'pipeline' => $u->canUseModule('sales') ? $this->pipeline() : null,
            'shortages' => $u->canUseModule('dryhire', 2) ? Order::with('company')->where('status', 'allocation')->orderBy('out_from')->get()->filter(fn ($o) => $o->state() === 'subhire')->take(5)->values() : null,
            'week' => $this->week($u),
        ]);
    }

    private function kpis(User $u, bool $money): array
    {
        $k = [];
        $m0 = today()->startOfMonth();
        $m1 = today()->endOfMonth();
        if ($money && $u->canUseModule('sales')) {
            $orders = Order::whereIn('status', ['allocation', 'approved'])->whereBetween('use_from', [$m0, $m1])->get(['net_cents']);
            $factory = Booking::with('quote')->whereIn('status', ['confirmed', 'done'])->whereBetween('start_date', [$m0, $m1])->get();
            $k['jobs'] = ['n' => $orders->count() + $factory->count(), 'cents' => (int) $orders->sum('net_cents') + (int) $factory->sum(fn ($b) => $b->currentQuote()?->net_cents ?? 0)];
            $sent = Quote::where('status', 'sent')->get()->filter(fn ($q) => $q->isLatest());
            $k['quotes'] = ['n' => $sent->count(), 'cents' => (int) $sent->sum('net_cents'), 'old' => $sent->filter(fn ($q) => $q->sent_at && $q->sent_at->lt(now()->subDays(5)))->count()];
        }
        if ($u->canUseModule('afrit6')) {
            $desks = Space::desks()->count();
            $taken = DeskRental::activeOn(today())->whereIn('space_id', Space::desks()->pluck('id'))->distinct()->count('space_id')
                + DeskBooking::live()->whereDate('date', today())->distinct()->count('space_id');
            $k['desks'] = ['taken' => min($desks, $taken), 'total' => $desks, 'ending' => DeskRental::whereNotNull('ends_on')->whereBetween('ends_on', [today(), today()->addDays(7)])->count()];
        }
        if ($u->canUseModule('warehouse')) {
            $k['warehouse'] = [
                'out' => Order::whereIn('status', ['allocation', 'approved'])->whereDate('out_from', today())->count(),
                'back' => Order::where('status', 'approved')->whereDate('out_until', today())->count(),
                'checks' => Asset::where('status', Asset::CHECK)->count(),
            ];
        }

        return $k;
    }

    /** Things waiting for a person: mine, or everyone's. */
    /** The task tiles; also used for the daily summary email. */
    public static function tasks(User $u, string $who): array
    {
        $t = [];
        // Tasks for a person (from meetings or added by hand): open, and how many are late.
        // "Everyone" shows colleagues' tasks only with the right to see them; otherwise the tile says it shows only yours.
        $allTodos = $who === 'all' && $u->hasRight('meetings.see_all');
        $todos = Todo::open()->when(! $allTodos, fn ($q) => $q->where('assignee_id', $u->id));
        $late = (clone $todos)->whereDate('due_on', '<', today())->count();
        $t[] = ['n' => $todos->count(), 'key' => $allTodos ? 'todos_all' : 'todos', 'url' => route('todos.index', ['v' => $allTodos ? 'all' : 'mine']), 'tone' => $late ? 'warn' : 'info', 'late' => $late,
            'note' => $who === 'all' && ! $allTodos ? __('dashboard.tasks.todos_only_mine') : null, 'show' => $who === 'all'];
        $mine = fn ($q) => $who === 'me' ? $q->where(fn ($w) => $w->whereNull('assignee_id')->orWhere('assignee_id', $u->id)) : $q;
        if ($u->canUseModule('sales', 2)) {
            // Commercial tasks: the number is all of them; the dots show how many per colour.
            $byLevel = SalesTask::open()->when($who === 'me', fn ($q) => $q->where('owner_id', $u->id))->selectRaw('level, count(*) n')->groupBy('level')->pluck('n', 'level');
            $t[] = ['n' => (int) $byLevel->sum(), 'key' => 'sales_tasks', 'url' => route('salestasks.index', ['who' => $who === 'me' ? 'me' : 'all']),
                'tone' => ($byLevel['red'] ?? 0) || ($byLevel['orange'] ?? 0) ? 'warn' : 'info',
                'dots' => collect(SalesTask::LEVELS)->mapWithKeys(fn ($l) => [$l => (int) ($byLevel[$l] ?? 0)])->filter()->all()];
            $t[] = ['n' => IncomingMessage::where('status', 'new')->count(), 'key' => 'incoming', 'url' => route('incoming.index'), 'tone' => 'warn'];
            $t[] = ['n' => Deal::open()->when($who === 'me', fn ($q) => $q->where('owner_id', $u->id))->where('check_date', true)->whereNotNull('event_start')->where('event_start', '<=', today()->addDays(14))->count(), 'key' => 'deals_soon', 'url' => route('deals.index'), 'tone' => 'info'];
            $t[] = ['n' => Quote::whereIn('status', ['change'])->when($who === 'me', fn ($q) => $q->where('owner_id', $u->id))->count(), 'key' => 'quote_changes', 'url' => route('quotes.index'), 'tone' => 'warn'];
        }
        if ($u->canUseModule('dryhire', 2)) {
            $t[] = ['n' => $mine(Order::where('status', 'allocation'))->count(), 'key' => 'orders', 'url' => route('orders.index'), 'tone' => 'warn'];
        }
        if ($u->canUseModule('warehouse')) {
            $t[] = ['n' => Order::where('status', 'approved')->whereNull('loaded_at')->where('out_from', '<=', today()->addDay()->endOfDay())->count(), 'key' => 'picks', 'url' => route('w.home'), 'tone' => 'info'];
            $t[] = ['n' => Delivery::where('status', 'open')->count(), 'key' => 'unknown_goods', 'url' => route('warehouse.index'), 'tone' => 'warn'];
        }
        if ($u->canUseModule('sales', 2) || $u->hasRight('orders.approve')) {
            $t[] = ['n' => Charge::where('status', 'pending')->distinct()->count('order_id'), 'key' => 'charges', 'url' => route('warehouse.index'), 'tone' => 'warn'];
        }
        if ($u->canUseModule('factory')) {
            $t[] = ['n' => Task::open()->when($who === 'me', fn ($q) => $q->where('assignee_id', $u->id))->where('due_at', '<=', now()->addDays(7))->count(), 'key' => 'venue_tasks', 'url' => route('factory.index', ['tasks' => $who === 'me' ? 'mine' : null]), 'tone' => 'info'];
        }
        if ($u->canUseModule('factory', 2)) {
            $t[] = ['n' => Booking::where('status', 'request')->count(), 'key' => 'venue_requests', 'url' => route('factory.index'), 'tone' => 'warn'];
        }
        if ($u->canUseModule('afrit6', 2)) {
            $t[] = ['n' => ServiceRequest::where('status', 'pending')->count(), 'key' => 'afrit6_confirm', 'url' => route('afrit6.staff.tasks', ['status' => 'pending']), 'tone' => 'warn'];
        }
        if ($u->hasRight('afrit6.door_codes')) {
            $req = Member::whereNotNull('door_requested_at')->orderBy('door_requested_at')->get(['id']);
            $t[] = ['n' => $req->count(), 'key' => 'door_codes', 'url' => $req->count() === 1 ? route('afrit6.staff.show', $req->first()->id) : route('afrit6.staff.index', ['door' => 1]), 'tone' => 'warn'];
        }
        if ($u->canUseModule('afrit6')) {
            $t[] = ['n' => ServiceRequest::open()->when($who === 'me', fn ($q) => $q->where('assignee_id', $u->id))->where('status', '!=', 'pending')->count(), 'key' => 'afrit6_tasks', 'url' => route('afrit6.staff.tasks', ['who' => $who === 'me' ? 'mine' : null]), 'tone' => 'info'];
        }
        if ($u->canUseModule('companies', 2)) {
            $t[] = ['n' => CustomerAccount::where('status', 'pending')->whereNotNull('email_verified_at')->count(), 'key' => 'web_accounts', 'url' => route('web-accounts.index'), 'tone' => 'warn'];
        }
        $t[] = ['n' => PreInvoice::where('status', 'back')->when($who === 'me', fn ($q) => $q->where('made_by_id', $u->id))->count(), 'key' => 'pf_back', 'url' => route('finance.index', ['f' => 'back']), 'tone' => 'warn'];
        if ($u->canUseModule('preinvoice', 2)) {
            $t[] = ['n' => PreInvoice::where('status', 'check')->count(), 'key' => 'pf_check', 'url' => route('finance.index'), 'tone' => 'warn'];
            // Late payments: one reminder per overdue invoice until the accountant decided what to do with the interest.
            $t[] = ['n' => PreInvoice::where('status', 'sent')->whereDate('due_date', '<', today())->whereDoesntHave('reminder', fn ($q) => $q->whereNotNull('decision'))->count(),
                'key' => 'late_pay', 'url' => route('finance.index', ['f' => 'late']), 'tone' => 'warn'];
        }
        if ($u->canUseModule('preinvoice', 2) || $u->canUseModule('dryhire', 2)) {
            $ready = PreInvoices::readyRental();
            $t[] = ['n' => ($who === 'me' && ! $u->canUseModule('preinvoice', 2) ? $ready->where('handed_by_id', $u->id) : $ready)->count(), 'key' => 'to_invoice', 'url' => route('finance.index').'#ready', 'tone' => 'info'];
        }

        return array_values(array_filter($t, fn ($x) => $x['n'] > 0 || ! empty($x['note'])));
    }

    /** Today in motion: what goes out, comes back, and today's bookings. */
    private function motion(User $u, string $type, string $tab): array
    {
        $rows = collect();
        if ($u->canUseModule('dryhire') || $u->canUseModule('warehouse')) {
            $m = WallData::moves(today(), today());
            foreach (['out', 'in'] as $dir) {
                foreach ($m[$dir] as $r) {
                    $rows->push(['name' => $r['job'], 'who' => $r['who'], 'type' => str_contains($r['job'], 'Afrit 6') ? 'afrit6' : 'dryhire', 'tab' => $dir, 'time' => ! empty($r['no_time']) ? '—' : $r['at']->format('H:i'), 'st' => $r['st'], 'at' => $r['at'], 'url' => $r['url'] ?? null]);
                }
            }
        }
        if ($u->canUseModule('factory')) {
            foreach (Booking::whereIn('status', ['option', 'confirmed'])->whereDate('start_date', '<=', today()->addDays(3))->whereDate('start_date', '>=', today()->subDays(14))->get() as $b) {
                if ($b->firstDay()->lte(today()) && $b->lastDay()->gte(today()) && $b->holds()) {
                    $part = today()->lt($b->start_date) ? 'buildup' : (today()->gt($b->lastEventDay()) ? 'breakdown' : 'event');
                    $rows->push(['name' => 'Cluk Factory · '.$b->title, 'who' => $b->customerName(), 'type' => 'factory', 'tab' => 'booking', 'time' => $part === 'event' ? $b->doors_time : $b->load_in_time, 'st' => 'factory_'.$part, 'at' => today()->setTimeFromTimeString($b->load_in_time ?: '08:00'), 'url' => route('factory.show', $b)]);
                }
            }
        }
        if ($u->canUseModule('afrit6')) {
            foreach (RoomBooking::with('member.company')->live()->whereDate('date', today())->get() as $rb) {
                $rows->push(['name' => __('dashboard.room_booking'), 'who' => $rb->member?->companyName() ?? '—', 'type' => 'afrit6', 'tab' => 'booking', 'time' => '', 'st' => 'room', 'at' => today()->setTime(8, 0)]);
            }
        }
        $counts = ['all' => $rows->count(), 'out' => $rows->where('tab', 'out')->count(), 'in' => $rows->where('tab', 'in')->count(), 'booking' => $rows->where('tab', 'booking')->count()];
        $rows = $rows->when($type !== 'all', fn ($c) => $c->where('type', $type))->when($tab !== 'all', fn ($c) => $c->where('tab', $tab))->sortBy('at')->values();

        return ['rows' => $rows, 'counts' => $counts];
    }

    private function pipeline(): array
    {
        $open = Deal::open()->get(['stage', 'value_cents']);
        $won = Deal::where('stage', 'won')->whereBetween('closed_at', [today()->startOfMonth(), today()->endOfMonth()->endOfDay()])->get(['value_cents']);

        return [
            ['key' => 'request', 'n' => $open->whereIn('stage', ['new', 'qualified'])->count(), 'cents' => (int) $open->whereIn('stage', ['new', 'qualified'])->sum('value_cents')],
            ['key' => 'quote', 'n' => $open->where('stage', 'proposal')->count(), 'cents' => (int) $open->where('stage', 'proposal')->sum('value_cents')],
            ['key' => 'followup', 'n' => $open->where('stage', 'negotiation')->count(), 'cents' => (int) $open->where('stage', 'negotiation')->sum('value_cents')],
            ['key' => 'won', 'n' => $won->count(), 'cents' => (int) $won->sum('value_cents')],
        ];
    }

    /** The next 7 days: the hall and the free desks. */
    private function week(User $u): array
    {
        $days = collect(range(0, 6))->map(fn ($i) => today()->addDays($i));
        $w = ['days' => $days];
        if ($u->canUseModule('factory')) {
            $occ = Booking::occupancy($days->first(), $days->last());
            $w['factory'] = $days->map(fn (Carbon $d) => ($o = $occ[$d->toDateString()] ?? null) ? ($o['booking']->status === 'option' ? 'option' : 'booked') : 'free')->all();
            $w['factory_n'] = collect($occ)->map(fn ($o) => $o['booking']->id)->unique()->count();
        }
        if ($u->canUseModule('afrit6')) {
            $ids = Space::desks()->pluck('id');
            $total = $ids->count();
            $w['desks'] = $days->map(function (Carbon $d) use ($ids, $total) {
                if ($d->isWeekend()) {
                    return null;
                }
                $taken = DeskRental::activeOn($d)->whereIn('space_id', $ids)->distinct()->count('space_id') + DeskBooking::live()->whereDate('date', $d)->distinct()->count('space_id');

                return max(0, $total - $taken);
            })->all();
            $w['desks_total'] = $total;
        }

        return $w;
    }
}
