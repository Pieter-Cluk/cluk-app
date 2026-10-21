@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
@php($tomorrow = today()->addDay())
<x-layouts.portal :title="__('workspace.desks.title')" tab="desks">
    <div class="pagehead">
        <div>
            <h1>{{ __('workspace.desks.title') }}</h1>
            <p class="muted">{{ __('workspace.desks.intro') }}</p>
        </div>
    </div>

    <section class="card card-accent">
        <h2>{{ __('workspace.days.title') }}</h2>
        <p class="muted">{{ __('workspace.days.intro', ['half' => $eur((int) config('afrit6.prices.desk_half_day')), 'day' => $eur((int) config('afrit6.prices.desk_day'))]) }}</p>
        <form method="get" action="{{ route('afrit6.portal.desks') }}" class="inline-form mt">
            <input type="date" name="day" value="{{ $dayDate->toDateString() }}" min="{{ today()->toDateString() }}" max="{{ today()->addWeeks((int) config('afrit6.room.weeks_ahead'))->toDateString() }}" aria-label="{{ __('workspace.days.date') }}">
            <select name="part" aria-label="{{ __('workspace.days.part') }}">
                @foreach (\App\Models\Afrit6\DeskBooking::PARTS as $p)<option value="{{ $p }}" @selected($part === $p)>{{ __('workspace.days.parts.'.$p) }} · {{ implode('–', config('afrit6.desk_parts.'.$p)) }}</option>@endforeach
            </select>
            <button class="btn" type="submit">{{ __('workspace.days.show') }}</button>
        </form>
        <h3 class="h3">{{ __('workspace.days.free_on', ['date' => $dayDate->locale(app()->getLocale())->translatedFormat('l j F'), 'part' => __('workspace.days.parts.'.$part)]) }}</h3>
        @if ($partStarted)
            <p class="muted">{{ __('workspace.room.err_past') }}</p>
        @elseif ($freeForDay->isEmpty())
            <p class="muted">{{ __('workspace.days.none_free') }}</p>
        @else
            <div class="desk-row">
                @foreach ($freeForDay as $s)
                    <form method="post" action="{{ route('afrit6.portal.desks.day') }}">
                        @csrf
                        <input type="hidden" name="space_id" value="{{ $s->id }}"><input type="hidden" name="date" value="{{ $dayDate->toDateString() }}"><input type="hidden" name="part" value="{{ $part }}">
                        <button class="desk d-free wide" type="submit" aria-label="{{ __('workspace.days.book', ['desk' => $s->code, 'amount' => $eur(\App\Models\Afrit6\DeskBooking::price($part))]) }}"><b>{{ $s->code }}</b><small>{{ __('workspace.days.book_short', ['amount' => $eur(\App\Models\Afrit6\DeskBooking::price($part))]) }}</small></button>
                    </form>
                @endforeach
            </div>
        @endif
        @if ($dayBookings->isNotEmpty())
            <h3 class="h3">{{ __('workspace.days.yours') }}</h3>
            @foreach ($dayBookings as $b)
                <div class="p-line">
                    <span><b>{{ $b->space->code }} · {{ $b->date->locale(app()->getLocale())->translatedFormat('D j M') }}</b> <small class="muted">{{ __('workspace.days.parts.'.$b->part) }} · {{ $b->hours() }} · {{ $eur($b->price_cents) }}</small></span>
                    @unless ($b->hasStarted())
                        <form method="post" action="{{ route('afrit6.portal.desks.day.cancel', $b) }}" onsubmit="return confirm(@js(__('workspace.room.cancel_confirm')))">@csrf<button class="linkbtn" type="submit">{{ __('workspace.room.cancel') }}</button></form>
                    @endunless
                </div>
            @endforeach
        @endif
    </section>

    <h2 class="h2">{{ __('workspace.desks.fixed_title') }}</h2>
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

            @if ($office)
                <section class="card">
                    <h2>{{ __('workspace.office.title') }}</h2>
                    <p class="muted">{{ __('workspace.office.intro') }}</p>
                    @if ($officeMine)
                        <p><span class="pill pill-ok">{{ __('workspace.desks.state.mine') }}</span></p>
                    @elseif ($officeRequest)
                        <p><span class="pill pill-warn">{{ __('workspace.services.status.'.$officeRequest->status) }}</span></p>
                        <p class="small"><a href="{{ route('afrit6.portal.services') }}">{{ __('workspace.office.follow') }} →</a></p>
                    @else
                        <details class="svc">
                            <summary><span class="btn">{{ __('workspace.office.ask') }}</span></summary>
                            <form method="post" action="{{ route('afrit6.portal.office') }}" class="form-grid form-grid-1">
                                @csrf
                                <label class="field"><span>{{ __('workspace.office.from') }}</span><input type="date" name="date" min="{{ today()->toDateString() }}" value="{{ old('date', today()->addMonth()->startOfMonth()->toDateString()) }}" required></label>
                                <label class="field"><span>{{ __('workspace.office.people') }}</span><input type="number" name="people" min="1" max="20" value="{{ old('people', 2) }}" required></label>
                                <label class="field"><span>{{ __('workspace.office.wishes') }}</span><textarea name="wishes" rows="3" maxlength="2000" placeholder="{{ __('workspace.office.wishes_hint') }}">{{ old('wishes') }}</textarea></label>
                                <button class="primary" type="submit">{{ __('workspace.office.send') }}</button>
                            </form>
                        </details>
                    @endif
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
