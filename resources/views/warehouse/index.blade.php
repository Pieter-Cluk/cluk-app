@php($fmt = fn ($d) => $d->locale(app()->getLocale())->translatedFormat('D j M H:i'))
<x-layouts.app :title="__('warehouse.office.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }} / {{ mb_strtoupper(__('warehouse.office.title')) }}</div>
            <h1>{{ __('warehouse.office.title') }}</h1>
            <p class="muted">{{ __('warehouse.office.intro') }}</p>
        </div>
        <div class="inline-form">
            @if (auth()->user()->hasRight('warehouse.setup_locations'))<a class="btn" href="{{ route('locations.index') }}">{{ __('nav.items.locations') }}</a>@endif
            <a class="btn" href="{{ route('warehouse.labels') }}">{{ __('warehouse.labels.title') }}</a>
            <a class="primary" href="{{ route('w.home') }}">{{ __('warehouse.office.open_app') }}</a>
        </div>
    </div>
    @include('partials.flash')

    <div class="tiles">
        <div class="tile tile-waiting"><span class="tile-k">{{ mb_strtoupper(__('warehouse.office.t_picks')) }}</span><b class="tile-v">{{ $picks }}</b><small class="muted">{{ __('warehouse.office.t_picks_s') }}</small></div>
        <div class="tile"><span class="tile-k">{{ mb_strtoupper(__('warehouse.office.t_out')) }}</span><b class="tile-v">{{ $out }}</b><small class="muted">{{ __('warehouse.office.t_out_s') }}</small></div>
        <div class="tile tile-subhire"><span class="tile-k">{{ mb_strtoupper(__('warehouse.office.t_repair')) }}</span><b class="tile-v">{{ $repair->count() }}</b><small class="muted">{{ __('warehouse.office.t_repair_s') }}</small></div>
        <div class="tile tile-ready"><span class="tile-k">{{ mb_strtoupper(__('warehouse.office.t_charges')) }}</span><b class="tile-v">{{ $charges->sum('pending') }}</b><small class="muted">{{ __('warehouse.office.t_charges_s') }}</small></div>
    </div>

    <section class="card">
        <h2>{{ __('warehouse.office.zones') }}</h2>
        <div class="wz-route">
            @foreach ($zones as $z)
                <a href="{{ route('warehouse.index', ['zone' => $z->code]) }}" @class(['wz', 'wz-'.$z->type, 'wz-on' => $zone?->is($z)])>
                    <b>{{ $z->label() }}</b><small>{{ $z->code }}</small><span class="wz-n">{{ $zoneCounts[$z->code] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        <p class="muted small">{{ __('warehouse.office.route_text') }}</p>
        @if ($zone)
            <h3>{{ $zone->label() }} · {{ $list->count() }}</h3>
            @if ($list->isEmpty())
                <p class="muted">{{ __('warehouse.office.zone_empty') }}</p>
            @else
                <table class="table"><thead><tr><th>{{ __('warehouse.office.piece') }}</th><th>{{ __('warehouse.office.item') }}</th><th>{{ __('warehouse.office.place') }}</th><th>{{ __('warehouse.office.status') }}</th></tr></thead>
                    <tbody>@foreach ($list as $a)<tr><td><a href="{{ route('equipment.assets.show', $a) }}">{{ $a->code }}</a></td><td>{{ $a->item->name }}</td><td>{{ $a->location }}</td><td>{{ __('equipment.statuses.'.$a->status) }}</td></tr>@endforeach</tbody></table>
            @endif
        @endif
    </section>

    @if ($deliveries->isNotEmpty())
        <section class="card">
            <h2>{{ __('warehouse.delivery.who_office') }}</h2>
            @foreach ($deliveries as $d)
                <div class="wd-row">
                    <div class="wd-photos">@foreach ($d->photos ?? [] as $i => $p)<a href="{{ route('w.media', ['delivery', $d->id, $i]) }}" target="_blank"><img src="{{ route('w.media', ['delivery', $d->id, $i]) }}" alt="" loading="lazy"></a>@endforeach</div>
                    <div class="wd-main">
                        <b>{{ trim(($d->colli ? trans_choice('warehouse.colli_n', $d->colli, ['n' => $d->colli]) : '').' '.($d->pallets ? trans_choice('warehouse.pallets_n', $d->pallets, ['n' => $d->pallets]) : '')) }}@if ($d->carrier) · {{ $d->carrier }}@endif</b>
                        <small class="muted">{{ $d->label_text }} · {{ $d->user?->first_name }} · {{ $fmt($d->created_at) }}@if ($d->damaged) · <span class="txt-danger">{{ __('warehouse.delivery.damaged') }}</span>@endif</small>
                        @if ($d->isOverdue())<span class="pill pill-danger">{{ __('warehouse.delivery.overdue') }}</span>@endif
                        @if ($canMove)
                            <form method="post" action="{{ route('warehouse.delivery.assign', $d) }}" class="inline-form mt">@csrf
                                <select name="member_id" aria-label="{{ __('warehouse.delivery.m_member') }}"><option value="">{{ __('warehouse.delivery.not_member') }}</option>@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->companyName() }}</option>@endforeach</select>
                                <input name="note" maxlength="500" placeholder="{{ __('warehouse.delivery.close_note') }}" aria-label="{{ __('warehouse.delivery.close_note') }}">
                                <button class="btn btn-sm" type="submit">{{ __('warehouse.delivery.close') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <div class="split-2">
        <section class="card card-flush">
            <div class="card-head pad"><h2>{{ __('warehouse.office.charges') }}</h2></div>
            <ul class="inbox">
                @forelse ($charges as $o)
                    <li><a class="inbox-item" href="{{ route('warehouse.charges', $o) }}"><span class="inbox-main"><b>{{ $o->title }}</b><small class="muted">{{ $o->number }} · {{ $o->customerName() }}</small></span><span class="inbox-side"><span class="pill pill-warn">{{ trans_choice('warehouse.office.lines_waiting', $o->pending, ['n' => $o->pending]) }}</span></span></a></li>
                @empty
                    <li class="muted inbox-empty">{{ __('warehouse.office.no_charges') }}</li>
                @endforelse
            </ul>
        </section>
        <section class="card card-flush">
            <div class="card-head pad"><h2>{{ __('warehouse.office.repair') }}</h2></div>
            <ul class="inbox">
                @forelse ($repair as $a)
                    <li class="inbox-item"><span class="inbox-main"><b><a href="{{ route('equipment.assets.show', $a) }}">{{ $a->code }}</a> · {{ $a->item->name }}</b><small class="muted">{{ $a->repair_released_at ? __('warehouse.office.released_at', ['when' => $fmt($a->repair_released_at)]) : __('warehouse.office.not_rentable') }}</small></span>
                        <span class="inbox-side">@if ($canRelease && ! $a->repair_released_at)<form method="post" action="{{ route('warehouse.release', $a) }}">@csrf<button class="btn btn-sm" type="submit">{{ __('warehouse.office.release') }}</button></form>@endif</span></li>
                @empty
                    <li class="muted inbox-empty">{{ __('warehouse.office.no_repair') }}</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="split-2">
        <section class="card card-flush">
            <div class="card-head pad"><h2>{{ __('warehouse.office.moves') }}</h2></div>
            <ul class="inbox">
                @forelse ($moves as $m)
                    <li class="inbox-item"><span class="inbox-main"><b>{{ $m->asset?->code ?? ($m->item?->name.' × '.abs($m->qty)) }} · {{ __('warehouse.kinds.'.$m->kind) }}</b>
                        <small class="muted">{{ \App\Services\Warehouse\Moves::label($m->from_code) }} → {{ \App\Services\Warehouse\Moves::label($m->to_code) }}@if ($m->order) · {{ $m->order->number }}@endif · {{ $m->user?->first_name }} · {{ $fmt($m->created_at) }}</small></span></li>
                @empty
                    <li class="muted inbox-empty">{{ __('warehouse.office.no_moves') }}</li>
                @endforelse
            </ul>
        </section>
        <section class="card card-flush">
            <div class="card-head pad"><h2>{{ __('warehouse.office.lost') }}</h2></div>
            <ul class="inbox">
                @forelse ($lost as $m)
                    <li class="inbox-item"><span class="inbox-main"><b>{{ $m->item?->number }} · {{ $m->item?->name }} · {{ $m->qty }}</b><small class="muted">{{ __('warehouse.office.stock_now', ['n' => $m->item?->stock_qty]) }}@if ($m->order) · {{ $m->order->number }}@endif · {{ $m->note }} · {{ $fmt($m->created_at) }}</small></span></li>
                @empty
                    <li class="muted inbox-empty">{{ __('warehouse.office.no_lost') }}</li>
                @endforelse
            </ul>
            <p class="muted small ftask-foot">{{ __('warehouse.office.lost_hint') }}</p>
        </section>
    </div>
</x-layouts.app>
