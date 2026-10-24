<?php

namespace App\Http\Controllers;

use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\Space;
use App\Models\Company;
use App\Models\Finance\PreInvoice;
use App\Models\ProductSales\SalesQuote;
use App\Models\Sales\Deal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Management: the money. Revenue is what went through pre-invoicing to OkiOki
 * (invoiced or paid), excl. VAT, on the invoice date. Margin uses the purchase
 * and sub-hire costs on each proforma.
 */
class ManagementController extends Controller
{
    public const PERIODS = ['month', 'ytd', '12m'];

    public const MODULES = ['dryhire', 'factory', 'sales', 'afrit6', 'warehouse', 'other'];

    /** Deal stages shown as "quotes in progress", in order. */
    public const STAGES = ['new', 'qualified', 'proposal', 'negotiation'];

    public function __invoke(Request $request): View
    {
        $period = in_array($request->query('p'), self::PERIODS, true) ? $request->query('p') : 'ytd';
        $sort = $request->query('sort') === 'growth' ? 'growth' : 'revenue';
        [$from, $to, $lyFrom, $lyTo] = self::range($period);

        // Everything invoiced in the last two years is small enough to work on in memory.
        $all = PreInvoice::with(['company', 'member.company'])->whereIn('status', ['sent', 'paid'])->whereNotNull('invoice_date')
            ->whereDate('invoice_date', '>=', today()->subYears(2)->startOfYear())->get();
        $cur = $all->filter(fn ($p) => $p->invoice_date->between($from, $to));
        $prev = $all->filter(fn ($p) => $p->invoice_date->between($lyFrom, $lyTo));
        $net = fn (Collection $c) => (int) $c->sum('net_cents');
        $margin = fn (Collection $c) => $net($c) > 0 ? ($net($c) - (int) $c->sum('cost_cents')) / $net($c) * 100 : null;

        // Quotes in progress: the open deals, plus product sales quotes not linked to a deal.
        $deals = Deal::open()->get(['id', 'stage', 'value_cents', 'owner_id']);
        $loose = SalesQuote::whereIn('status', ['draft', 'sent'])->whereNull('deal_id')->get(['id', 'status', 'net_cents', 'owner_id']);
        $pipe = collect(self::STAGES)->map(function (string $s) use ($deals, $loose) {
            $d = $deals->where('stage', $s);
            $extra = $s === 'proposal' ? $loose->where('status', 'sent') : ($s === 'new' ? $loose->where('status', 'draft') : collect());
            $cents = (int) $d->sum('value_cents') + (int) $extra->sum('net_cents');

            return ['stage' => $s, 'n' => $d->count() + $extra->count(), 'cents' => $cents, 'pct' => Deal::PROBABILITY[$s], 'weighted' => (int) round($cents * Deal::PROBABILITY[$s] / 100)];
        });

        $check = PreInvoice::where('status', 'check')->get(['gross_cents']);
        $unpaid = PreInvoice::where('status', 'sent')->get(['gross_cents', 'due_date', 'company_id', 'member_id']);
        $overdue = $unpaid->filter(fn ($p) => $p->due_date && $p->due_date->lt(today()));
        $desks = Space::desks()->pluck('id');
        $rentals = DeskRental::activeOn(today())->whereIn('space_id', $desks)->get(['space_id', 'monthly_price_cents']);
        $allRentals = DeskRental::activeOn(today())->sum('monthly_price_cents');

        // Revenue per month: this year against last year.
        $year = today()->year;
        $months = collect(range(1, 12))->map(function (int $m) use ($all, $year) {
            $sum = fn (int $y) => (int) $all->filter(fn ($p) => $p->invoice_date->year === $y && $p->invoice_date->month === $m)->sum('net_cents');
            $started = Carbon::create($year, $m, 1)->lte(today());

            return ['m' => $m, 'ty' => $started ? $sum($year) : null, 'ly' => $sum($year - 1)];
        });
        $sel = (int) $request->query('m', today()->month);
        $sel = $sel >= 1 && $sel <= 12 ? $sel : today()->month;

        $modules = collect(self::MODULES)->map(fn ($m) => ['key' => $m, 'cents' => $net($cur->where('module', $m)), 'ly' => $net($prev->where('module', $m))])
            ->filter(fn ($m) => $m['cents'] || $m['ly'])->sortByDesc('cents')->values();

        return view('management.index', [
            'period' => $period, 'sort' => $sort, 'from' => $from, 'to' => $to, 'lyFrom' => $lyFrom, 'lyTo' => $lyTo,
            'kpi' => [
                'revenue' => $net($cur), 'revenue_ly' => $net($prev),
                'margin' => $margin($cur), 'margin_ly' => $margin($prev), 'margin_cents' => $net($cur) - (int) $cur->sum('cost_cents'),
                'pipe_n' => $pipe->sum('n'), 'pipe' => $pipe->sum('cents'), 'pipe_w' => $pipe->sum('weighted'),
                'check_n' => $check->count(), 'check' => (int) $check->sum('gross_cents'),
                'unpaid' => (int) $unpaid->sum('gross_cents'), 'overdue' => (int) $overdue->sum('gross_cents'),
                'unpaid_customers' => $overdue->map(fn ($p) => $p->company_id ? 'c'.$p->company_id : 'm'.$p->member_id)->unique()->count(),
                'a6' => (int) $allRentals, 'desks' => $rentals->pluck('space_id')->unique()->count(), 'desks_total' => $desks->count(),
            ],
            'months' => $months, 'sel' => $sel, 'max' => max(1, (int) $months->flatMap(fn ($m) => [$m['ty'] ?? 0, $m['ly']])->max()),
            'modules' => $modules,
            'reps' => $this->reps($cur, $prev, $from, $to, $deals, $sort),
            'pipe' => $pipe,
            'top' => $this->top($cur, $prev),
        ]);
    }

    /** [from, to, last-year from, last-year to] for a period. */
    public static function range(string $period): array
    {
        $to = today()->endOfDay();
        $from = match ($period) {
            'month' => today()->startOfMonth(),
            '12m' => today()->subMonths(12)->addDay()->startOfDay(),
            default => today()->startOfYear(),
        };

        return [$from, $to, $from->copy()->subYear(), $to->copy()->subYear()];
    }

    /** Revenue per salesperson (only people marked as commercial), plus "Counter & Online". */
    private function reps(Collection $cur, Collection $prev, Carbon $from, Carbon $to, Collection $open, string $sort): array
    {
        $people = User::where('is_commercial', true)->orderBy('first_name')->get();
        $ids = $people->pluck('id')->all();
        $closed = Deal::whereIn('stage', ['won', 'lost'])->whereBetween('closed_at', [$from, $to])->get(['owner_id', 'stage']);
        $total = max(1, (int) $cur->sum('net_cents'));
        // Companies each person is the account owner of (active ones).
        $owned = Company::active()->whereNotNull('account_owner_id')->selectRaw('account_owner_id, count(*) n')->groupBy('account_owner_id')->pluck('n', 'account_owner_id');
        $rows = $people->map(function (User $u) use ($cur, $prev, $closed, $open, $total, $owned) {
            $v = (int) $cur->where('owner_id', $u->id)->sum('net_cents');
            $won = $closed->where('owner_id', $u->id)->where('stage', 'won')->count();
            $decided = $closed->where('owner_id', $u->id)->count();

            return ['user' => $u, 'cents' => $v, 'ly' => (int) $prev->where('owner_id', $u->id)->sum('net_cents'), 'share' => $v / $total,
                'won' => $won, 'rate' => $decided ? $won / $decided : null, 'avg' => $won ? intdiv($v, $won) : null,
                'open' => (int) $open->where('owner_id', $u->id)->sum('value_cents'), 'companies' => (int) ($owned[$u->id] ?? 0)];
        });
        $rows = ($sort === 'growth'
            ? $rows->sortByDesc(fn ($r) => $r['ly'] ? $r['cents'] / $r['ly'] : ($r['cents'] ? PHP_INT_MAX : 0))
            : $rows->sortByDesc('cents'))->values();
        $none = fn (Collection $c) => (int) $c->filter(fn ($p) => ! in_array($p->owner_id, $ids, true))->sum('net_cents');
        $counter = ['user' => null, 'cents' => $none($cur), 'ly' => $none($prev), 'share' => $none($cur) / $total,
            'companies' => Company::active()->where(fn ($q) => $q->whereNull('account_owner_id')->orWhereNotIn('account_owner_id', $ids))->count()];

        return ['rows' => $rows->all(), 'counter' => $counter, 'max' => max(1, (int) $rows->max('cents'), $counter['cents'])];
    }

    /** The five biggest customers in the period. */
    private function top(Collection $cur, Collection $prev): array
    {
        $key = fn ($p) => $p->company_id ? 'c'.$p->company_id : ($p->member_id ? 'm'.$p->member_id : null);

        return $cur->filter($key)->groupBy($key)->map(fn (Collection $g, $k) => [
            'name' => $g->first()->customerName(),
            'company_id' => $g->first()->company_id,
            'mix' => $g->pluck('module')->unique()->values()->all(),
            'cents' => (int) $g->sum('net_cents'),
            'ly' => (int) $prev->filter(fn ($p) => $key($p) === $k)->sum('net_cents'),
        ])->sortByDesc('cents')->take(5)->values()->all();
    }
}
