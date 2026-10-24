@php($url = fn (array $q = []) => route('salestasks.index', array_filter(array_merge(['who' => $who, 'kind' => $kind, 'level' => $level], $q), fn ($v) => $v !== null && $v !== '')))
@php($eur = fn ($c) => '€ '.number_format($c / 100, 0, ',', '.'))
<x-layouts.app :title="__('salestasks.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.commercial')) }}</div>
            <h1>{{ __('salestasks.title') }}</h1>
            <p class="muted">{{ __('salestasks.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    <nav class="tabs">
        @foreach (['me', 'all', 'nobody'] as $w)<a @class(['tab', 'tab-on' => $who === $w]) href="{{ $url(['who' => $w]) }}">{{ __('salestasks.who.'.$w) }} <small class="muted">{{ $counts[$w] }}</small></a>@endforeach
    </nav>
    <div class="inline-form mt st-filters">
        <nav class="chips">
            <a @class(['chip', 'chip-on' => ! $level]) href="{{ $url(['level' => '']) }}">{{ __('salestasks.all_levels') }}</a>
            @foreach (\App\Models\Sales\SalesTask::LEVELS as $l)<a @class(['chip', 'st-chip-'.$l, 'chip-on' => $level === $l]) href="{{ $url(['level' => $l]) }}"><i class="st-dot st-{{ $l }}"></i>{{ __('salestasks.levels.'.$l) }} <small>{{ $levels[$l] ?? 0 }}</small></a>@endforeach
        </nav>
        <nav class="chips">
            <a @class(['chip', 'chip-on' => ! $kind]) href="{{ $url(['kind' => '']) }}">{{ __('salestasks.all_kinds') }}</a>
            @foreach (\App\Models\Sales\SalesTask::KINDS as $k)<a @class(['chip', 'chip-on' => $kind === $k]) href="{{ $url(['kind' => $k]) }}">{{ __('salestasks.kinds.'.$k) }} <small>{{ $kinds[$k] ?? 0 }}</small></a>@endforeach
        </nav>
    </div>

    <section class="card card-flush mt">
        @forelse ($tasks as $t)
            @php($d = $t->data ?? [])
            <div class="st-row st-{{ $t->level }}">
                <div class="st-main">
                    <small class="st-tag">{{ __('salestasks.levels.'.$t->level) }} · {{ __('salestasks.kinds.'.$t->kind) }}</small>
                    @if ($t->kind === 'card')
                        <b><a href="{{ $d['url'] ?? '#' }}">{{ $t->company?->name }}</a></b> <span class="muted">{{ __('salestasks.card.title') }}</span>
                        <div class="st-pills">@foreach ($d['problems'] ?? [] as $pr)<span @class(['pill', 'pill-warn' => in_array($pr, ['credit_red', 'vat_invalid'], true)])>{{ __('salestasks.problems.'.$pr) }}</span>@endforeach</div>
                    @elseif ($t->kind === 'quote')
                        <b><a href="{{ $d['url'] ?? '#' }}">{{ $d['number'] ?? '' }}</a></b> {{ $d['title'] ?? '' }} <span class="muted">· {{ $t->company?->name }} · {{ $eur($d['cents'] ?? 0) }}</span>
                        <div class="small">{{ trans_choice('salestasks.quote.open', $d['days'] ?? 0, ['n' => $d['days'] ?? 0]) }}@if (! empty($d['expired'])) · <b>{{ __('salestasks.quote.expired') }}</b>@endif @if ($t->contact) · {{ __('salestasks.call', ['name' => $t->contact->name ?? trim($t->contact->first_name.' '.$t->contact->last_name)]) }}@endif</div>
                    @else
                        <b><a href="{{ $d['url'] ?? '#' }}">{{ $t->company?->name }}</a></b> <span class="muted">{{ __('salestasks.web.title') }}</span>
                        <div class="small">
                            {{ trans_choice('salestasks.web.views', $d['views'] ?? 0, ['n' => $d['views'] ?? 0]) }}@if (! empty($d['products'])) · {{ trans_choice('salestasks.web.products', $d['products'], ['n' => $d['products']]) }}@endif
                            @if (! empty($d['basket'])) · <b>{{ __('salestasks.web.basket') }}</b>@endif
                            @if (! empty($d['searches'])) · {{ __('salestasks.web.searched', ['words' => implode(', ', $d['searches'])]) }}@endif
                            @if (! empty($d['social'])) · <b>{{ __('salestasks.web.via', ['where' => implode(', ', $d['social'])]) }}</b>@endif
                            @if (! empty($d['last'])) · {{ \Illuminate\Support\Carbon::parse($d['last'])->timezone('Europe/Brussels')->format('d/m H:i') }}@endif
                            @if ($t->contact) · {{ __('salestasks.call', ['name' => $t->contact->name ?? trim($t->contact->first_name.' '.$t->contact->last_name)]) }}@endif
                        </div>
                    @endif
                    <small class="muted">{{ $t->owner?->name ?? __('salestasks.no_owner') }} · {{ __('salestasks.since', ['when' => $t->created_at->timezone('Europe/Brussels')->format('d/m/Y')]) }}</small>
                </div>
                <div class="st-actions">
                    <form method="post" action="{{ route('salestasks.done', $t) }}">@csrf<button class="btn btn-sm" type="submit">✓ {{ __('salestasks.done') }}</button></form>
                    <form method="post" action="{{ route('salestasks.snooze', $t) }}" class="inline-form">@csrf
                        <select name="days" aria-label="{{ __('salestasks.snooze') }}" onchange="this.form.submit()"><option value="">{{ __('salestasks.snooze') }}…</option>@foreach ([1, 3, 7, 14] as $n)<option value="{{ $n }}">{{ trans_choice('salestasks.days', $n, ['n' => $n]) }}</option>@endforeach</select>
                    </form>
                </div>
            </div>
        @empty
            <p class="pad muted">{{ __('salestasks.empty') }}</p>
        @endforelse
    </section>
    <x-pager :items="$tasks"/>
    <p class="muted small mt">{{ __('salestasks.legend') }}</p>
</x-layouts.app>
