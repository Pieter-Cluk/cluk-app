@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('nav.items.productsales')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.commercial')) }} / {{ mb_strtoupper(__('nav.items.productsales')) }}</div>
            <h1>{{ __('nav.items.productsales') }}</h1>
            <p class="muted">{{ __('psales.intro') }}</p>
        </div>
        @if ($canWork)<a class="primary" href="{{ route('psales.create') }}">{{ __('psales.new') }}</a>@endif
    </div>
    @include('partials.flash')
    @include('psales._tabs')
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
