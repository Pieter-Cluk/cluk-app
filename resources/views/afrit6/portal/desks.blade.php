@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
@php($tomorrow = today()->addDay())
<x-layouts.portal :title="__('workspace.desks.title')" tab="desks">
    <div class="pagehead">
        <div>
            <h1>{{ __('workspace.desks.title') }}</h1>
            <p class="muted">{{ __('workspace.desks.intro') }}</p>
        </div>
    </div>

    <div class="split">
        <section class="card">
            @foreach (['up', 'ground'] as $floor)
                @continue(! isset($floors[$floor]))
                <h2>{{ __('workspace.desks.floors.'.$floor) }}</h2>
                @foreach ($floors[$floor]->groupBy(fn ($d) => $d['space']->zone) as $zone => $list)
                    <div class="zone">
                        <div class="zone-name">{{ __('workspace.desks.zones.'.$zone) }}</div>
                        <div class="desk-row">
                            @foreach ($list as $d)
                                <a href="{{ route('afrit6.portal.desks', ['desk' => $d['space']->code]) }}"
                                   @class(['desk', 'd-'.$d['state'], 'd-sel' => $selected && $selected['space']->id === $d['space']->id])
                                   aria-label="{{ $d['space']->code }}, {{ __('workspace.desks.state.'.$d['state']) }}">
                                    <b>{{ $d['space']->code }}</b><small>{{ __('workspace.desks.state.'.$d['state']) }}</small>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
            <div class="legend">
                <span><i class="lg d-mine"></i>{{ __('workspace.desks.state.mine') }}</span>
                <span><i class="lg d-free"></i>{{ __('workspace.desks.state.free') }}</span>
                <span><i class="lg d-taken"></i>{{ __('workspace.desks.state.taken') }}</span>
            </div>
        </section>

        <aside class="stack">
            @if ($selected)
                @php($code = $selected['space']->code)
                <section class="card card-accent">
                    @if ($selected['state'] === 'free')
                        <h2>{{ __('workspace.desks.selected_free', ['desk' => $code]) }}</h2>
                        @foreach ($mine as $r)
                            <form method="post" action="{{ route('afrit6.portal.desks.swap') }}" class="mt">
                                @csrf
                                <input type="hidden" name="from" value="{{ $r->space_id }}">
                                <input type="hidden" name="to" value="{{ $selected['space']->id }}">
                                <button class="btn wide" type="submit">{{ __('workspace.desks.swap', ['from' => $r->space->code, 'to' => $code]) }}</button>
                            </form>
                        @endforeach
                        @if ($mine->isNotEmpty())<p class="muted small mt">{{ __('workspace.desks.swap_note') }}</p>@endif

                        <h3 class="h3">{{ __('workspace.desks.add_title', ['desk' => $code]) }}</h3>
                        <form method="post" action="{{ route('afrit6.portal.desks.add') }}" class="form-grid form-grid-1">
                            @csrf
                            <input type="hidden" name="space_id" value="{{ $selected['space']->id }}">
                            <label class="field"><span>{{ __('workspace.desks.add_from') }}</span><input type="date" name="date" min="{{ $tomorrow->toDateString() }}" value="{{ old('date', $tomorrow->toDateString()) }}" required></label>
                            <label class="field"><span>{{ __('workspace.desks.comment') }}</span><input name="note" maxlength="500" value="{{ old('note') }}"></label>
                            <p class="muted small">{{ __('workspace.desks.add_price', ['amount' => $eur((int) config('afrit6.prices.desk_month'))]) }}</p>
                            <button class="primary" type="submit">{{ __('workspace.desks.add_btn') }}</button>
                        </form>
                    @elseif ($selected['state'] === 'mine')
                        <h2>{{ __('workspace.desks.selected_mine', ['desk' => $code]) }}</h2>
                    @else
                        <h2>{{ __('workspace.desks.selected_taken', ['desk' => $code]) }}</h2>
                    @endif
                    <p class="mt"><a href="{{ route('afrit6.portal.desks') }}">{{ __('workspace.desks.close') }}</a></p>
                </section>
            @endif

            <section class="card">
                <h2>{{ __('workspace.portal.your_desks') }}</h2>
                @forelse ($mine as $r)
                    <div class="p-line">
                        <b>{{ $r->space->code }}</b>
                        <span class="muted">
                            @if ($r->starts_on->gt(today())){{ __('workspace.desks.from', ['date' => $r->starts_on->format('d/m/Y')]) }} · @endif
                            {{ $r->ends_on ? __('workspace.desks.until', ['date' => $r->ends_on->format('d/m/Y')]) : __('workspace.desks.open_ended') }}
                            · {{ __('workspace.desks.per_month', ['amount' => $eur($r->monthly_price_cents)]) }}
                        </span>
                    </div>
                @empty
                    <p class="muted">{{ __('workspace.portal.no_desks') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.portal>
