{{-- Stacked bars per week (inline SVG): $weeks = [monday => [platform => [field => n]]], $field = views|engagements. --}}
@php
    $W = 640; $H = 220; $pl = 44; $pr = 10; $pt = 10; $pb = 24;
    $totals = collect($weeks)->map(fn ($p) => collect($p)->sum($field));
    $max = max(1, $totals->max() ?? 1);
    $n = max(1, count($weeks));
    $bw = ($W - $pl - $pr) / $n;
    $fmt = fn ($v) => $v >= 10000 ? number_format($v / 1000, 1, ',', '.').'k' : number_format($v, 0, ',', '.');
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" class="sm-chart" role="img" aria-label="{{ $label }}">
    @foreach ([0, 0.5, 1] as $f)
        @php($yy = $pt + ($H - $pt - $pb) * (1 - $f))
        <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ round($yy, 1) }}" y2="{{ round($yy, 1) }}" class="sm-grid"/>
        <text x="{{ $pl - 6 }}" y="{{ round($yy, 1) + 4 }}" text-anchor="end" class="sm-axis">{{ $fmt($max * $f) }}</text>
    @endforeach
    @php($i = 0)
    @foreach ($weeks as $monday => $platforms)
        @php($base = $H - $pb)
        @foreach ($platforms as $platform => $v)
            @php($h = ($H - $pt - $pb) * ($v[$field] ?? 0) / $max)
            @if ($h > 0)<rect x="{{ round($pl + $i * $bw + $bw * 0.15, 1) }}" y="{{ round($base - $h, 1) }}" width="{{ round($bw * 0.7, 1) }}" height="{{ round($h, 1) }}" fill="{{ \App\Services\Social\Dashboard::COLORS[$platform] ?? '#888' }}"><title>{{ __('social.week_of', ['date' => \Illuminate\Support\Carbon::parse($monday)->format('d/m')]) }} · {{ __('social.platforms.'.$platform) }}: {{ number_format($v[$field] ?? 0, 0, ',', '.') }}</title></rect>@endif
            @php($base -= $h)
        @endforeach
        @if ($n <= 14 || $i % 2 === 0)<text x="{{ round($pl + $i * $bw + $bw / 2, 1) }}" y="{{ $H - 6 }}" text-anchor="middle" class="sm-axis">{{ \Illuminate\Support\Carbon::parse($monday)->format('d/m') }}</text>@endif
        @php($i++)
    @endforeach
</svg>
