@php($max = max(1, max($s['days'] ?: [0])))
<x-layouts.app :title="__('webstats.title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('management') }}">{{ mb_strtoupper(__('nav.items.management')) }}</a> / {{ mb_strtoupper(__('webstats.title')) }}</div>
            <h1>{{ __('webstats.title') }}</h1>
            <p class="muted">{{ __('webstats.intro', ['months' => \App\Services\Website\WebActivity::KEEP_MONTHS]) }}</p>
        </div>
        <nav class="chips">@foreach (\App\Http\Controllers\WebStatsController::PERIODS as $d)<a @class(['chip', 'chip-on' => $days === $d]) href="{{ route('management.website', ['d' => $d]) }}">{{ __('webstats.last_days', ['n' => $d]) }}</a>@endforeach</nav>
    </div>

    <div class="tiles mg-kpis">
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('webstats.kpi.visitors')) }}</span><b class="tile-v">{{ number_format($s['visitors'], 0, ',', '.') }}</b><small class="muted">{{ __('webstats.kpi.linked', ['n' => $s['linked']]) }}</small></div>
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('webstats.kpi.views')) }}</span><b class="tile-v">{{ number_format($s['views'], 0, ',', '.') }}</b><small class="muted">{{ implode(' · ', array_map(fn ($d, $n) => __('webstats.devices.'.$d).' '.$n, array_keys($s['devices']), $s['devices'])) ?: '—' }}</small></div>
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('webstats.kpi.searches')) }}</span><b class="tile-v">{{ number_format($s['searches_n'], 0, ',', '.') }}</b><small class="muted">{{ __('webstats.kpi.no_results', ['n' => array_sum($s['no_results'])]) }}</small></div>
        <a class="tile" href="{{ route('incoming.index', ['channel' => 'website']) }}"><span class="tile-k">{{ mb_strtoupper(__('webstats.kpi.requests')) }}</span><b class="tile-v">{{ $s['requests_n'] }}</b><small class="muted">{{ __('webstats.kpi.requests_s') }}</small></a>
    </div>

    <section class="card mt">
        <h2>{{ __('webstats.per_day') }}</h2>
        <p class="muted small">{{ __('webstats.per_day_hint') }}</p>
        <div class="wd-bars" role="img" aria-label="{{ __('webstats.per_day') }}">@foreach ($s['days'] as $day => $n)<span style="height:{{ round($n / $max * 100) }}%" data-t="{{ \Illuminate\Support\Carbon::parse($day)->format('d/m') }}: {{ $n }}"></span>@endforeach</div>
        <div class="wd-axis"><span>{{ $s['from']->format('d/m') }}</span><span>{{ today()->format('d/m') }}</span></div>
    </section>

    <div class="split mt">
        <div class="stack">
            <section class="card">
                <h2>{{ __('webstats.no_results') }}</h2>
                <p class="muted small">{{ __('webstats.no_results_hint') }}</p>
                @if ($s['no_results'])<ul class="wd-list">@foreach ($s['no_results'] as $w => $n)<li><span>{{ $w }}</span><b>{{ $n }}</b></li>@endforeach</ul>@else<p class="muted">{{ __('webstats.nothing') }}</p>@endif
            </section>
            <section class="card">
                <h2>{{ __('webstats.products') }}</h2>
                @if ($s['products'])<ul class="wd-list">@foreach ($s['products'] as $p)<li><span>{{ $p['name'] }} <small class="muted">· {{ __('webstats.sections.'.$p['section']) }}</small></span><b>{{ $p['n'] }}</b></li>@endforeach</ul>@else<p class="muted">{{ __('webstats.nothing') }}</p>@endif
            </section>
        </div>
        <aside class="stack">
            <section class="card">
                <h2>{{ __('webstats.request_sources') }}</h2>
                <p class="muted small">{{ __('webstats.request_sources_hint') }}</p>
                @if ($s['request_sources'])<ul class="wd-list">@foreach ($s['request_sources'] as $src => $n)<li><span>{{ $src }}</span><b>{{ $n }}</b></li>@endforeach</ul>@else<p class="muted">{{ __('webstats.nothing') }}</p>@endif
            </section>
            <section class="card">
                <h2>{{ __('webstats.sources') }}</h2>
                @if ($s['sources'])<ul class="wd-list">@foreach ($s['sources'] as $src => $n)<li><span>{{ $src }}</span><b>{{ $n }}</b></li>@endforeach</ul>@else<p class="muted">{{ __('webstats.nothing') }}</p>@endif
            </section>
            <section class="card">
                <h2>{{ __('webstats.searches') }}</h2>
                @if ($s['searches'])<ul class="wd-list">@foreach ($s['searches'] as $w => $n)<li><span>{{ $w }}</span><b>{{ $n }}</b></li>@endforeach</ul>@else<p class="muted">{{ __('webstats.nothing') }}</p>@endif
            </section>
        </aside>
    </div>
</x-layouts.app>
