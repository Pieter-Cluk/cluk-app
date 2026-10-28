{{-- Dashboard: today's digital activity (website per hour + social media per day). $d = DigitalToday::get() --}}
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
    $max = max(1, max($d['hours']));
    $step = collect([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000])->first(fn ($s) => $max / $s <= 4) ?? 10000;
    $top = (int) (ceil($max / $step) * $step);
    $W = 480; $L = 30; $R = 8; $T = 10; $B = 130;
    $band = ($W - $L - $R) / 24; $bw = min(14, $band - 4);
    $y = fn ($v) => $B - ($v / $top) * ($B - $T);
    $srcMax = max(1, (int) $d['sources']->max('n'));
@endphp
<section class="card dg">
    <div class="card-head">
        <div><h2>{{ __('dashboard.digital.title') }} <span class="muted small">{{ $d['day']->locale(app()->getLocale())->translatedFormat('j M') }}</span></h2>
            <p class="muted small">{{ __('dashboard.digital.sub') }}</p></div>
        <nav class="chips">
            @if ($webLink)<a class="btn" href="{{ route('management.website') }}">{{ __('dashboard.digital.to_web') }}</a>@endif
            @if (Route::has('social.index'))<a class="btn" href="{{ route('social.index') }}">{{ __('dashboard.digital.to_social') }}</a>@endif
        </nav>
    </div>

    <div class="dg-top">
        <div class="dg-hero"><small>{{ mb_strtoupper(__('dashboard.digital.total')) }}</small><b>{{ $fmt($d['total']) }}</b><span class="muted small">{{ __('dashboard.digital.total_s') }}</span></div>
        <div class="dg-tiles">
            <div class="dg-tile"><small>{{ __('dashboard.digital.visitors') }}</small><b>{{ $fmt($d['web']['visitors']) }}</b></div>
            <div class="dg-tile"><small>{{ __('dashboard.digital.views') }}</small><b>{{ $fmt($d['web']['views']) }}</b></div>
            <div class="dg-tile"><small>{{ __('dashboard.digital.actions') }}</small><b>{{ $fmt($d['web']['actions']) }}</b>
                @if ($d['web']['by_type'])<span class="muted small">{{ collect($d['web']['by_type'])->take(3)->map(fn ($n, $t) => $n.' × '.mb_strtolower(__('webstats.types.'.$t)))->implode(' · ') }}</span>@endif</div>
            <div class="dg-tile"><small>{{ __('dashboard.digital.interactions') }}</small><b>{{ $d['social']['connected'] ? $fmt($d['social']['interactions']) : '—' }}</b></div>
            <div class="dg-tile"><small>{{ __('dashboard.digital.reach') }}</small><b>{{ $d['social']['connected'] ? $fmt($d['social']['reach']) : '—' }}</b>
                @if ($d['social']['posts'])<span class="muted small">{{ trans_choice('dashboard.digital.posts', $d['social']['posts'], ['n' => $d['social']['posts']]) }}</span>@endif</div>
        </div>
    </div>

    <div class="dg-charts">
        <figure class="dg-fig">
            <figcaption>{{ __('dashboard.digital.per_hour') }}</figcaption>
            <div class="dg-plot">
                <svg viewBox="0 0 {{ $W }} 150" role="img" aria-label="{{ __('dashboard.digital.per_hour') }}">
                    @for ($v = 0; $v <= $top; $v += $step)
                        <line class="dg-grid" x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ round($y($v), 1) }}" y2="{{ round($y($v), 1) }}" @if ($v === 0) data-base @endif />
                        <text class="dg-ax" x="{{ $L - 6 }}" y="{{ round($y($v), 1) + 4 }}" text-anchor="end">{{ $v }}</text>
                    @endfor
                    @foreach ($d['hours'] as $h => $n)
                        @php($cx = $L + $band * $h + $band / 2)
                        <g class="dg-col" data-tip="{{ sprintf('%02d:00–%02d:00', $h, ($h + 1) % 24) }} · {{ trans_choice('dashboard.digital.events', $n, ['n' => $n]) }}">
                            <rect class="dg-hit" x="{{ round($cx - $band / 2, 1) }}" y="{{ $T }}" width="{{ round($band, 1) }}" height="{{ $B - $T }}" />
                            @if ($n > 0)
                                @php($yt = $y($n)) @php($r = min(4, ($B - $yt) / 2)) @php($x0 = $cx - $bw / 2)
                                <path class="dg-bar" d="M{{ round($x0, 1) }} {{ $B }}V{{ round($yt + $r, 1) }}Q{{ round($x0, 1) }} {{ round($yt, 1) }} {{ round($x0 + $r, 1) }} {{ round($yt, 1) }}H{{ round($x0 + $bw - $r, 1) }}Q{{ round($x0 + $bw, 1) }} {{ round($yt, 1) }} {{ round($x0 + $bw, 1) }} {{ round($yt + $r, 1) }}V{{ $B }}Z" />
                            @endif
                            @if ($h % 3 === 0)<text class="dg-ax" x="{{ round($cx, 1) }}" y="146" text-anchor="middle">{{ sprintf('%02d', $h) }}h</text>@endif
                        </g>
                    @endforeach
                    @if ($d['now'] < 23)
                        @php($nx = $L + $band * ($d['now'] + 1))
                        <line class="dg-now" x1="{{ round($nx, 1) }}" x2="{{ round($nx, 1) }}" y1="{{ $T }}" y2="{{ $B }}" />
                    @endif
                </svg>
                <div class="dg-tip" hidden></div>
            </div>
        </figure>

        <figure class="dg-fig">
            <figcaption>{{ __('dashboard.digital.where') }}</figcaption>
            <div class="dg-src">
                @foreach ($d['sources'] as $s)
                    <div class="dg-src-row" title="{{ __('dashboard.digital.src.'.$s['key']) }} · {{ $fmt($s['n']) }}">
                        <span>{{ __('dashboard.digital.src.'.$s['key']) }}</span>
                        <i><em style="width: {{ $s['n'] > 0 ? max(2, round($s['n'] / $srcMax * 100, 1)) : 0 }}%"></em></i>
                        <b>{{ $fmt($s['n']) }}</b>
                    </div>
                @endforeach
                @unless ($d['social']['connected'])<p class="muted small">{{ __('dashboard.digital.no_social') }}</p>@endunless
            </div>
        </figure>
    </div>

    <p class="muted small dg-note">{{ __('dashboard.digital.note') }}@if ($d['social']['synced']) {{ __('dashboard.digital.synced', ['time' => \Illuminate\Support\Carbon::parse($d['social']['synced'])->format('H:i')]) }}@endif</p>
</section>
<script>
(function () {
    document.querySelectorAll('.dg-plot').forEach(function (plot) {
        var tip = plot.querySelector('.dg-tip');
        plot.querySelectorAll('.dg-col').forEach(function (g) {
            g.addEventListener('mouseenter', function () {
                var box = g.querySelector('.dg-hit').getBoundingClientRect(), p = plot.getBoundingClientRect();
                tip.textContent = g.getAttribute('data-tip');
                tip.hidden = false;
                var x = box.left - p.left + box.width / 2;
                tip.style.left = Math.max(60, Math.min(p.width - 60, x)) + 'px';
            });
            g.addEventListener('mouseleave', function () { tip.hidden = true; });
        });
    });
})();
</script>
