{{-- Dashboard: last 30 days per channel (small multiples) and every post on a day × hour grid. $t = DigitalTrend::get() --}}
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
    $n = count($t['dates']);
    $loc = app()->getLocale();
    $dayLabel = fn ($d) => $d->locale($loc)->translatedFormat('D j M');
    // Small multiples
    $BW = 600; $BH = 46; $band = $BW / $n; $bw = max(3, min(14, $band - 3));
    // Posts grid
    $W = 1000; $H = 300; $L = 40; $R = 12; $T = 10; $B = 270;
    // Date ticks: every week, plus today when it is not too close to the last weekly tick.
    $tick = fn ($i) => $i % 7 === 0 || ($i === $n - 1 && ($n - 1) % 7 >= 4);
    $px = fn ($day) => $L + ($W - $L - $R) * ($day + 0.5) / $n;
    $py = fn ($hour) => $T + ($B - $T) * $hour / 24;
    $rad = fn ($v) => 4 + 9 * sqrt($v / $t['maxPost']);
@endphp
<section class="card dt">
    <div class="card-head">
        <div><h2>{{ __('dashboard.trend.title') }}</h2><p class="muted small">{{ __('dashboard.trend.sub') }}</p></div>
    </div>

    <figure class="dg-fig">
        <figcaption>{{ __('dashboard.trend.per_day') }}</figcaption>
        <div class="dt-rows">
            @foreach ($t['rows'] as $row)
                <div class="dt-row">
                    <div class="dt-label">
                        <b>@if ($row['color'])<i class="dt-key" style="background: {{ $row['color'] }}"></i>@endif{{ __('dashboard.digital.src.'.$row['key']) }}</b>
                        <small class="muted">{{ $fmt($row['total']) }} {{ __('dashboard.trend.'.$row['measure']) }}</small>
                    </div>
                    <div class="dg-plot">
                        <svg viewBox="0 0 {{ $BW }} {{ $BH }}" preserveAspectRatio="none" role="img" aria-label="{{ __('dashboard.digital.src.'.$row['key']) }}">
                            <line class="dg-grid" data-base x1="0" x2="{{ $BW }}" y1="{{ $BH }}" y2="{{ $BH }}" />
                            @foreach ($row['values'] as $i => $v)
                                @php($cx = $band * $i + $band / 2)
                                <g class="dg-col" data-tip="{{ $dayLabel($t['dates'][$i]) }} · {{ $fmt($v) }} {{ __('dashboard.trend.'.$row['measure']) }}">
                                    <rect class="dg-hit" x="{{ round($band * $i, 1) }}" y="0" width="{{ round($band, 1) }}" height="{{ $BH }}" />
                                    @if ($v > 0)
                                        @php($h = max(2, ($BH - 4) * $v / $row['max'])) @php($r = min(3, $h / 2)) @php($x0 = $cx - $bw / 2)
                                        <path class="dt-bar" @if ($row['color']) style="fill: {{ $row['color'] }}" @endif d="M{{ round($x0, 1) }} {{ $BH }}V{{ round($BH - $h + $r, 1) }}Q{{ round($x0, 1) }} {{ round($BH - $h, 1) }} {{ round($x0 + $r, 1) }} {{ round($BH - $h, 1) }}H{{ round($x0 + $bw - $r, 1) }}Q{{ round($x0 + $bw, 1) }} {{ round($BH - $h, 1) }} {{ round($x0 + $bw, 1) }} {{ round($BH - $h + $r, 1) }}V{{ $BH }}Z" />
                                    @endif
                                </g>
                            @endforeach
                        </svg>
                        <div class="dg-tip" hidden></div>
                    </div>
                </div>
            @endforeach
            <div class="dt-row dt-axis"><span></span>
                <div class="dt-ticks">@foreach ($t['dates'] as $i => $d)@if ($tick($i))<span style="left: {{ round(($i + 0.5) / $n * 100, 2) }}%">{{ $d->locale($loc)->translatedFormat('j M') }}</span>@endif @endforeach</div>
            </div>
        </div>
        @unless ($t['connected'])<p class="muted small">{{ __('dashboard.digital.no_social') }}</p>@endunless
    </figure>

    <figure class="dg-fig dt-posts">
        <figcaption>{{ __('dashboard.trend.posts') }}</figcaption>
        <p class="muted small">{{ __('dashboard.trend.posts_sub') }}</p>
        @if ($t['posts']->isEmpty())
            <p class="muted">{{ __('dashboard.trend.no_posts') }}</p>
        @else
            <div class="dt-legend">@foreach ($t['posts']->unique('platform') as $p)<span><svg viewBox="0 0 14 14" width="14" height="14" aria-hidden="true">@include('partials.digital-trend-mark', ['p' => $p, 'x' => 7, 'y' => 7, 'r' => 5.5])</svg>{{ __('dashboard.digital.src.'.$p['platform']) }}</span>@endforeach
                <span class="muted">{{ __('dashboard.trend.size') }}</span></div>
            <div class="dg-plot">
                <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ __('dashboard.trend.posts') }}">
                    @foreach ([0, 6, 12, 18, 24] as $hh)
                        <line class="dg-grid" @if ($hh === 24) data-base @endif x1="{{ $L }}" x2="{{ $W - $R }}" y1="{{ round($py($hh), 1) }}" y2="{{ round($py($hh), 1) }}" />
                        <text class="dg-ax" x="{{ $L - 6 }}" y="{{ round($py($hh), 1) + 4 }}" text-anchor="end">{{ sprintf('%02d', $hh) }}h</text>
                    @endforeach
                    @foreach ($t['dates'] as $i => $d)
                        @if ($tick($i))<text class="dg-ax" x="{{ round($px($i), 1) }}" y="{{ $H - 8 }}" text-anchor="middle">{{ $d->locale($loc)->translatedFormat('j M') }}</text>@endif
                    @endforeach
                    @foreach ($t['posts']->sortByDesc('interactions') as $p)
                        <a @if ($p['url']) href="{{ $p['url'] }}" target="_blank" rel="noopener" @endif class="dg-col dt-post"
                           data-tip="{{ __('dashboard.digital.src.'.$p['platform']) }} · {{ $p['at']->locale($loc)->translatedFormat('D j M H:i') }} · {{ trans_choice('dashboard.trend.interactions_n', $p['interactions'], ['n' => $fmt($p['interactions'])]) }}{{ $p['text'] ? ' · '.$p['text'] : '' }}">
                            <circle class="dg-hit" cx="{{ round($px($p['day']), 1) }}" cy="{{ round($py($p['hour']), 1) }}" r="{{ round(max(10, $rad($p['interactions']) + 4), 1) }}" />
                            @include('partials.digital-trend-mark', ['p' => $p, 'x' => $px($p['day']), 'y' => $py($p['hour']), 'r' => $rad($p['interactions'])])
                        </a>
                    @endforeach
                </svg>
                <div class="dg-tip" hidden></div>
            </div>
        @endif
    </figure>
    <p class="muted small dg-note">{{ __('dashboard.trend.note') }}</p>
</section>
<script>
(function () {
    document.querySelectorAll('.dt .dg-plot').forEach(function (plot) {
        var tip = plot.querySelector('.dg-tip');
        plot.querySelectorAll('.dg-col').forEach(function (g) {
            g.addEventListener('mouseenter', function () {
                var box = g.querySelector('.dg-hit').getBoundingClientRect(), p = plot.getBoundingClientRect();
                tip.textContent = g.getAttribute('data-tip');
                tip.hidden = false;
                tip.style.left = Math.max(80, Math.min(p.width - 80, box.left - p.left + box.width / 2)) + 'px';
                tip.style.top = (box.top - p.top - 4) + 'px';
            });
            g.addEventListener('mouseleave', function () { tip.hidden = true; });
        });
    });
})();
</script>
