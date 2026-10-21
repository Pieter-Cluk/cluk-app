@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('quotes.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.items.sales')) }}</div>
            <h1>{{ __('quotes.title') }}</h1>
            <p class="muted">{{ __('quotes.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @include('sales._tabs', ['section' => 'quotes'])
    <div class="filters">
        <nav class="chips">
            <a href="{{ route('quotes.index', array_filter(['owner' => $owner])) }}" @class(['chip', 'chip-on' => ! $status])>{{ __('deals.all_modules') }}</a>
            @foreach (['draft', 'sent', 'change', 'accepted', 'declined'] as $s)
                <a href="{{ route('quotes.index', array_filter(['status' => $s, 'owner' => $owner])) }}" @class(['chip', 'chip-on' => $status === $s])>{{ __('quotes.statuses.'.$s) }}</a>
            @endforeach
        </nav>
        <nav class="chips">
            <a href="{{ route('quotes.index', array_filter(['status' => $status])) }}" @class(['chip', 'chip-on' => $owner !== 'me'])>{{ __('deals.everyone') }}</a>
            <a href="{{ route('quotes.index', array_filter(['status' => $status, 'owner' => 'me'])) }}" @class(['chip', 'chip-on' => $owner === 'me'])>{{ __('quotes.mine') }}</a>
        </nav>
    </div>
    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('quotes.number') }}</th><th>{{ __('deals.fields.title') }}</th><th>{{ __('deals.customer') }}</th><th>{{ __('quotes.period') }}</th><th>{{ __('quotes.status') }}</th><th class="num">{{ __('quotes.total_excl') }}</th></tr></thead>
                <tbody>
                    @forelse ($quotes as $q)
                        <tr>
                            <td class="nowrap"><a href="{{ route('quotes.show', $q) }}"><b>{{ $q->code() }}</b></a></td>
                            <td>{{ $q->title }}<br><small class="muted">{{ $q->owner?->name }}</small></td>
                            <td>{{ $q->customerName() }}</td>
                            <td class="nowrap">{{ $q->use_from->format('d/m') }} – {{ $q->use_until->format('d/m/Y') }}</td>
                            <td>@include('sales.quotes._status', ['quote' => $q])</td>
                            <td class="num nowrap">{{ $eur($q->net_cents) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ __('quotes.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$quotes"/>
</x-layouts.app>
