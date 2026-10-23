{{-- A small line chart (inline SVG): $series = [platform => [date => value|null]]. --}}
@php
    $W = 640; $H = 220; $pl = 44; $pr = 10; $pt = 10; $pb = 24;
    $vals = collect($series)->flatten()->filter(fn ($v) => $v !== null);
    $min = $vals->min() ?? 0; $max = $vals->max() ?? 1;
    if ($max === $min) { $max += 1; $min = max(0, $min - 1); }
    $pad = ($max - $min) * 0.1; $min = max(0, $min - $pad); $max += $pad;
    $n = max(1, count(reset($series) ?: []) - 1);
    $x = fn ($i) => $pl + ($W - $pl - $pr) * $i / $n;
    $y = fn ($v) => $pt + ($H - $pt - $pb) * (1 - ($v - $min) / ($max - $min));
    $fmt = fn ($v) => $v >= 10000 ? number_format($v / 1000, 1, ',', '.').'k' : number_format($v, 0, ',', '.');
    $dates = array_keys(reset($series) ?: []);
@endphp
<svg viewBox="0 0 {{ $W }} {{ $H }}" class="sm-chart" role="img" aria-label="{{ $label }}">
    @foreach ([0, 0.5, 1] as $f)
        @php $v = $min + ($max - $min) * $f; @endphp
        <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ round($y($v), 1) }}" y2="{{ round($y($v), 1) }}" class="sm-grid"/>
        <text x="{{ $pl - 6 }}" y="{{ round($y($v), 1) + 4 }}" text-anchor="end" class="sm-axis">{{ $fmt($v) }}</text>
    @endforeach
    @if ($dates)
        <text x="{{ $pl }}" y="{{ $H - 6 }}" class="sm-axis">{{ \Illuminate\Support\Carbon::parse($dates[0])->format('d/m') }}</text>
        <text x="{{ $W - $pr }}" y="{{ $H - 6 }}" text-anchor="end" class="sm-axis">{{ \Illuminate\Support\Carbon::parse(end($dates))->format('d/m') }}</text>
    @endif
    @foreach ($series as $platform => $points)
        @php
            $path = ''; $i = 0;
            foreach ($points as $v) {
                if ($v !== null) { $path .= ($path === '' ? 'M' : 'L').round($x($i), 1).' '.round($y($v), 1).' '; }
                $i++;
            }
        @endphp
        <path d="{{ trim($path) }}" fill="none" stroke="{{ \App\Services\Social\Dashboard::COLORS[$platform] ?? '#888' }}" stroke-width="2.5" stroke-linejoin="round"/>
    @endforeach
</svg>
