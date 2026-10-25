@php($hour = (int) now()->format('G'))
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($q = fn ($k, $v) => route('dashboard', array_filter(array_merge(['type' => $type, 'tab' => $tab, 'who' => $who], [$k => $v]), fn ($x, $key) => $x && ! ($key === 'who' ? $x === 'me' : $x === 'all'), ARRAY_FILTER_USE_BOTH)))
@php($can = fn ($m, $l = 1) => $user->canUseModule($m, $l))
<x-layouts.app :title="__('nav.items.dashboard')">
    <div class="pagehead">
        <div>
            <div class="kicker">CLUK / {{ mb_strtoupper(__('nav.overview')) }}</div>
            <h1>{{ __($hour < 12 ? 'nav.greeting_morning' : ($hour < 18 ? 'nav.greeting_afternoon' : 'nav.greeting_evening'), ['name' => $user->first_name]) }}</h1>
            <p class="muted">{{ __('nav.today') }}</p>
        </div>
        <details class="db-new">
            <summary class="primary">+ {{ __('nav.new_request') }}</summary>
            <div class="db-new-menu">
                <b class="db-new-title">{{ __('dashboard.new.title') }}</b>
                @php($groups = [
                    'commercial' => array_filter([
                        $can('sales', 2) ? ['deals.create', 'deal'] : null,
                        $can('sales', 2) && Route::has('psales.create') ? ['psales.create', 'sales_quote'] : null,
                        $can('companies', 2) ? ['companies.create', 'company'] : null,
                        $can('factory', 2) ? ['factory.create', 'venue'] : null,
                    ]),
                    'operations' => array_filter([
                        $can('purchasing', 2) && Route::has('purchasing.create') ? ['purchasing.create', 'purchase'] : null,
                        $can('warehouse') ? ['w.home', 'warehouse'] : null,
                        $can('warehouse') ? ['w.delivery', 'delivery'] : null,
                        $user->hasRight('sales.edit_items') ? ['psales.items', 'sales_item'] : null,
                        $can('dryhire', 2) ? ['equipment.create', 'item'] : null,
                    ]),
                    'afrit6' => array_filter([
                        $can('afrit6') ? ['afrit6.staff.tasks', 'member_task'] : null,
                        $can('afrit6', 2) ? ['afrit6.staff.index', 'member'] : null,
                    ]),
                ])
                @foreach ($groups as $g => $items)
                    @if ($items)
                        <div class="db-new-group"><small>{{ mb_strtoupper(__('dashboard.new.groups.'.$g)) }}</small>
                            @foreach ($items as [$route, $key])
                                @if (Route::has($route))<a href="{{ route($route) }}"><b>{{ __('dashboard.new.items.'.$key) }}</b><span>{{ __('dashboard.new.items.'.$key.'_s') }}</span></a>@endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </details>
    </div>
    @include('partials.flash')

    @if ($kpi)
        <div class="tiles">
            @isset($kpi['jobs'])<a class="tile tile-ready" href="{{ route('orders.index') }}"><span class="tile-k">{{ mb_strtoupper(__('dashboard.kpi.jobs', ['month' => now()->locale(app()->getLocale())->translatedFormat('F')])) }}</span><b class="tile-v">{{ $eur($kpi['jobs']['cents']) }}</b><small class="muted">{{ trans_choice('dashboard.kpi.jobs_s', $kpi['jobs']['n'], ['n' => $kpi['jobs']['n']]) }}</small></a>@endisset
            @isset($kpi['desks'])<a class="tile" href="{{ route('afrit6.staff.index') }}"><span class="tile-k">{{ mb_strtoupper(__('dashboard.kpi.desks')) }}</span><b class="tile-v">{{ $kpi['desks']['taken'] }} <small>/ {{ $kpi['desks']['total'] }}</small></b><small class="muted">{{ trans_choice('dashboard.kpi.desks_s', $kpi['desks']['ending'], ['n' => $kpi['desks']['ending']]) }}</small></a>@endisset
            @isset($kpi['quotes'])<a class="tile tile-waiting" href="{{ route('quotes.index') }}"><span class="tile-k">{{ mb_strtoupper(__('dashboard.kpi.quotes')) }}</span><b class="tile-v">{{ $kpi['quotes']['n'] }} <small>/ {{ $eur($kpi['quotes']['cents']) }}</small></b><small class="muted">{{ trans_choice('dashboard.kpi.quotes_s', $kpi['quotes']['old'], ['n' => $kpi['quotes']['old']]) }}</small></a>@endisset
            @isset($kpi['warehouse'])<a class="tile" href="{{ route('warehouse.index') }}"><span class="tile-k">{{ mb_strtoupper(__('dashboard.kpi.warehouse')) }}</span><b class="tile-v">{{ $kpi['warehouse']['out'] }} <small>{{ __('dashboard.kpi.out') }} /</small> {{ $kpi['warehouse']['back'] }} <small>{{ __('dashboard.kpi.back') }}</small></b><small class="muted">{{ trans_choice('dashboard.kpi.checks', $kpi['warehouse']['checks'], ['n' => $kpi['warehouse']['checks']]) }}</small></a>@endisset
        </div>
    @endif

    <div class="split">
        <div class="stack">
            <section class="card card-flush">
                <div class="card-head pad">
                    <h2>{{ __('dashboard.motion') }} <span class="muted small">{{ now()->locale(app()->getLocale())->translatedFormat('j M') }}</span></h2>
                    <nav class="chips">
                        @foreach (['all', 'dryhire', 'factory', 'afrit6'] as $t)<a @class(['chip', 'chip-on' => $type === $t]) href="{{ $q('type', $t) }}">{{ __('dashboard.types.'.$t) }}</a>@endforeach
                    </nav>
                </div>
                <nav class="tabs pad">
                    @foreach (['all', 'out', 'in', 'booking'] as $t)<a @class(['tab', 'tab-on' => $tab === $t]) href="{{ $q('tab', $t) }}">{{ __('dashboard.tabs.'.$t) }} <small class="muted">{{ $motion['counts'][$t] }}</small></a>@endforeach
                </nav>
                @if ($motion['rows']->isEmpty())
                    <p class="muted pad">{{ __('dashboard.nothing') }}</p>
                @else
                    <table class="table">
                        <thead><tr><th>{{ __('dashboard.cols.job') }}</th><th>{{ __('dashboard.cols.activity') }}</th><th>{{ __('dashboard.cols.time') }}</th><th>{{ __('dashboard.cols.status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($motion['rows'] as $r)
                                <tr @class(['db-row' => ! empty($r['url'])])><td><b>@if (! empty($r['url']))<a class="db-row-link" href="{{ $r['url'] }}">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</b><br><small class="muted">{{ $r['who'] }}</small></td>
                                    <td>{{ __('dashboard.types.'.$r['type']) }} · {{ __('dashboard.tabs.'.$r['tab']) }}</td><td>{{ $r['time'] }}</td><td><span class="pill">{{ __('dashboard.st.'.$r['st']) }}</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            @if ($pipeline)
                <section class="card">
                    <div class="card-head"><div><h2>{{ __('dashboard.pipeline') }}</h2><p class="muted small">{{ __('dashboard.pipeline_s') }}</p></div><a class="btn" href="{{ route('deals.index') }}">{{ __('dashboard.view_deals') }}</a></div>
                    <div class="db-pipe">
                        @foreach ($pipeline as $i => $p)
                            <div class="db-step"><small>0{{ $i + 1 }} · {{ mb_strtoupper(__('dashboard.pipe.'.$p['key'])) }}</small>@if ($money)<b>{{ $eur($p['cents']) }}</b>@endif<span class="muted">{{ trans_choice('dashboard.pipe.'.$p['key'].'_n', $p['n'], ['n' => $p['n']]) }}</span></div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($shortages !== null)
                <section class="card">
                    <div class="card-head"><h2>{{ __('dashboard.stock') }}</h2><a class="btn" href="{{ route('orders.index') }}">{{ __('nav.items.orders') }}</a></div>
                    @forelse ($shortages as $o)
                        <a class="db-short" href="{{ route('orders.show', $o) }}#subhire"><b>{{ $o->title }} · {{ $o->customerName() }}</b><span class="muted">{{ __('dashboard.shortage', ['from' => $o->out_from->format('d/m'), 'until' => $o->out_until->format('d/m')]) }}</span><span class="db-short-go">→</span></a>
                    @empty
                        <p class="muted">{{ __('dashboard.stock_ok') }}</p>
                    @endforelse
                    @if ($shortages->isNotEmpty())<p class="muted small">{{ __('dashboard.stock_hint') }}</p>@endif
                </section>
            @endif
        </div>

        <aside class="stack">
            <section class="card">
                <div class="card-head"><h2>{{ __('nav.your_tasks') }}</h2>
                    <nav class="chips"><a @class(['chip', 'chip-on' => $who === 'me']) href="{{ $q('who', 'me') }}">{{ __('dashboard.mine') }}</a><a @class(['chip', 'chip-on' => $who === 'all']) href="{{ $q('who', 'all') }}">{{ __('dashboard.everyone') }}</a></nav>
                </div>
                @forelse ($tasks as $t)
                    <a class="db-task db-{{ $t['tone'] }}" href="{{ $t['url'] }}"><b>{{ $t['n'] }}</b><span>{{ __('dashboard.tasks.'.$t['key']) }}@if (! empty($t['dots']))<span class="st-dots">@foreach ($t['dots'] as $lvl => $n)<span title="{{ __('salestasks.levels.'.$lvl) }}"><i class="st-dot st-{{ $lvl }}"></i>{{ $n }}</span>@endforeach</span>@endif<small class="muted">{{ __('dashboard.tasks.'.$t['key'].'_s') }}@if (! empty($t['late'])) · <b class="txt-warn">{{ trans_choice('dashboard.tasks.late', $t['late'], ['n' => $t['late']]) }}</b>@endif @if (! empty($t['note']))<br><i>{{ $t['note'] }}</i>@endif</small></span></a>
                @empty
                    <p class="muted">{{ __('nav.no_tasks') }}</p>
                @endforelse
            </section>

            @if (isset($week['factory']) || isset($week['desks']))
                <section class="card">
                    <h2>{{ __('dashboard.places') }} <span class="muted small">{{ $week['days']->first()->format('j') }}–{{ $week['days']->last()->locale(app()->getLocale())->translatedFormat('j M') }}</span></h2>
                    @isset($week['factory'])
                        <div class="db-place"><b>Cluk Factory</b><small class="muted">{{ trans_choice('dashboard.bookings_n', $week['factory_n'], ['n' => $week['factory_n']]) }}</small></div>
                        <div class="db-days">@foreach ($week['days'] as $i => $d)<span class="db-cell db-{{ $week['factory'][$i] }}"><small>{{ $d->locale(app()->getLocale())->translatedFormat('D') }}</small>{{ $d->day }}</span>@endforeach</div>
                    @endisset
                    @isset($week['desks'])
                        <div class="db-place"><b>Cluk Workspace</b><small class="muted">{{ __('dashboard.desks_free', ['total' => $week['desks_total']]) }}</small></div>
                        <div class="db-days">@foreach ($week['days'] as $i => $d)@php($n = $week['desks'][$i])<span @class(['db-cell', 'db-free' => $n !== null && $n > 0, 'db-booked' => $n === 0, 'db-closed' => $n === null])><small>{{ $d->locale(app()->getLocale())->translatedFormat('D') }}</small>{{ $n ?? '—' }}</span>@endforeach</div>
                    @endisset
                    <p class="muted small">{{ __('dashboard.legend') }}</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
