@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('factory.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }} / CLUK FACTORY</div>
            <h1>{{ __('factory.title') }}</h1>
            <p class="muted">{{ __('factory.intro') }}</p>
        </div>
        <div class="inline-form">
            <a class="btn" href="{{ route('factory.book') }}" target="_blank" rel="noopener">{{ __('factory.open_page') }}</a>
            @if ($canWork)<a class="primary" href="{{ route('factory.create') }}">{{ __('factory.new') }}</a>@endif
        </div>
    </div>
    @include('partials.flash')

    <div class="tiles">
        <div class="tile tile-subhire"><span class="tile-k">{{ mb_strtoupper(__('factory.tiles.requests')) }}</span><b class="tile-v">{{ $tiles['requests'] }}</b><small class="muted">{{ __('factory.tiles.requests_s') }}</small></div>
        <div class="tile tile-waiting"><span class="tile-k">{{ mb_strtoupper(__('factory.tiles.options')) }}</span><b class="tile-v">{{ $tiles['options'] }}</b><small class="muted">{{ __('factory.tiles.options_s', ['n' => config('factory.option_days')]) }}</small></div>
        <div class="tile tile-ready"><span class="tile-k">{{ mb_strtoupper(__('factory.tiles.events', ['month' => $month->locale(app()->getLocale())->translatedFormat('F')])) }}</span><b class="tile-v">{{ $tiles['events'] }}</b><small class="muted">{{ __('factory.tiles.events_s') }}</small></div>
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('factory.tiles.usage')) }}</span><b class="tile-v">{{ $tiles['usage'] }}%</b><small class="muted">{{ __('factory.tiles.usage_s', ['used' => $tiles['used'], 'days' => $tiles['days']]) }}</small></div>
    </div>

    @if ($requests->isNotEmpty())
        <section class="card card-flush">
            <div class="card-head pad"><h2>{{ __('factory.requests') }}</h2><span class="pill pill-warn">{{ $requests->count() }}</span></div>
            <ul class="inbox">
                @foreach ($requests as $r)
                    <li><a class="inbox-item" href="{{ route('factory.show', $r) }}">
                        <span class="inbox-main"><b>{{ __('factory.types.'.$r->event_type) }} · {{ $r->start_date->locale(app()->getLocale())->translatedFormat('D j M Y') }} · {{ trans_choice('factory.guests_n', $r->guests, ['n' => $r->guests]) }} · {{ $r->size }} · {{ __('factory.levels.'.$r->level) }}</b>
                            <small class="muted">{{ $r->customerName() }} · {{ __('factory.sources.'.$r->source) }} · {{ $r->created_at->locale(app()->getLocale())->diffForHumans() }} · {{ $r->estimateCents() !== null ? __('factory.from', ['amount' => $eur($r->estimateCents())]) : __('factory.custom') }}</small></span>
                        <span class="inbox-side">@if ($r->clashes())<span class="pill pill-danger">{{ __('factory.not_free') }}</span>@else<span class="pill pill-ok">{{ __('factory.free') }}</span>@endif</span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card">
        <div class="card-head">
            <h2>{{ $month->locale(app()->getLocale())->translatedFormat('F Y') }}</h2>
            <nav class="chips">
                <a class="chip" href="{{ route('factory.index', ['m' => $month->copy()->subMonth()->format('Y-m')]) }}">‹ {{ $month->copy()->subMonth()->locale(app()->getLocale())->translatedFormat('F') }}</a>
                <a class="chip" href="{{ route('factory.index') }}">{{ __('factory.today') }}</a>
                <a class="chip" href="{{ route('factory.index', ['m' => $month->copy()->addMonth()->format('Y-m')]) }}">{{ $month->copy()->addMonth()->locale(app()->getLocale())->translatedFormat('F') }} ›</a>
            </nav>
        </div>
        <div class="fcal">
            @foreach (range(0, 6) as $i)<div class="fdow">{{ \Illuminate\Support\Carbon::create(2026, 10, 19)->addDays($i)->locale(app()->getLocale())->translatedFormat('D') }}</div>@endforeach
            @for ($i = 0; $i < ($month->copy()->startOfMonth()->dayOfWeekIso - 1); $i++)<div class="fday fday-empty"></div>@endfor
            @for ($d = $month->copy()->startOfMonth(); $d->lte($month->copy()->endOfMonth()); $d->addDay())
                @php($o = $occ[$d->toDateString()] ?? null)
                <div @class(['fday', 'fday-past' => $d->lt(today()), 'fday-today' => $d->isToday()])>
                    <span class="fnum">{{ $d->day }}</span>
                    @if ($o)
                        <a href="{{ route('factory.show', $o['booking']) }}" @class(['fbar', 'fbar-'.$o['part'], 'fbar-option' => $o['booking']->status === 'option'])>{{ $o['part'] !== 'event' ? __('factory.parts.'.$o['part']).' · ' : '' }}{{ $o['booking']->title }}</a>
                    @endif
                </div>
            @endfor
        </div>
        <div class="legend-row"><span><i class="fbar-event"></i>{{ __('factory.parts.event') }}</span><span><i class="fbar-buildup"></i>{{ __('factory.parts.buildup') }} / {{ __('factory.parts.breakdown') }}</span><span><i class="fbar-option"></i>{{ __('factory.statuses.option') }}</span></div>
    </section>

    <section class="card card-flush">
        <div class="card-head pad"><h2>{{ __('factory.bookings') }}</h2></div>
        <ul class="inbox">
            @forelse ($bookings as $b)
                <li><a class="inbox-item" href="{{ route('factory.show', $b) }}">
                    <span class="date-chip"><b>{{ $b->start_date->day }}</b><small>{{ $b->start_date->locale(app()->getLocale())->translatedFormat('M') }}</small></span>
                    <span class="inbox-main"><b>{{ $b->title }}</b><small class="muted">{{ $b->customerName() }} · {{ $b->size }} · {{ __('factory.levels.'.$b->level) }} · {{ trans_choice('factory.guests_n', $b->guests, ['n' => $b->guests]) }}@if ($b->owner) · {{ $b->owner->name }}@endif</small></span>
                    <span class="inbox-side">@include('factory._status')</span>
                </a></li>
            @empty
                <li class="muted inbox-empty">{{ __('factory.none') }}</li>
            @endforelse
        </ul>
    </section>
</x-layouts.app>
