<x-layouts.app :title="__('workspace.staff.tabs.desks')">
    @include('afrit6.staff._head', ['section' => 'desks'])
    <form class="filters" method="get"><h2 class="h2">{{ __('workspace.staff.desks_on', ['date' => $day->format('d/m/Y')]) }}</h2><input type="date" name="day" value="{{ $day->toDateString() }}" onchange="this.form.submit()" aria-label="{{ __('workspace.tasks.cols.when') }}"></form>
    <div class="desk-grid">
        @foreach ($desks as $s)
            @php($r = $rentals[$s->id] ?? null)
            <section @class(['card', 'desk-card', 'is-free' => ! $r])>
                <div class="desk-code">{{ $s->code }}</div>
                <small class="muted">{{ __('workspace.desks.floors.'.$s->floor) }} · {{ __('workspace.desks.zones.'.$s->zone) }}</small>
                @if ($r)
                    <a href="{{ route('afrit6.staff.show', $r->member) }}"><b>{{ $r->member->name }}</b></a>
                    <small>{{ $r->member->companyName() }} · {{ $r->ends_on ? __('workspace.desks.until', ['date' => $r->ends_on->format('d/m/Y')]) : __('workspace.desks.open_ended') }}</small>
                @else
                    <span class="pill pill-ok">{{ __('workspace.staff.free_desk') }}</span>
                @endif
                @foreach ($dayBookings[$s->id] ?? [] as $db)
                    <small><span class="pill pill-info">{{ __('workspace.days.parts.'.$db->part) }}</span> <a href="{{ route('afrit6.staff.show', $db->member) }}">{{ $db->member->name }}</a></small>
                @endforeach
                @foreach (($next[$s->id] ?? collect())->take(1) as $n)
                    <small class="muted">{{ __('workspace.staff.next_rental', ['name' => $n->member->name, 'date' => $n->starts_on->format('d/m/Y')]) }}</small>
                @endforeach
            </section>
        @endforeach
    </div>
</x-layouts.app>
