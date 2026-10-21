@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($fmt = fn ($d, $f = 'l j F Y') => $d->locale(app()->getLocale())->translatedFormat($f))
@php($open = in_array($b->status, ['request', 'option'], true))
<x-layouts.app :title="$b->number">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('factory.index', ['m' => $b->start_date->format('Y-m')]) }}">CLUK FACTORY</a> · {{ $b->number }}</div>
            <h1>{{ $b->title }}</h1>
            <p class="muted">{{ $b->customerName() }} · {{ __('factory.types.'.$b->event_type) }} · {{ trans_choice('factory.guests_n', $b->guests, ['n' => $b->guests]) }} · {{ __('factory.sources.'.$b->source) }}</p>
        </div>
        @include('factory._status')
    </div>
    @include('partials.flash')
    @if ($clashes && $b->status !== 'confirmed')<div class="flash flash-err" role="alert">{{ __('factory.clash_on', ['days' => collect($clashes)->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m'))->implode(', ')]) }}</div>@endif

    <div class="split">
        <div class="stack">
            <section class="card">
                <h2>{{ __('factory.timing') }}</h2>
                <dl class="facts">
                    @if ($b->buildup_days)<dt>{{ __('factory.parts.buildup') }}</dt><dd>{{ $fmt($b->firstDay()) }}@if ($b->buildup_days > 1) – {{ $fmt($b->start_date->copy()->subDay()) }}@endif</dd>@endif
                    <dt>{{ __('factory.parts.event') }}</dt><dd><b>{{ $fmt($b->start_date) }}@if ($b->days > 1) – {{ $fmt($b->lastEventDay()) }}@endif</b></dd>
                    <dt>{{ __('factory.parts.breakdown') }}</dt><dd>{{ $b->breakdown_days ? $fmt($b->lastDay()) : __('factory.same_night') }}</dd>
                    <dt>{{ __('factory.space') }}</dt><dd>{{ $b->size }} · {{ __('factory.book.floor', ['floor' => config('factory.sizes.'.$b->size.'.0'), 'ent' => config('factory.sizes.'.$b->size.'.1')]) }}</dd>
                    <dt>{{ __('factory.level') }}</dt><dd>{{ __('factory.levels.'.$b->level) }}</dd>
                    <dt>{{ __('factory.extras_title') }}</dt><dd>{{ collect($b->extras ?? [])->map(fn ($x) => __('factory.extras.'.$x))->implode(', ') ?: '—' }}</dd>
                    <dt>{{ __('factory.estimate') }}</dt><dd>{{ $b->estimateCents() !== null ? __('factory.from', ['amount' => $eur($b->estimateCents())]) : __('factory.custom') }}</dd>
                </dl>
                @if ($b->note)<blockquote>{!! nl2br(e($b->note)) !!}</blockquote>@endif
                @if ($canWork && ! in_array($b->status, ['declined', 'cancelled', 'done'], true))<a class="btn" href="{{ route('factory.edit', $b) }}">{{ __('factory.edit') }}</a>@endif
            </section>

            @if (in_array($b->status, ['option', 'confirmed', 'done'], true))
                <section class="card">
                    <h2>{{ __('factory.before') }}</h2>
                    <form method="post" action="{{ route('factory.checks', $b) }}" class="checks one-col">
                        @csrf
                        @foreach (\App\Models\Factory\Booking::CHECKS as $c)
                            <label class="check"><input type="checkbox" name="check_{{ $c }}" value="1" @checked($b->checks[$c] ?? false) @disabled(! $canWork) @if ($canWork) onchange="this.form.submit()" @endif><span>{{ __('factory.checks.'.$c) }}<br><small>{{ __('factory.checks_hint.'.$c, ['guests' => $b->guests]) }}</small></span></label>
                        @endforeach
                    </form>
                </section>
            @endif
        </div>

        <aside class="stack">
            <section class="card">
                <h2>{{ __('factory.customer') }}</h2>
                @if ($b->company)<p><a href="{{ route('companies.show', $b->company) }}"><b>{{ $b->company->name }}</b></a> <span class="pill pill-ok">{{ __('factory.known') }}</span></p>@elseif ($b->req_company)<p><b>{{ $b->req_company }}</b>@if ($b->req_vat) · {{ $b->req_vat }}@endif</p>@endif
                <p>{{ $b->contact?->name ?? $b->req_name }}@if ($b->req_email ?: $b->contact?->email)<br><a href="mailto:{{ $b->req_email ?: $b->contact?->email }}">{{ $b->req_email ?: $b->contact?->email }}</a>@endif @if ($b->req_phone)<br><a href="tel:{{ $b->req_phone }}">{{ $b->req_phone }}</a>@endif</p>
                @if ($b->deal)<p><a href="{{ route('deals.show', $b->deal) }}">{{ $b->deal->number }} · {{ __('deals.stages.'.$b->deal->stage) }}</a></p>@endif
                @if ($b->quote)<p><a href="{{ route('quotes.show', $b->quote) }}">{{ $b->quote->code() }}</a> @include('sales.quotes._status', ['quote' => $b->quote])</p>@endif
            </section>

            @if ($canWork && $open)
                <section class="card">
                    <h2>{{ __('factory.next') }}</h2>
                    @if (! $b->quote_id && auth()->user()->canUseModule('sales', 2))
                        <form method="post" action="{{ route('factory.quote', $b) }}">@csrf<button class="primary" type="submit">{{ __('factory.make_quote') }}</button></form>
                        <small class="muted">{{ __('factory.make_quote_hint') }}</small>
                    @endif
                    @if ($b->status === 'request' || $b->isOptionExpired())
                        <form method="post" action="{{ route('factory.hold', $b) }}" class="mt">@csrf<button class="btn" type="submit" @disabled($clashes)>{{ __('factory.hold', ['n' => config('factory.option_days')]) }}</button></form>
                    @elseif ($b->status === 'option')
                        <form method="post" action="{{ route('factory.hold', $b) }}" class="mt">@csrf<button class="btn" type="submit">{{ __('factory.extend') }}</button></form>
                    @endif
                    @if (auth()->user()->canUseModule('factory', 3))
                        <form method="post" action="{{ route('factory.confirm', $b) }}" class="mt">@csrf<button class="btn" type="submit" @disabled($clashes)>{{ __('factory.confirm_now') }}</button></form>
                    @endif
                    <details class="mt">
                        <summary>{{ __('factory.decline') }}</summary>
                        <form method="post" action="{{ route('factory.decline', $b) }}" class="stack-sm mt">@csrf<input name="reason" required maxlength="500" placeholder="{{ __('factory.decline_hint') }}" aria-label="{{ __('factory.decline') }}"><button class="btn btn-danger" type="submit">{{ __('factory.decline_go') }}</button></form>
                    </details>
                </section>
            @elseif ($canWork && $b->status === 'confirmed')
                <section class="card">
                    <form method="post" action="{{ route('factory.status', $b) }}" class="inline-form">@csrf<input type="hidden" name="to" value="done"><button class="btn" type="submit">{{ __('factory.mark_done') }}</button></form>
                    <details class="mt"><summary>{{ __('factory.cancel') }}</summary><form method="post" action="{{ route('factory.status', $b) }}" class="mt">@csrf<input type="hidden" name="to" value="cancelled"><button class="btn btn-danger" type="submit">{{ __('factory.cancel_go') }}</button></form></details>
                </section>
            @endif
            @if ($b->decline_reason)<section class="card"><h2>{{ __('factory.statuses.declined') }}</h2><p>{{ $b->decline_reason }}</p></section>@endif
        </aside>
    </div>
</x-layouts.app>
