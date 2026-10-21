@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('nav.items.sales')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.commercial')) }}</div>
            <h1>{{ __('nav.items.sales') }}</h1>
            <p class="muted">{{ __('deals.totals', ['count' => $totals['count'], 'value' => $eur($totals['value']), 'weighted' => $eur($totals['weighted'])]) }}</p>
        </div>
        @if ($canWork)<a class="primary" href="{{ route('deals.create') }}">{{ __('deals.new') }}</a>@endif
    </div>
    @include('partials.flash')
    @include('sales._tabs', ['section' => $view === 'closed' ? 'closed' : 'board'])

    <form method="get" class="filters">
        @if ($view === 'closed')<input type="hidden" name="view" value="closed">@endif
        <nav class="chips">
            <a href="{{ route('deals.index', array_filter(['view' => $view === 'closed' ? 'closed' : null, 'owner' => $owner])) }}" @class(['chip', 'chip-on' => ! $module])>{{ __('deals.all_modules') }}</a>
            @foreach (\App\Models\Sales\Deal::MODULES as $m)
                <a href="{{ route('deals.index', array_filter(['module' => $m, 'view' => $view === 'closed' ? 'closed' : null, 'owner' => $owner])) }}" @class(['chip', 'chip-on' => $module === $m])>{{ __('deals.modules.'.$m) }}</a>
            @endforeach
        </nav>
        @if ($module)<input type="hidden" name="module" value="{{ $module }}">@endif
        <select name="owner" onchange="this.form.submit()" aria-label="{{ __('deals.fields.owner_id') }}">
            <option value="">{{ __('deals.everyone') }}</option>
            <option value="me" @selected($owner === 'me')>{{ __('deals.mine') }}</option>
            @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $owner === (string) $o->id)>{{ $o->name }}</option>@endforeach
        </select>
    </form>

    @if ($view === 'closed')
        <section class="card card-flush">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('deals.fields.title') }}</th><th>{{ __('deals.customer') }}</th><th>{{ __('deals.fields.module') }}</th><th>{{ __('deals.result') }}</th><th class="num">{{ __('deals.fields.value') }}</th><th>{{ __('deals.closed_on') }}</th></tr></thead>
                    <tbody>
                        @forelse ($closed as $d)
                            <tr>
                                <td><a href="{{ route('deals.show', $d) }}"><b>{{ $d->title }}</b></a><br><small class="muted">{{ $d->number }}</small></td>
                                <td>{{ $d->customerName() }}</td>
                                <td>{{ __('deals.modules.'.$d->module) }}</td>
                                <td><span @class(['pill', 'pill-ok' => $d->stage === 'won', 'pill-danger' => $d->stage === 'lost', 'pill-off' => $d->stage === 'disqualified'])>{{ __('deals.stages.'.$d->stage) }}</span>@if ($d->lost_reason) <small class="muted">{{ __('deals.lost_reasons.'.$d->lost_reason) }}</small>@endif</td>
                                <td class="num nowrap">{{ $eur($d->value_cents) }}</td>
                                <td class="nowrap muted">{{ $d->closed_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">{{ __('deals.none_closed') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <x-pager :items="$closed"/>
    @else
        <div class="board">
            @foreach ($columns as $stage => $col)
                <section class="board-col">
                    <header class="board-head board-{{ $stage }}">
                        <div><b>{{ __('deals.stages.'.$stage) }}</b><small>{{ __('deals.stage_hints.'.$stage) }} · {{ \App\Models\Sales\Deal::PROBABILITY[$stage] }}%</small></div>
                        <span class="muted small">{{ $col['deals']->count() }} · {{ $eur($col['sum']) }}</span>
                    </header>
                    @forelse ($col['deals'] as $d)
                        <a class="deal-card" href="{{ route('deals.show', $d) }}">
                            <b>{{ $d->title }}</b>
                            <span class="muted small">{{ $d->customerName() }}</span>
                            <span class="deal-meta"><span class="pill">{{ __('deals.modules.'.$d->module) }}</span><span>{{ $eur($d->value_cents) }}</span></span>
                            <span class="deal-meta muted small"><span>{{ $d->event_start ? $d->event_start->locale(app()->getLocale())->translatedFormat('j M') : '—' }}</span><span class="av av-sm" title="{{ $d->owner?->name }}">{{ $d->owner?->initials ?? '?' }}</span></span>
                        </a>
                    @empty
                        <p class="muted small board-empty">{{ __('deals.empty_col') }}</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
