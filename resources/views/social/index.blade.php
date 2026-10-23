@php($nf = fn ($v) => $v === null ? '—' : ($v >= 100000 ? number_format($v / 1000, 0, ',', '.').'k' : number_format($v, 0, ',', '.')))
@php($delta = function ($a, $b) { if (! $b) { return null; } $p = ($a - $b) / $b * 100; return ['txt' => ($p >= 0 ? '▲ ' : '▼ ').number_format(abs($p), 0, ',', '').'%', 'cls' => $p >= 0 ? 'up' : 'down']; })
@php($k = $d['kpi'])
<x-layouts.app :title="__('social.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.media')) }}</div>
            <h1>{{ __('social.title') }}</h1>
            <p class="muted">{{ __('social.intro', ['from' => $d['from']->format('d/m/Y')]) }}</p>
        </div>
        <div class="inline-form">
            <nav class="chips">@foreach (\App\Http\Controllers\Social\SocialController::PERIODS as $p)<a @class(['chip', 'chip-on' => $d['days'] === $p]) href="{{ route('social.index', ['d' => $p]) }}">{{ __('social.last_days', ['n' => $p]) }}</a>@endforeach</nav>
            <a class="btn" href="{{ route('social.plans') }}">{{ __('social.posts') }}@if ($d['drafts']) <span class="pill">{{ $d['drafts'] }}</span>@endif</a>
            @if ($canPost)<a class="primary" href="{{ route('social.plans.create') }}">{{ __('social.new_post') }}</a>@endif
        </div>
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif

    @if ($d['accounts']->isEmpty())
        <section class="card sm-empty">
            <h2>{{ __('social.empty.title') }}</h2>
            <p class="muted">{{ __('social.empty.text') }}</p>
        </section>
    @else
        <div class="tiles mg-kpis">
            <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('social.kpi.followers')) }}</span><b class="tile-v">{{ $nf($k['followers']) }}</b>
                <small class="muted">@if ($k['followers_delta'])<span class="{{ $k['followers_delta'] >= 0 ? 'up' : 'down' }}">{{ $k['followers_delta'] >= 0 ? '+' : '' }}{{ $nf($k['followers_delta']) }}</span> @endif{{ __('social.kpi.in_period') }}</small></div>
            @php($dv = $delta($k['views'], $k['views_prev']))
            <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('social.kpi.views')) }}</span><b class="tile-v">{{ $nf($k['views']) }}</b><small class="muted">@if ($dv)<span class="{{ $dv['cls'] }}">{{ $dv['txt'] }}</span> @endif{{ __('social.kpi.vs_prev') }}</small></div>
            @php($de = $delta($k['engagements'], $k['engagements_prev']))
            <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('social.kpi.engagements')) }}</span><b class="tile-v">{{ $nf($k['engagements']) }}</b><small class="muted">@if ($de)<span class="{{ $de['cls'] }}">{{ $de['txt'] }}</span> @endif{{ __('social.kpi.vs_prev') }}</small></div>
            <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('social.kpi.rate')) }}</span><b class="tile-v">{{ $k['rate'] === null ? '—' : number_format($k['rate'], 1, ',', '').'%' }}</b><small class="muted">{{ trans_choice('social.kpi.posts', $k['posts'], ['n' => $k['posts']]) }}</small></div>
            <a class="tile" href="{{ route('management.website') }}"><span class="tile-k">{{ mb_strtoupper(__('social.kpi.website')) }}</span><b class="tile-v">{{ $d['web']['visitors'] }}</b><small class="muted">{{ trans_choice('social.kpi.requests', $d['web']['requests'], ['n' => $d['web']['requests']]) }}</small></a>
        </div>

        <div class="sm-two mt">
            <section class="card">
                <div class="card-head"><h2>{{ __('social.charts.followers') }}</h2>
                    <span class="sm-legend">@foreach (array_keys($d['followers_series']) as $p)<span><i style="background:{{ \App\Services\Social\Dashboard::COLORS[$p] }}"></i>{{ __('social.platforms.'.$p) }}</span>@endforeach</span></div>
                @if ($d['followers_series'])@include('social._line', ['series' => $d['followers_series'], 'label' => __('social.charts.followers')])@else<p class="muted">{{ __('social.no_data') }}</p>@endif
            </section>
            <section class="card">
                <div class="card-head"><h2>{{ __('social.charts.views') }}</h2></div>
                @if ($d['weekly'])@include('social._bars', ['weeks' => $d['weekly'], 'field' => 'views', 'label' => __('social.charts.views')])@else<p class="muted">{{ __('social.no_data') }}</p>@endif
            </section>
        </div>

        <div class="sm-cards mt">
            @foreach ($d['per'] as $p)
                @php($a = $p['account'])
                <section class="card sm-acc" style="--pc: {{ \App\Services\Social\Dashboard::COLORS[$a->platform] ?? '#888' }}">
                    <div class="sm-acc-head">
                        @if ($a->avatar_url)<img src="{{ $a->avatar_url }}" alt="" referrerpolicy="no-referrer">@else<span class="sm-badge">{{ mb_strtoupper(mb_substr(__('social.platforms.'.$a->platform), 0, 2)) }}</span>@endif
                        <div><b>{{ $a->name }}</b><small class="muted">{{ __('social.platforms.'.$a->platform) }}@if ($a->handle) · {{ $a->handle }}@endif</small></div>
                        @if ($a->url)<a class="sm-open" href="{{ $a->url }}" target="_blank" rel="noopener" aria-label="{{ __('social.open') }}">↗</a>@endif
                    </div>
                    <dl class="sm-figs">
                        <div><dt>{{ $a->platform === 'google' ? __('social.figs.reviews') : __('social.figs.followers') }}</dt><dd>{{ $a->platform === 'google' ? (($a->meta['rating'] ?? null) ? number_format($a->meta['rating'], 1, ',', '').' ★' : '—') : $nf($p['followers']) }}@if ($p['followers_delta']) <small class="{{ $p['followers_delta'] >= 0 ? 'up' : 'down' }}">{{ $p['followers_delta'] >= 0 ? '+' : '' }}{{ $p['followers_delta'] }}</small>@endif</dd></div>
                        <div><dt>{{ __('social.figs.views') }}</dt><dd>{{ $nf($p['views']) }}</dd></div>
                        <div><dt>{{ __('social.figs.engagements') }}</dt><dd>{{ $nf($p['engagements']) }}</dd></div>
                        <div><dt>{{ $a->platform === 'google' ? __('social.figs.clicks') : __('social.figs.posts') }}</dt><dd>{{ $a->platform === 'google' ? $nf($p['clicks']) : $p['posts'] }}</dd></div>
                    </dl>
                    @if ($a->status === 'error')<p class="sm-err small">{{ __('social.status_error') }}: {{ \Illuminate\Support\Str::limit($a->last_error, 140) }}</p>@endif
                </section>
            @endforeach
        </div>

        <div class="split mt">
            <section class="card card-flush">
                <div class="card-head sm-pad"><h2>{{ __('social.top.title') }}</h2></div>
                @forelse ($d['top'] as $post)
                    <a class="sm-post" href="{{ $post->permalink }}" target="_blank" rel="noopener">
                        @if ($post->thumb_url)<img src="{{ $post->thumb_url }}" alt="" loading="lazy" referrerpolicy="no-referrer">@else<span class="sm-thumb" style="--pc: {{ \App\Services\Social\Dashboard::COLORS[$post->account->platform] ?? '#888' }}"></span>@endif
                        <span class="sm-post-main"><b>{{ \Illuminate\Support\Str::limit($post->text ?: '—', 110) }}</b><small class="muted">{{ __('social.platforms.'.$post->account->platform) }} · {{ $post->published_at?->timezone('Europe/Brussels')->format('d/m H:i') }}@if ($post->plan_id) · {{ __('social.from_cluk') }}@endif</small></span>
                        <span class="sm-post-figs"><b>{{ $nf($post->engagement()) }}</b><small class="muted">{{ __('social.top.engagement') }}@if ($post->views) · {{ $nf($post->views) }} {{ __('social.top.views') }}@endif</small></span>
                    </a>
                @empty
                    <p class="muted sm-pad">{{ __('social.no_posts') }}</p>
                @endforelse
            </section>
            <aside class="stack">
                <section class="card">
                    <h2>{{ __('social.best.title') }}</h2>
                    <p class="muted small">{{ trans_choice('social.best.hint', $d['best']['posts'], ['n' => $d['best']['posts']]) }}</p>
                    <div class="sm-heat" role="table">
                        <span></span>@foreach (range(0, 7) as $b)<span class="sm-h">{{ str_pad($b * 3, 2, '0', STR_PAD_LEFT) }}h</span>@endforeach
                        @foreach (range(1, 7) as $wd)
                            <span class="sm-h">{{ \Illuminate\Support\Carbon::now()->startOfWeek()->addDays($wd - 1)->locale(app()->getLocale())->minDayName }}</span>
                            @foreach (range(0, 7) as $b)
                                @php($c = $d['best']['grid'][$wd][$b] ?? null)
                                <span class="sm-cell" style="--a: {{ $c && $d['best']['max'] ? round(0.12 + 0.88 * $c['avg'] / $d['best']['max'], 2) : 0 }}" title="{{ $c ? __('social.best.cell', ['avg' => number_format($c['avg'], 0, ',', '.'), 'n' => $c['n']]) : '' }}"></span>
                            @endforeach
                        @endforeach
                    </div>
                </section>
                <section class="card">
                    <div class="card-head"><h2>{{ __('social.upcoming') }}</h2><a href="{{ route('social.plans') }}">{{ __('social.all_posts') }} →</a></div>
                    @forelse ($d['upcoming'] as $plan)
                        <a class="p-line" href="{{ route('social.plans.show', $plan) }}"><span><b>{{ $plan->title }}</b><br><small class="muted">{{ $plan->targets->map(fn ($t) => __('social.platforms.'.$t->account->platform))->unique()->implode(', ') }}</small></span><span class="muted small">{{ $plan->scheduled_at?->timezone('Europe/Brussels')->format('D d/m H:i') }}</span></a>
                    @empty
                        <p class="muted">{{ __('social.nothing_planned') }}</p>
                    @endforelse
                </section>
                @if ($d['web']['per'])
                    <section class="card">
                        <h2>{{ __('social.web.title') }}</h2>
                        <ul class="wd-list">@foreach ($d['web']['per'] as $p => $w)<li><span>{{ __('social.platforms.'.$p) }}</span><b>{{ $w['visitors'] ?? 0 }} · {{ trans_choice('social.kpi.requests', $w['requests'] ?? 0, ['n' => $w['requests'] ?? 0]) }}</b></li>@endforeach</ul>
                    </section>
                @endif
                @if ($d['reviews'])
                    <section class="card">
                        <h2>{{ __('social.reviews.title') }}</h2>
                        @foreach ($d['reviews']['profiles'] as $g)<p class="p-line"><span>{{ $g['name'] }}</span><b>{{ $g['rating'] ? number_format($g['rating'], 1, ',', '').' ★' : '—' }} <small class="muted">({{ $g['count'] ?? 0 }})</small></b></p>@endforeach
                        @foreach ($d['reviews']['latest'] as $r)<p class="small"><b>{{ str_repeat('★', (int) $r->rating) }}</b> {{ $r->author }} · <span class="muted">{{ $r->reviewed_at?->format('d/m/Y') }}</span><br>{{ \Illuminate\Support\Str::limit($r->text, 160) }}@unless ($r->reply) <span class="pill pill-warn">{{ __('social.reviews.no_reply') }}</span>@endunless</p>@endforeach
                    </section>
                @endif
            </aside>
        </div>
    @endif

    <section class="card mt" id="accounts">
        <div class="card-head"><h2>{{ __('social.accounts.title') }}</h2>
            @if ($canConnect && $d['accounts']->isNotEmpty())<form method="post" action="{{ route('social.sync') }}">@csrf<button class="btn" type="submit">{{ __('social.accounts.sync') }}</button></form>@endif</div>
        <p class="muted small">{{ __('social.accounts.intro') }}</p>
        <div class="sm-connect">
            @foreach ($providers as $pv)
                <div class="sm-prov">
                    <b>{{ __('social.providers.'.$pv['key'].'.name') }}</b><small class="muted">{{ __('social.providers.'.$pv['key'].'.what') }}</small>
                    @if (! $pv['configured'])
                        <span class="pill pill-off">{{ __('social.accounts.keys_missing') }}</span>
                    @elseif ($canConnect)
                        <a class="btn" href="{{ route('social.connect', $pv['key']) }}">{{ $d['accounts']->contains(fn ($a) => config('social.platforms.'.$a->platform.'.provider') === $pv['key']) ? __('social.accounts.reconnect') : __('social.accounts.connect') }}</a>
                    @endif
                </div>
            @endforeach
            <div class="sm-prov"><b>TikTok · Threads</b><small class="muted">{{ __('social.accounts.later') }}</small></div>
        </div>
        @if ($d['accounts']->isNotEmpty())
            <table class="table mt">
                <thead><tr><th>{{ __('social.accounts.account') }}</th><th>{{ __('social.accounts.status') }}</th><th>{{ __('social.accounts.synced') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($d['accounts'] as $a)
                        <tr><td><b>{{ $a->name }}</b> <small class="muted">· {{ __('social.platforms.'.$a->platform) }}</small></td>
                            <td><span @class(['pill', 'pill-ok' => $a->status === 'connected', 'pill-warn' => $a->status === 'error'])>{{ __('social.statuses.'.$a->status) }}</span></td>
                            <td class="muted small">{{ $a->last_synced_at?->timezone('Europe/Brussels')->format('d/m H:i') ?? '—' }}</td>
                            <td class="num">@if ($canConnect)<form method="post" action="{{ route('social.disconnect', $a) }}">@csrf<button class="linkbtn" type="submit">{{ __('social.accounts.disconnect') }}</button></form>@endif</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
