<x-layouts.app :title="__('orders.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('orders.kicker')) }}</div>
            <h1>{{ __('orders.title') }}</h1>
            <p class="muted">{{ __('orders.intro') }}</p>
        </div>
        <a class="btn" href="{{ route('subhire.index') }}">{{ __('orders.subhire_list') }} →</a>
    </div>
    @include('partials.flash')

    @if ($tab === 'open')
        <div class="tiles">
            @foreach (\App\Models\Rental\Order::STATES as $s)
                <a href="{{ route('orders.index', array_filter(['who' => $who, 'state' => $state === $s ? null : $s])) }}" @class(['tile', 'tile-'.$s, 'tile-on' => $state === $s])>
                    <span class="tile-k">{{ mb_strtoupper(__('orders.states.'.$s)) }}</span>
                    <b class="tile-v">{{ $counts[$s] }}</b>
                    <small class="muted">{{ __('orders.state_hints.'.$s) }}</small>
                </a>
            @endforeach
        </div>
    @endif

    <div class="filters">
        <nav class="tabs" aria-label="{{ __('orders.title') }}">
            <a href="{{ route('orders.index', ['who' => $who]) }}" @class(['tab', 'tab-on' => $tab === 'open'])>{{ __('orders.tab_open') }}</a>
            <a href="{{ route('orders.index', ['tab' => 'done', 'who' => $who]) }}" @class(['tab', 'tab-on' => $tab === 'done'])>{{ __('orders.tab_done') }}</a>
        </nav>
        <nav class="chips">
            <a href="{{ route('orders.index', array_filter(['tab' => $tab === 'done' ? 'done' : null, 'who' => 'me'])) }}" @class(['chip', 'chip-on' => $who === 'me'])>{{ __('orders.mine') }}</a>
            <a href="{{ route('orders.index', array_filter(['tab' => $tab === 'done' ? 'done' : null, 'who' => 'all'])) }}" @class(['chip', 'chip-on' => $who === 'all'])>{{ __('orders.everyone') }}</a>
        </nav>
    </div>

    <section class="card card-flush">
        <ul class="inbox">
            @forelse ($orders as $o)
                <li>
                    <a class="inbox-item" href="{{ route('orders.show', $o) }}">
                        <span class="inbox-main">
                            <b>{{ $o->title }}</b>
                            <small class="muted">{{ $o->number }} · {{ $o->customerName() }} · {{ __('orders.out', ['from' => $o->out_from->format('d/m'), 'until' => $o->out_until->format('d/m')]) }}</small>
                            <small class="muted">{{ __('orders.from_seller', ['name' => $o->handedBy?->name ?? '—', 'date' => $o->confirmed_at?->format('d/m H:i')]) }} · {{ __('orders.assignee', ['name' => $o->assignee?->name ?? __('orders.nobody')]) }}</small>
                        </span>
                        <span class="inbox-side">@include('rental.orders._state', ['state' => $states[$o->id]])</span>
                    </a>
                </li>
            @empty
                <li class="muted inbox-empty">{{ __('orders.none') }}</li>
            @endforelse
        </ul>
    </section>
    <p class="muted small">{{ __('orders.help') }}</p>
</x-layouts.app>
