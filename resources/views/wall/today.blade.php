@extends('wall.layout')
@php($compact = in_array($day, ['week', 'month'], true))
@php($q = fn ($d) => array_filter(['view' => 'today', 'key' => $key ?: null, 'day' => $d]))
@section('title'){{ __('wall.titles.'.$day) }} · {{ $from->locale(app()->getLocale())->translatedFormat('D j M') }}@if (! $from->isSameDay($until)) – {{ $until->locale(app()->getLocale())->translatedFormat('D j M') }}@endif @endsection
@section('nav')
    <span class="dbtns">@foreach (['today', 'tomorrow', 'week', 'month'] as $d)<a href="{{ route('wall.show', $q($d)) }}" @class(['dbtn', 'dbtn-on' => $day === $d])>{{ __('wall.days.'.$d) }}</a>@endforeach</span>
@endsection
@section('body')
<div class="cols">
    @foreach (['out' => 'h-out', 'in' => 'h-in'] as $dir => $cls)
        @php($list = $moves[$dir])
        <section class="panel col">
            <div class="col-head"><span class="{{ $cls }}">{{ mb_strtoupper(__('wall.'.$dir)) }}</span><b>{{ $list->count() }}</b></div>
            @if ($compact)
                @foreach ($list->groupBy(fn ($i) => $day === 'week' ? $i['at']->locale(app()->getLocale())->translatedFormat('l j M') : __('wall.week', ['n' => $i['at']->isoWeek()])) as $label => $rows)
                    <div class="gh">{{ $label }}<span>{{ $rows->count() }}</span></div>
                    @foreach ($rows->take(14) as $r)
                        <div class="crow"><b class="cw">{{ $day === 'week' ? (($r['no_time'] ?? false) ? '—' : $r['at']->format('H:i')) : $r['at']->locale(app()->getLocale())->translatedFormat('D j').(($r['no_time'] ?? false) ? '' : ' '.$r['at']->format('H:i')) }}</b><span class="cmain"><b>{{ $r['job'] }}</b><span class="cwho">{{ $r['who'] }}</span></span><span class="st st-{{ $r['st'] }}">{{ __('wall.st.'.$r['st']) }}</span></div>
                    @endforeach
                @endforeach
            @else
                @foreach ($list as $i)
                    <div @class(['card', 'card-late' => in_array($i['st'], ['due'])])>
                        <b class="tm">{{ ($i['no_time'] ?? false) ? '—' : $i['at']->format('H:i') }}</b>
                        <div class="cbody">
                            <div class="cjob">{{ $i['job'] }}</div>
                            <div class="cwho">{{ $i['who'] }}@if ($i['what']) · {{ $i['what'] }}@endif</div>
                            @if ($i['prog'])<div class="bar"><span class="bar-{{ $dir }}" style="width: {{ round($i['done'] / $i['total'] * 100) }}%"></span></div><div class="cprog">{{ $i['prog'] }}</div>@endif
                        </div>
                        <span class="st st-{{ $i['st'] }}">{{ __('wall.st.'.$i['st']) }}</span>
                    </div>
                @endforeach
            @endif
            @if ($list->isEmpty())<p class="empty">{{ __('wall.nothing') }}</p>@endif
        </section>
    @endforeach
</div>
<div class="bottom">
    <section class="panel">
        <p class="ptitle">{{ mb_strtoupper(__('wall.open_work')) }}</p>
        @foreach ($work as $k => $n)<div class="kv"><span>{{ __('wall.work.'.$k) }}</span><b @class(['hot' => $n > 0 && in_array($k, ['orders', 'factory'])])>{{ $n }}</b></div>@endforeach
    </section>
    <section class="panel">
        <p class="ptitle">{{ mb_strtoupper(__('wall.fleet')) }}</p>
        @forelse ($fleet as $cat => $pct)
            <div class="mrow"><span class="ml">{{ $cat }}</span><div class="mbar"><span style="width: {{ $pct }}%"></span></div><b>{{ $pct }}%</b></div>
        @empty
            <p class="empty">{{ __('wall.no_fleet') }}</p>
        @endforelse
    </section>
    <section class="panel">
        <p class="ptitle">AFRIT 6 &amp; FACTORY</p>
        <div class="kv"><span>{{ __('wall.desks') }}</span><b>{{ $venues['desks'] }}</b></div>
        <div class="kv"><span>{{ __('wall.room') }}</span><b>{{ trans_choice('wall.bookings', $venues['room'], ['n' => $venues['room']]) }}</b></div>
        <div class="kv"><span>{{ __('wall.shelf') }}</span><b @class(['hot' => $venues['shelf_overdue'] > 0])>{{ $venues['shelf_overdue'] }}</b></div>
        <div class="kv"><span>{{ __('wall.next_factory') }}</span><b>@if ($venues['factory']){{ $venues['factory']->firstDay()->locale(app()->getLocale())->translatedFormat('D j M') }} · {{ $venues['factory']->title }}@else — @endif</b></div>
    </section>
</div>
@endsection
