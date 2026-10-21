@php($loc = app()->getLocale())
<x-layouts.app :title="__('workspace.staff.tabs.room')">
    @include('afrit6.staff._head', ['section' => 'room'])
    <div class="filters">
        <h2 class="h2">{{ __('workspace.staff.week_of', ['date' => $week->locale($loc)->translatedFormat('j F Y')]) }}</h2>
        <nav class="chips">
            <a class="chip" href="{{ route('afrit6.staff.room', ['week' => $week->copy()->subWeek()->toDateString()]) }}">← {{ __('workspace.staff.prev_week') }}</a>
            <a class="chip" href="{{ route('afrit6.staff.room') }}">{{ __('workspace.staff.this_week') }}</a>
            <a class="chip" href="{{ route('afrit6.staff.room', ['week' => $week->copy()->addWeek()->toDateString()]) }}">{{ __('workspace.staff.next_week') }} →</a>
        </nav>
    </div>
    <div class="week">
        @for ($i = 0; $i < 5; $i++)
            @php($d = $week->copy()->addDays($i))
            <section @class(['card', 'day', 'day-today' => $d->isToday()])>
                <h3 class="h3">{{ $d->locale($loc)->translatedFormat('l j M') }}</h3>
                @forelse ($bookings[$d->toDateString()] ?? [] as $b)
                    <div @class(['bk', 'bk-class' => $b->isClassroom()])><b>{{ $b->timeRange() }}</b><span>{{ $b->member->companyName() }} · {{ $b->member->name }}</span><small>{{ __('workspace.room.setups.'.$b->setup) }}@if ($b->note) · {{ $b->note }}@endif</small></div>
                @empty
                    <p class="muted small">{{ __('workspace.staff.no_bookings') }}</p>
                @endforelse
            </section>
        @endfor
    </div>
</x-layouts.app>
