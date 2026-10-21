@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($open = $deal->isOpen())
<x-layouts.app :title="$deal->title">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('deals.index') }}">{{ mb_strtoupper(__('nav.items.sales')) }}</a> · {{ $deal->number }}</div>
            <h1>{{ $deal->title }}</h1>
            <p class="muted">{{ $deal->customerName() }} · {{ __('deals.modules.'.$deal->module) }} · {{ $eur($deal->value_cents) }}@if ($open) · {{ __('deals.weighted', ['amount' => $eur($deal->weightedCents()), 'pct' => $deal->probability()]) }}@endif</p>
        </div>
        <span @class(['pill', 'pill-lg', 'pill-ok' => $deal->stage === 'won', 'pill-danger' => $deal->stage === 'lost', 'pill-off' => $deal->stage === 'disqualified', 'pill-info' => $open])>{{ __('deals.stages.'.$deal->stage) }}</span>
    </div>
    @include('partials.flash')

    {{-- Pipeline: click a stage to move the deal there --}}
    @if ($open)
        <nav class="pipeline" aria-label="{{ __('deals.pipeline') }}">
            @foreach (\App\Models\Sales\Deal::OPEN as $i => $s)
                @php($reached = array_search($deal->stage, \App\Models\Sales\Deal::OPEN, true) >= $i)
                @if ($canWork && $s !== $deal->stage)
                    <form method="post" action="{{ route('deals.move', $deal) }}">@csrf<input type="hidden" name="stage" value="{{ $s }}"><button type="submit" @class(['pipe', 'pipe-done' => $reached])>{{ __('deals.stages.'.$s) }}</button></form>
                @else
                    <span @class(['pipe', 'pipe-done' => $reached, 'pipe-on' => $s === $deal->stage]) @if ($s === $deal->stage) aria-current="step" @endif>{{ __('deals.stages.'.$s) }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="split">
        <div class="stack">
            @if (! $open)
                <section class="card">
                    <h2>{{ __('deals.result') }}</h2>
                    <dl class="facts">
                        <dt>{{ __('deals.result') }}</dt><dd>{{ __('deals.stages.'.$deal->stage) }}@if ($deal->lost_reason) · {{ __('deals.lost_reasons.'.$deal->lost_reason) }}@endif @if ($deal->competitor) · {{ $deal->competitor }}@endif</dd>
                        <dt>{{ __('deals.closed_on') }}</dt><dd>{{ $deal->closed_at?->format('d/m/Y H:i') }} · {{ $deal->closedBy?->name }}</dd>
                        @if ($deal->close_note)<dt>{{ __('deals.note') }}</dt><dd>{{ $deal->close_note }}</dd>@endif
                    </dl>
                    @if ($canWork)
                        <form method="post" action="{{ route('deals.reopen', $deal) }}" class="form-actions">@csrf<button class="btn" type="submit">{{ __('deals.reopen') }}</button></form>
                    @endif
                </section>
            @endif

            <section class="card">
                <h2>{{ __('deals.details') }}</h2>
                @if ($canWork)
                    <form method="post" action="{{ route('deals.update', $deal) }}">
                        @csrf @method('PUT')
                        @include('sales.deals._fields')
                        <div class="form-actions"><button class="primary" type="submit">{{ __('deals.save') }}</button></div>
                    </form>
                @else
                    <dl class="facts">
                        <dt>{{ __('deals.fields.source') }}</dt><dd>{{ $deal->source ? __('deals.sources.'.$deal->source) : '—' }}</dd>
                        <dt>{{ __('deals.fields.owner_id') }}</dt><dd>{{ $deal->owner?->name ?? '—' }}</dd>
                        <dt>{{ __('deals.fields.event_start') }}</dt><dd>{{ $deal->event_start?->format('d/m/Y') ?? '—' }}@if ($deal->event_end && ! $deal->event_end->equalTo($deal->event_start)) – {{ $deal->event_end->format('d/m/Y') }}@endif</dd>
                        <dt>{{ __('deals.fields.location') }}</dt><dd>{{ $deal->location ?: '—' }}</dd>
                    </dl>
                @endif
            </section>

            <section class="card">
                <div class="card-head"><h2>{{ __('quotes.title') }}</h2>
                    @if ($canWork && $open)<form method="post" action="{{ route('quotes.store', $deal) }}">@csrf<button class="btn" type="submit">{{ __('quotes.new') }}</button></form>@endif
                </div>
                @php($quotes = $deal->quotes->where('status', '!=', 'replaced'))
                @forelse ($quotes as $q)
                    <div class="p-line"><a href="{{ route('quotes.show', $q) }}"><b>{{ $q->code() }}</b> · {{ $q->title }}</a><span>@include('sales.quotes._status', ['quote' => $q]) {{ \App\Services\Afrit6\Pricing::eur($q->net_cents) }}</span></div>
                @empty
                    <p class="muted">{{ __('quotes.none_for_deal') }}</p>
                @endforelse
            </section>

            <section class="card">
                <h2>{{ __('deals.activity') }}</h2>
                @if (auth()->user()->canUseModule('sales', 2))
                    <form method="post" action="{{ route('deals.note', $deal) }}" class="note-form">
                        @csrf
                        <select name="kind" aria-label="{{ __('deals.kind') }}">@foreach (\App\Models\Sales\DealActivity::KINDS as $k)<option value="{{ $k }}">{{ __('deals.kinds.'.$k) }}</option>@endforeach</select>
                        <textarea name="body" rows="2" required maxlength="5000" placeholder="{{ __('deals.note_hint') }}" aria-label="{{ __('deals.note') }}"></textarea>
                        <button class="btn" type="submit">{{ __('deals.add_note') }}</button>
                    </form>
                @endif
                <ol class="activity">
                    @forelse ($deal->activities as $a)
                        <li @class(['act', 'act-'.$a->kind])>
                            <x-avatar :who="$a->user" size="sm" aria-hidden="true"/>
                            <div>
                                <small class="muted">{{ __('deals.kinds.'.$a->kind) }} · {{ $a->user?->name ?? __('deals.system') }} · {{ $a->created_at?->format('d/m/Y H:i') }}</small>
                                <p>{!! nl2br(e($a->body)) !!}</p>
                            </div>
                        </li>
                    @empty
                        <li class="muted">{{ __('deals.no_activity') }}</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <aside class="stack">
            @if ($open)
                <section class="card">
                    <h2>{{ __('deals.qualify') }}</h2>
                    <p class="muted small">{{ __('deals.qualify_hint') }}</p>
                    <form method="post" action="{{ route('deals.checks', $deal) }}" class="checks">
                        @csrf
                        @foreach (['check_date', 'check_budget', 'check_decider'] as $c)
                            <label class="check"><input type="checkbox" name="{{ $c }}" value="1" @checked($deal->$c) @disabled(! $canWork) @if ($canWork) onchange="this.form.submit()" @endif> {{ __('deals.checks.'.$c) }}</label>
                        @endforeach
                        <noscript>@if ($canWork)<button class="btn" type="submit">{{ __('deals.save') }}</button>@endif</noscript>
                    </form>
                </section>

                @if ($canWork)
                    <section class="card">
                        <h2>{{ __('deals.close') }}</h2>
                        <form method="post" action="{{ route('deals.win', $deal) }}" class="stack-sm">
                            @csrf
                            <input name="note" maxlength="500" placeholder="{{ __('deals.note_optional') }}" aria-label="{{ __('deals.note') }}">
                            <button class="primary" type="submit">{{ __('deals.win') }}</button>
                        </form>
                        <details class="mt" @if ($errors->has('reason') || $errors->has('competitor')) open @endif>
                            <summary>{{ __('deals.lose') }}</summary>
                            <form method="post" action="{{ route('deals.lose', $deal) }}" class="stack-sm mt">
                                @csrf
                                <label class="field"><span>{{ __('deals.lost_reason') }}</span>
                                    <select name="reason" required><option value="">—</option>@foreach (\App\Models\Sales\Deal::LOST_REASONS as $r)<option value="{{ $r }}" @selected(old('reason') === $r)>{{ __('deals.lost_reasons.'.$r) }}</option>@endforeach</select>
                                </label>
                                <label class="field"><span>{{ __('deals.competitor') }}</span><input name="competitor" maxlength="120" value="{{ old('competitor') }}"><small>{{ __('deals.competitor_hint') }}</small></label>
                                <input name="note" maxlength="500" placeholder="{{ __('deals.note_optional') }}" aria-label="{{ __('deals.note') }}">
                                <button class="btn btn-danger" type="submit">{{ __('deals.mark_lost') }}</button>
                            </form>
                        </details>
                        @if (in_array($deal->stage, ['new', 'qualified'], true))
                            <details class="mt">
                                <summary>{{ __('deals.disqualify') }}</summary>
                                <p class="muted small">{{ __('deals.disqualify_hint') }}</p>
                                <form method="post" action="{{ route('deals.disqualify', $deal) }}" class="stack-sm">
                                    @csrf
                                    <input name="note" maxlength="500" placeholder="{{ __('deals.note_optional') }}" aria-label="{{ __('deals.note') }}">
                                    <button class="btn" type="submit">{{ __('deals.disqualify') }}</button>
                                </form>
                            </details>
                        @endif
                    </section>
                @endif
            @endif

            <section class="card">
                <h2>{{ __('deals.customer') }}</h2>
                @if ($deal->company)
                    <p><a href="{{ route('companies.show', $deal->company) }}"><b>{{ $deal->company->name }}</b></a></p>
                @else
                    <p class="muted">{{ __('deals.private') }}</p>
                @endif
                @if ($deal->contact)
                    <p>{{ $deal->contact->name }}@if ($deal->contact->email)<br><a href="mailto:{{ $deal->contact->email }}">{{ $deal->contact->email }}</a>@endif @if ($deal->contact->phone)<br><a href="tel:{{ $deal->contact->phone }}">{{ $deal->contact->phone }}</a>@endif</p>
                @endif
                @if ($canWork)
                    <details>
                        <summary>{{ __('deals.add_contact') }}</summary>
                        <form method="post" action="{{ route('deals.contact', $deal) }}" class="stack-sm mt">
                            @csrf
                            <input name="first_name" required maxlength="80" placeholder="{{ __('deals.first_name') }}" aria-label="{{ __('deals.first_name') }}">
                            <input name="last_name" maxlength="80" placeholder="{{ __('deals.last_name') }}" aria-label="{{ __('deals.last_name') }}">
                            <input type="email" name="email" maxlength="190" placeholder="{{ __('deals.email') }}" aria-label="{{ __('deals.email') }}">
                            <input name="phone" maxlength="40" placeholder="{{ __('deals.phone') }}" aria-label="{{ __('deals.phone') }}">
                            <button class="btn" type="submit">{{ __('deals.add_contact') }}</button>
                        </form>
                    </details>
                @endif
                @if ($deal->incoming)
                    <p class="muted small mt">{{ __('deals.from_incoming', ['channel' => __('deals.channels.'.$deal->incoming->channel), 'date' => $deal->incoming->received_at?->format('d/m/Y')]) }}</p>
                @endif
                @if ($openDeals->isNotEmpty())
                    <h3>{{ __('deals.other_open') }}</h3>
                    <ul class="plain">@foreach ($openDeals as $o)<li><a href="{{ route('deals.show', $o) }}">{{ $o->number }} · {{ $o->title }}</a></li>@endforeach</ul>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
