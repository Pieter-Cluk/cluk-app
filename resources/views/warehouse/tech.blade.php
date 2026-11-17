@php($say = fn ($t) => \App\Models\Warehouse\CheckProcedure::say($t))
<x-layouts.app :title="__('nav.items.techcheck')">
    <div class="pagehead"><div>
        <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }} / {{ mb_strtoupper(__('nav.items.techcheck')) }}</div>
        <h1>{{ __('nav.items.techcheck') }}</h1>
        <p class="muted">{{ __('checks.tech.intro') }}</p>
    </div>
        @if (auth()->user()->hasRight('warehouse.build_checks'))<a class="btn" href="{{ route('checks.index') }}">{{ __('nav.items.checkbuilder') }}</a>@endif
    </div>
    @include('partials.flash')
    <section class="card card-flush">
        <div class="card-head pad"><h2>{{ __('checks.tech.open') }} <small class="muted">{{ $open->count() }}</small></h2></div>
        <table class="table">
            <thead><tr><th>{{ __('checks.tech.piece') }}</th><th>{{ __('checks.tech.from') }}</th><th>{{ __('checks.tech.procedure') }}</th><th>{{ __('checks.tech.progress') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($open as $c)
                    @php($v = $c->procedureVersion ?? $c->asset->item?->checkProcedure?->current)
                    <tr>
                        <td><b>{{ $c->asset->item?->name }}</b><br><small class="muted">{{ $c->asset->code }}</small></td>
                        <td>@if ($c->order){{ $c->order->number }}<br><small class="muted">{{ $c->order->customerName() }}</small>@else—@endif</td>
                        <td>@if ($v){{ $v->procedure?->name() }} · v{{ $v->version }}@else<span class="muted">{{ __('checks.tech.simple') }}</span>@endif</td>
                        <td>@if ($c->procedure_version_id){{ __('checks.tech.step_n', ['n' => min($c->step, count($v->steps ?? [])), 'total' => count($v->steps ?? [])]) }}@else{{ $c->cleaned_at ? __('checks.tech.cleaned') : __('checks.tech.waiting') }}@endif</td>
                        <td><a class="btn btn-sm" href="{{ route('w.check', $c->asset) }}">{{ $c->procedure_version_id ? __('checks.tech.continue') : __('checks.tech.start') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('checks.tech.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    @if ($notices->isNotEmpty())
        <section class="card card-flush mt">
            <div class="card-head pad"><h2>{{ __('checks.tech.costs') }}</h2></div>
            <table class="table">
                <tbody>
                    @foreach ($notices as $n)
                        <tr><td><a href="{{ route('warehouse.charges', $n->order) }}"><b>{{ $n->order->number }}</b> · {{ $n->order->customerName() }}</a></td>
                            <td>{{ __('checks.notice.status.'.$n->status) }}@if ($n->status === 'sent') · {{ __('checks.notice.until', ['date' => $n->deadline->format('d/m/Y')]) }}@endif</td>
                            <td class="muted">{{ $n->order->company?->accountOwner?->name ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
    <section class="card card-flush mt">
        <div class="card-head pad"><h2>{{ __('checks.tech.recent') }}</h2></div>
        <table class="table">
            <tbody>
                @foreach ($done as $c)
                    <tr><td>{{ $c->asset?->item?->name }} <small class="muted">{{ $c->asset?->code }}</small></td><td>{{ $c->order?->number ?? '—' }}</td>
                        <td><span @class(['pill', 'pill-ok' => $c->result === 'ok', 'pill-danger' => $c->result === 'repair'])>{{ __('checks.tech.result.'.$c->result) }}</span></td>
                        <td class="muted">{{ $c->checkedBy?->first_name }} · {{ $c->finished_at->format('d/m H:i') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
</x-layouts.app>
