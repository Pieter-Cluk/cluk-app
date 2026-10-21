@extends('wall.layout')
@php($end = $start->copy()->addDays($days - 1))
@php($ti = (int) $start->diffInDays(today(), false))
@php($ramp = ['#1f2a21', '#2f5a24', '#4f8f36', '#7cc35a', '#bde8a2'])
@php($q = fn ($o) => array_filter(['view' => 'planning', 'key' => $key ?: null, 'off' => $o ?: null]))
@section('title'){{ __('wall.planning') }}@endsection
@section('nav')
    <span class="dbtns"><a class="nav" href="{{ route('wall.show', $q($off - 1)) }}" aria-label="{{ __('wall.prev') }}">‹</a><b class="range">{{ $start->locale(app()->getLocale())->translatedFormat('j M') }} – {{ $end->locale(app()->getLocale())->translatedFormat('j M') }}</b><a class="nav" href="{{ route('wall.show', $q($off + 1)) }}" aria-label="{{ __('wall.next') }}">›</a></span>
@endsection
@section('body')
<section class="panel">
    <div class="ph"><p class="ptitle">{{ mb_strtoupper(__('wall.busy')) }}</p><span class="legend">{{ __('wall.calm') }} <span class="ramp"></span> {{ __('wall.busy_word') }} · <b class="hot">!</b> {{ __('wall.over') }}</span></div>
    <div class="grid" style="grid-template-columns: 230px repeat({{ $days }}, minmax(0, 1fr))">
        <span></span>
        @for ($i = 0; $i < $days; $i++)
            @php($d = $start->copy()->addDays($i))
            <span @class(['dh', 'dh-we' => $d->isWeekend(), 'dh-today' => $i === $ti])>{{ $d->locale(app()->getLocale())->translatedFormat('D') }}<b>{{ $d->day }}</b></span>
        @endfor
        @foreach ($heat as $row)
            <span class="rl">{{ $row['label'] }}<small>{{ $row['sub'] }}</small></span>
            @foreach ($row['cells'] as $i => $pct)
                <span @class(['hc', 'hc-hot' => $pct > 90, 'hc-today' => $i === $ti, 'hc-light' => $pct < 45]) style="background: {{ $ramp[min(4, (int) floor($pct / 100 * 4.999))] }}" title="{{ $row['label'] }}: {{ $pct }}%">{{ $pct > 90 ? '!' : ($pct >= 75 ? $pct : '') }}</span>
            @endforeach
        @endforeach
    </div>
</section>
<section class="panel grow">
    <div class="ph"><p class="ptitle">{{ mb_strtoupper(__('wall.jobs')) }}</p>
        <div class="legend">@foreach (['opt', 'conf', 'out', 'done'] as $s)<span><i class="seg-{{ $s }}"></i>{{ __('wall.seg.'.$s) }}</span>@endforeach</div></div>
    <div class="grid" style="grid-template-columns: 230px repeat({{ $days }}, minmax(0, 1fr))">
        <span></span>
        @for ($i = 0; $i < $days; $i++)
            @php($d = $start->copy()->addDays($i))
            <span @class(['dh', 'dh-we' => $d->isWeekend(), 'dh-today' => $i === $ti])>{{ $d->locale(app()->getLocale())->translatedFormat('D') }}<b>{{ $d->day }}</b></span>
        @endfor
        @forelse ($jobs as $j)
            <span class="rl">{{ $j['name'] }}<small>{{ $j['sub'] }}</small></span>
            <span class="lane" style="grid-column: span {{ $days }}; --n: {{ $days }}">
                @if ($ti >= 0 && $ti < $days)<span class="today" style="left: calc({{ ($ti + 0.5) / $days * 100 }}% - 1px)"></span>@endif
                <span class="seg seg-{{ $j['st'] }}" style="left: calc({{ $j['a'] / $days * 100 }}% + 2px); width: calc({{ ($j['b'] - $j['a'] + 1) / $days * 100 }}% - 4px)">{{ $j['b'] - $j['a'] >= 1 ? __('wall.seg.'.$j['st']) : '' }}</span>
            </span>
        @empty
            <span class="empty" style="grid-column: span {{ $days + 1 }}">{{ __('wall.no_jobs') }}</span>
        @endforelse
    </div>
</section>
@endsection
