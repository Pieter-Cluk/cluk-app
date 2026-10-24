@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($k = function ($c) { $e = $c / 100; return '€ '.($e >= 1000000 ? number_format($e / 1000000, 2, ',', '.').' M' : ($e >= 1000 ? preg_replace('/,0$/', '', number_format($e / 1000, $e >= 100000 ? 0 : 1, ',', '.')).'k' : number_format($e, 0, ',', '.'))); })
@php($delta = function ($a, $b) { if (! $b) { return $a ? ['txt' => __('management.new'), 'cls' => 'up'] : null; } $d = ($a - $b) / $b * 100; return ['txt' => ($d >= 0 ? '▲ ' : '▼ ').number_format(abs($d), 1, ',', '').'%', 'cls' => $d >= 0 ? 'up' : 'down']; })
@php($url = fn (array $x) => route('management', array_filter(array_merge(['p' => $period, 'sort' => $sort, 'm' => request('m')], $x), fn ($v) => $v !== null && $v !== 'ytd' && $v !== 'revenue')))
@php($loc = app()->getLocale())
<x-layouts.app :title="__('nav.items.management')">
    <div class="pagehead">
        <div>
            <div class="kicker">CLUK / {{ mb_strtoupper(__('nav.items.management')) }}</div>
            <h1>{{ __('management.title') }}</h1>
            <p class="muted">{{ __('management.period_text', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'lyfrom' => $lyFrom->format('d/m/Y'), 'lyto' => $lyTo->format('d/m/Y')]) }}</p>
        </div>
        <nav class="chips">@foreach (\App\Http\Controllers\ManagementController::PERIODS as $p)<a @class(['chip', 'chip-on' => $period === $p]) href="{{ $url(['p' => $p]) }}">{{ __('management.periods.'.$p) }}</a>@endforeach</nav>
    </div>

    <div class="tiles mg-kpis">
        @php($d = $delta($kpi['revenue'], $kpi['revenue_ly']))
        <a class="tile" href="{{ route('finance.index', ['f' => 'all']) }}"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.revenue')) }}</span><b class="tile-v">{{ $k($kpi['revenue']) }}</b><small class="muted">@if ($d)<span class="{{ $d['cls'] }}">{{ $d['txt'] }}</span> @endif{{ __('management.kpi.vs', ['amount' => $k($kpi['revenue_ly'])]) }}</small></a>
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.margin')) }}</span><b class="tile-v">{{ $kpi['margin'] === null ? '—' : number_format($kpi['margin'], 1, ',', '').'%' }}</b>
            <small class="muted">@if ($kpi['margin'] !== null && $kpi['margin_ly'] !== null)@php($pts = $kpi['margin'] - $kpi['margin_ly'])<span class="{{ $pts >= 0 ? 'up' : 'down' }}">{{ $pts >= 0 ? '▲' : '▼' }} {{ __('management.kpi.pts', ['n' => number_format(abs($pts), 1, ',', '')]) }}</span> @endif{{ __('management.kpi.margin_s', ['amount' => $k($kpi['margin_cents'])]) }}</small></div>
        <a class="tile" href="{{ route('deals.index') }}"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.pipe')) }}</span><b class="tile-v">{{ $k($kpi['pipe']) }}</b><small class="muted">{{ trans_choice('management.kpi.pipe_s', $kpi['pipe_n'], ['n' => $kpi['pipe_n'], 'amount' => $k($kpi['pipe_w'])]) }}</small></a>
        <a class="tile tile-waiting" href="{{ route('finance.index') }}"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.check')) }}</span><b class="tile-v">{{ $kpi['check_n'] }}</b><small class="muted">{{ __('management.kpi.check_s', ['amount' => $k($kpi['check'])]) }}</small></a>
        <a class="tile" href="{{ route('finance.index', ['f' => 'sent']) }}"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.unpaid')) }}</span><b class="tile-v">{{ $k($kpi['unpaid']) }}</b><small class="muted">@if ($kpi['overdue'])<span class="down">{{ __('management.kpi.overdue', ['amount' => $k($kpi['overdue'])]) }}</span> · {{ trans_choice('management.kpi.customers', $kpi['unpaid_customers'], ['n' => $kpi['unpaid_customers']]) }}@else{{ __('management.kpi.none_overdue') }}@endif</small></a>
        <a class="tile" href="{{ route('afrit6.staff.index') }}"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.a6')) }}</span><b class="tile-v">{{ $k($kpi['a6']) }}</b><small class="muted">{{ __('management.kpi.a6_s', ['n' => $kpi['desks'], 'total' => $kpi['desks_total']]) }}</small></a>
        @php($webV = \Illuminate\Support\Facades\DB::table('web_events')->where('created_at', '>=', today()->subDays(29))->distinct()->count('visitor_id'))
        @php($webR = \Illuminate\Support\Facades\DB::table('web_events')->where('created_at', '>=', today()->subDays(29))->whereIn('type', ['request', 'order'])->count())
        @php($noOwner = \App\Models\Company::active()->where('is_customer', true)->whereNull('account_owner_id')->count())
        @php($noOwnerActive = \App\Models\Company::active()->where('is_customer', true)->whereNull('account_owner_id')->whereIn('id', \Illuminate\Support\Facades\DB::table('quotes')->where('created_at', '>=', now()->subYears(2))->whereNotNull('company_id')->select('company_id'))->count())
        <a @class(['tile', 'tile-waiting' => $noOwner > 0]) href="{{ route('companies.index', ['view' => 'customers', 'owner' => 'none']) }}"><span class="tile-k">{{ mb_strtoupper(__('management.kpi.no_owner')) }}</span><b class="tile-v">{{ $noOwner }}</b><small class="muted">{{ trans_choice('management.kpi.no_owner_s', $noOwnerActive, ['n' => $noOwnerActive]) }}</small></a>
        <a class="tile" href="{{ route('management.website') }}"><span class="tile-k">{{ mb_strtoupper(__('webstats.title')) }}</span><b class="tile-v">{{ $webV }}</b><small class="muted">{{ __('webstats.tile', ['n' => $webR]) }}</small></a>
    </div>

    <section class="card mt">
        <div class="card-head"><div><h2>{{ __('management.months_title') }}</h2><p class="muted">{{ __('management.months_hint') }}</p></div>
            <div class="mg-legend"><span><i class="mg-ty"></i>{{ now()->year }}</span><span><i class="mg-ly"></i>{{ now()->year - 1 }}</span></div></div>
        @php($s = $months[$sel - 1])
        @php($sd = $s['ty'] === null ? null : $delta($s['ty'], $s['ly']))
        <p class="mg-sel"><b>{{ ucfirst(\Illuminate\Support\Carbon::create(null, $sel, 1)->locale($loc)->translatedFormat('F')) }}</b> · {{ now()->year }}: <b>{{ $s['ty'] === null ? __('management.not_started') : $k($s['ty']) }}</b> · {{ now()->year - 1 }}: {{ $k($s['ly']) }} @if ($sd)<span class="{{ $sd['cls'] }}">{{ $sd['txt'] }}</span>@endif</p>
        <div class="mg-chart" role="group" aria-label="{{ __('management.months_title') }}">
            @foreach ($months as $m)
                <a @class(['mcol', 'mcol-on' => $m['m'] === $sel]) href="{{ $url(['m' => $m['m']]) }}" aria-label="{{ \Illuminate\Support\Carbon::create(null, $m['m'], 1)->locale($loc)->translatedFormat('F') }}: {{ now()->year }} {{ $m['ty'] === null ? __('management.not_started') : $k($m['ty']) }}, {{ now()->year - 1 }} {{ $k($m['ly']) }}">
                    <span class="mg-bars"><i class="mg-ty" style="height:{{ round(($m['ty'] ?? 0) / $max * 100, 1) }}%"></i><i class="mg-ly" style="height:{{ round($m['ly'] / $max * 100, 1) }}%"></i></span>
                    <small>{{ ucfirst(\Illuminate\Support\Carbon::create(null, $m['m'], 1)->locale($loc)->translatedFormat('M')) }}</small>
                </a>
            @endforeach
        </div>
        <p class="muted small">{{ __('management.months_note', ['date' => today()->format('d/m')]) }}</p>
    </section>

    <div class="mg-two mt">
        <section class="card">
            <h2>{{ __('management.modules_title') }}</h2>
            <p class="muted">{{ __('management.modules_hint', ['period' => __('management.periods.'.$period)]) }}</p>
            @php($mmax = max(1, (int) collect($modules)->max('cents')))
            @forelse ($modules as $m)
                @php($md = $delta($m['cents'], $m['ly']))
                <div class="mg-row"><span>{{ __('finance.modules.'.$m['key']) }}</span><span class="mg-track"><i style="width:{{ round($m['cents'] / $mmax * 100, 1) }}%"></i></span><b>{{ $k($m['cents']) }}</b>@if ($md)<small class="{{ $md['cls'] }}">{{ $md['txt'] }}</small>@else<small></small>@endif</div>
            @empty
                <p class="muted">{{ __('management.empty') }}</p>
            @endforelse
        </section>
        <section class="card">
            <h2>{{ __('management.pipe_title') }}</h2>
            <p class="muted">{{ __('management.pipe_hint', ['amount' => $k($kpi['pipe']), 'n' => $kpi['pipe_n']]) }}</p>
            @php($pmax = max(1, (int) $pipe->max('cents')))
            @foreach ($pipe as $i => $p)
                <div class="mg-row"><span>{{ __('management.stages.'.$p['stage']) }}<br><small class="muted">{{ trans_choice('management.quotes_n', $p['n'], ['n' => $p['n']]) }} · {{ $p['pct'] }}%</small></span><span class="mg-track"><i class="mg-stage-{{ $i + 1 }}" style="width:{{ round($p['cents'] / $pmax * 100, 1) }}%"></i></span><b>{{ $k($p['cents']) }}</b><small class="muted">{{ __('management.weighted', ['amount' => $k($p['weighted'])]) }}</small></div>
            @endforeach
        </section>
    </div>

    <section class="card card-flush mt">
        <div class="pad card-head"><div><h2>{{ __('management.reps_title') }}</h2><p class="muted">{{ __('management.reps_hint', ['period' => __('management.periods.'.$period)]) }}</p></div>
            <nav class="chips">@foreach (['revenue', 'growth'] as $s2)<a @class(['chip', 'chip-on' => $sort === $s2]) href="{{ $url(['sort' => $s2]) }}">{{ __('management.sort.'.$s2) }}</a>@endforeach</nav></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('management.cols.person') }}</th><th>{{ __('management.cols.revenue') }}</th><th class="num">{{ __('management.cols.vs_ly') }}</th><th class="num">{{ __('management.cols.won') }}</th><th class="num">{{ __('management.cols.rate') }}</th><th class="num">{{ __('management.cols.avg') }}</th><th class="num">{{ __('management.cols.open') }}</th><th class="num">{{ __('management.cols.companies') }}</th></tr></thead>
            <tbody>
                @foreach (array_merge($reps['rows'], [$reps['counter']]) as $r)
                    @php($rd = $delta($r['cents'], $r['ly']))
                    <tr>
                        <td>@if ($r['user'])<b>{{ $r['user']->name }}</b><br><small class="muted">{{ $r['user']->title }}</small>@else<b>{{ __('management.counter') }}</b><br><small class="muted">{{ __('management.counter_s') }}</small>@endif</td>
                        <td><div class="mg-rep"><span class="mg-track"><i @class(['mg-none' => ! $r['user']]) style="width:{{ round($r['cents'] / $reps['max'] * 100, 1) }}%"></i></span><b class="nowrap">{{ $k($r['cents']) }}</b><small class="muted">{{ round($r['share'] * 100) }}%</small></div></td>
                        <td class="num">@if ($rd)<span class="{{ $rd['cls'] }}">{{ $rd['txt'] }}</span>@else—@endif</td>
                        <td class="num">{{ $r['user'] ? $r['won'] : '–' }}</td>
                        <td class="num">{{ $r['user'] && $r['rate'] !== null ? round($r['rate'] * 100).'%' : '–' }}</td>
                        <td class="num nowrap">{{ $r['user'] && $r['avg'] !== null ? $k($r['avg']) : '–' }}</td>
                        <td class="num nowrap">{{ $r['user'] ? $k($r['open']) : '–' }}</td>
                        <td class="num">@if ($r['user'])<a href="{{ route('companies.index', ['owner' => $r['user']->id]) }}">{{ $r['companies'] }}</a>@else<span title="{{ __('management.companies_none') }}">{{ $r['companies'] }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <p class="mg-pad muted small">{{ __('management.reps_note') }}</p>
    </section>

    <section class="card card-flush mt">
        <div class="mg-pad"><h2>{{ __('management.top_title') }}</h2><p class="muted">{{ __('management.periods.'.$period) }}</p></div>
        <table class="table">
            <thead><tr><th>{{ __('management.cols.company') }}</th><th class="num">{{ __('management.cols.revenue') }}</th><th class="num">{{ __('management.cols.vs_ly') }}</th></tr></thead>
            <tbody>
                @forelse ($top as $t)
                    @php($td = $delta($t['cents'], $t['ly']))
                    <tr><td>@if ($t['company_id'])<a href="{{ route('companies.show', $t['company_id']) }}"><b>{{ $t['name'] }}</b></a>@else<b>{{ $t['name'] }}</b>@endif<br><small class="muted">{{ collect($t['mix'])->map(fn ($m) => __('finance.modules.'.$m))->implode(' · ') }}</small></td>
                        <td class="num nowrap">{{ $k($t['cents']) }}</td><td class="num">@if ($td)<span class="{{ $td['cls'] }}">{{ $td['txt'] }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">{{ __('management.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
