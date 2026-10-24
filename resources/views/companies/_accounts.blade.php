{{-- Customer card › Credit check › Annual accounts (National Bank): bars per year with the profit as a line, and a small table. --}}
@php
    $rows = $company->accounts->sortBy('year')->values();
    $metric = $rows->whereNotNull('turnover')->count() >= max(1, $rows->whereNotNull('gross_margin')->count()) ? 'turnover' : 'gross_margin';
    $money = function ($cents) {
        if ($cents === null) return '—';
        $v = $cents / 100; $a = abs($v); $s = $v < 0 ? '−' : '';
        return $s.'€ '.($a >= 1e6 ? number_format($a / 1e6, 1, ',', '.').'M' : ($a >= 1e3 ? number_format($a / 1e3, 0, ',', '.').'k' : number_format($a, 0, ',', '.')));
    };
    $W = 340; $H = 196; $pl = 6; $pr = 6; $pt = 18; $pb = 40;
    $vals = $rows->pluck($metric)->filter(fn ($v) => $v !== null);
    $max = max(1, $vals->max() ?? 1); $min = min(0, $vals->min() ?? 0);
    $y = fn ($v) => round($pt + ($H - $pt - $pb) * ($max - $v) / max(1, $max - $min), 1);
    $n = max(1, $rows->count()); $bw = ($W - $pl - $pr) / $n;
    $cx = fn ($i) => round($pl + $i * $bw + $bw / 2, 1);
    $fte = $rows->whereNotNull('employees')->last();
@endphp
<div class="fa" id="accounts">
    <h3 class="fa-title">{{ __('credit.nbb.title') }}</h3>
    @if ($rows->isEmpty())
        <p class="muted small">{{ $company->accounts_checked_at ? __('credit.nbb.none') : __('credit.nbb.not_yet') }}</p>
    @else
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="fa-chart" role="img" aria-label="{{ __('credit.nbb.chart', ['what' => __('credit.nbb.'.$metric)]) }}">
            <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $y(0) }}" y2="{{ $y(0) }}" class="sm-grid"/>
            @foreach ($rows as $i => $r)
                @php $v = $r->{$metric}; @endphp
                @if ($v !== null)
                    @php $top = min($y($v), $y(0)); $h = max(1, abs($y($v) - $y(0))); @endphp
                    <rect x="{{ round($pl + $i * $bw + $bw * 0.2, 1) }}" y="{{ $top }}" width="{{ round($bw * 0.6, 1) }}" height="{{ $h }}" rx="3" @class(['fa-bar', 'fa-bar-last' => $loop->last])><title>{{ $r->year }} · {{ __('credit.nbb.'.$metric) }}: {{ $money($v) }}</title></rect>
                    <text x="{{ $cx($i) }}" y="{{ max(11, $top - 5) }}" text-anchor="middle" class="fa-val">{{ $money($v) }}</text>
                @endif
                @if ($r->profit !== null)<text x="{{ $cx($i) }}" y="{{ $H - 24 }}" text-anchor="middle" @class(['fa-profit', 'fa-neg' => $r->profit < 0])>{{ $r->profit > 0 ? '+' : '' }}{{ $money($r->profit) }}<title>{{ __('credit.nbb.profit') }} {{ $r->year }}</title></text>@endif
                <text x="{{ $cx($i) }}" y="{{ $H - 6 }}" text-anchor="middle" class="sm-axis">{{ $r->year }}</text>
            @endforeach
        </svg>
        <p class="fa-legend small"><span><i class="fa-key fa-key-bar"></i>{{ __('credit.nbb.'.$metric) }}</span><span><b class="fa-key-txt">+€</b>{{ __('credit.nbb.profit') }}</span></p>
        <table class="table fa-table">
            <thead><tr><th>{{ __('credit.nbb.year') }}</th><th class="num">{{ __('credit.nbb.'.$metric.'_short') }}</th><th class="num">%</th><th class="num">{{ __('credit.nbb.profit') }}</th><th class="num">{{ __('credit.nbb.equity') }}</th></tr></thead>
            <tbody>
                @foreach ($rows->reverse() as $r)
                    @php
                        $prev = $rows->firstWhere('year', $r->year - 1)?->{$metric};
                        $pct = $prev && $r->{$metric} !== null && $prev > 0 ? round(($r->{$metric} - $prev) / $prev * 100) : null;
                    @endphp
                    <tr>
                        <td>{{ $r->year }}</td>
                        <td class="num">{{ $money($r->{$metric}) }}</td>
                        <td @class(['num', 'fa-up' => $pct > 0, 'fa-down' => $pct < 0])>{{ $pct === null ? '' : ($pct > 0 ? '+' : '').$pct.'%' }}</td>
                        <td @class(['num', 'fa-down' => $r->profit < 0])>{{ $money($r->profit) }}</td>
                        <td @class(['num', 'fa-down' => $r->equity < 0])>{{ $money($r->equity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted small">
            @if ($fte){{ __('credit.nbb.staff', ['n' => rtrim(rtrim(number_format($fte->employees, 1, ',', '.'), '0'), ','), 'year' => $fte->year]) }}<br>@endif
            @if ($metric === 'gross_margin'){{ __('credit.nbb.no_turnover') }}<br>@endif
            {{ __('credit.nbb.source', ['date' => $rows->last()->deposited_on?->format('d/m/Y') ?? '—']) }}
        </p>
    @endif
</div>
