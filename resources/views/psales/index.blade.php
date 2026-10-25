@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('quotes.kinds.sales')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.items.sales')) }}</div>
            <h1>{{ __('quotes.title') }}</h1>
            <p class="muted">{{ __('quotes.kinds.sales_intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @include('sales._tabs', ['section' => 'quotes'])
    @include('sales.quotes._kinds', ['kind' => 'sales'])
    <nav class="chips mt">
        @foreach (\App\Http\Controllers\ProductSales\SalesQuoteController::TABS as $t)<a @class(['chip', 'chip-on' => $tab === $t]) href="{{ route('psales.index', ['tab' => $t]) }}">{{ __('psales.list_tabs.'.$t) }} · {{ $counts[$t] }}</a>@endforeach
    </nav>
    <section class="card card-flush mt">
        <ul class="inbox">
            @forelse ($quotes as $q)
                <li><a class="inbox-item" href="{{ route('psales.show', $q) }}">
                    <span class="inbox-main"><b>{{ $q->title }}</b><small class="muted">{{ $q->code() }} · {{ $q->customerName() }}@if ($q->owner) · {{ $q->owner->name }}@endif · {{ trans_choice('psales.lines_n', $q->lines->where('kind', 'item')->count(), ['n' => $q->lines->where('kind', 'item')->count()]) }}</small></span>
                    <span class="inbox-side"><b>{{ $eur($q->net_cents) }}</b> @include('psales._status')</span>
                </a></li>
            @empty
                <li class="muted inbox-empty">{{ __('psales.none') }}</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
