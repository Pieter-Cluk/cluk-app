@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('orders.subhire_title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('orders.kicker')) }}</div>
            <h1>{{ __('orders.subhire_title') }}</h1>
            <p class="muted">{{ __('orders.subhire_intro') }}</p>
        </div>
        <a class="btn" href="{{ route('orders.index') }}">{{ __('orders.title') }} →</a>
    </div>
    @include('partials.flash')
    <div class="tiles">
        <a href="{{ route('subhire.index', ['f' => 'draft']) }}" @class(['tile', 'tile-subhire', 'tile-on' => $filter === 'draft'])><span class="tile-k">{{ mb_strtoupper(__('orders.sub_statuses.draft')) }}</span><b class="tile-v">{{ $counts['draft'] }}</b><small class="muted">{{ __('orders.tiles.draft') }}</small></a>
        <a href="{{ route('subhire.index', ['f' => 'sent']) }}" @class(['tile', 'tile-waiting', 'tile-on' => $filter === 'sent'])><span class="tile-k">{{ mb_strtoupper(__('orders.sub_statuses.sent')) }}</span><b class="tile-v">{{ $counts['sent'] }}</b><small class="muted">{{ __('orders.tiles.sent') }}</small></a>
        <a href="{{ route('subhire.index', ['tab' => 'shc', 'f' => 'running']) }}" @class(['tile', 'tile-ready', 'tile-on' => $filter === 'running'])><span class="tile-k">{{ mb_strtoupper(__('orders.tiles.running_k')) }}</span><b class="tile-v">{{ $counts['running'] }}</b><small class="muted">{{ __('orders.tiles.running') }}</small></a>
    </div>
    <div class="filters">
        <nav class="tabs">
            <a href="{{ route('subhire.index') }}" @class(['tab', 'tab-on' => $tab === 'shq'])>{{ __('orders.tab_shq') }}</a>
            <a href="{{ route('subhire.index', ['tab' => 'shc']) }}" @class(['tab', 'tab-on' => $tab === 'shc'])>{{ __('orders.tab_shc') }}</a>
        </nav>
    </div>
    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('quotes.number') }}</th><th>{{ __('orders.partner') }}</th><th>{{ __('orders.for_order') }}</th><th>{{ __('orders.items') }}</th><th>{{ __('quotes.period') }}</th><th class="num">{{ __('orders.amount') }}</th><th>{{ __('quotes.status') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="nowrap"><a href="{{ route('subhire.show', $r) }}"><b>{{ $r->code() }}</b></a><br><small class="muted">{{ $r->sent_at?->format('d/m H:i') ?? $r->created_at->format('d/m') }}</small></td>
                            <td>{{ $r->supplier->name }}@if ($r->their_ref)<br><small class="muted">{{ __('orders.their_ref_short', ['ref' => $r->their_ref]) }}</small>@endif</td>
                            <td>{{ $r->order->title }}<br><small class="muted">{{ $r->order->number }}</small></td>
                            <td>{{ $r->lines->map(fn ($l) => $l->qty.' × '.$l->name)->implode(' · ') }}</td>
                            <td class="nowrap">{{ $r->delivery_at?->format('d/m') }} – {{ $r->return_at?->format('d/m') }}</td>
                            <td class="num nowrap">{{ $r->total_cents ? $eur($r->total_cents) : '—' }}</td>
                            <td>@include('rental.subhire._status', ['r' => $r])</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">{{ __('orders.sub_none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$rows"/>
    <p class="muted small">{{ __('orders.sub_help') }}</p>
</x-layouts.app>
