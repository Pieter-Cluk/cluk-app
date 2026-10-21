<x-layouts.app :title="__('deals.tabs.incoming')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.items.sales')) }}</div>
            <h1>{{ __('deals.tabs.incoming') }}</h1>
            <p class="muted">{{ __('deals.incoming.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @include('sales._tabs', ['section' => 'incoming', 'incomingCount' => $counts['new']])

    <div class="filters">
        <nav class="chips">
            <a href="{{ route('incoming.index', array_filter(['channel' => $channel])) }}" @class(['chip', 'chip-on' => $status === 'new'])>{{ __('deals.incoming.to_handle') }} ({{ $counts['new'] }})</a>
            <a href="{{ route('incoming.index', array_filter(['status' => 'handled', 'channel' => $channel])) }}" @class(['chip', 'chip-on' => $status === 'handled'])>{{ __('deals.incoming.handled') }} ({{ $counts['handled'] }})</a>
        </nav>
        <form method="get">
            @if ($status === 'handled')<input type="hidden" name="status" value="handled">@endif
            <select name="channel" onchange="this.form.submit()" aria-label="{{ __('deals.incoming.channel') }}">
                <option value="">{{ __('deals.incoming.all_channels') }}</option>
                @foreach (\App\Models\Sales\IncomingMessage::CHANNELS as $c)<option value="{{ $c }}" @selected($channel === $c)>{{ __('deals.channels.'.$c) }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="split">
        <section class="card card-flush">
            <ul class="inbox">
                @forelse ($items as $it)
                    <li>
                        <a href="{{ route('incoming.index', array_filter(['open' => $it->id, 'status' => $status === 'handled' ? 'handled' : null, 'channel' => $channel])) }}" @class(['inbox-item', 'is-on' => $selected?->id === $it->id])>
                            <span class="pill">{{ __('deals.channels.'.$it->channel) }}</span>
                            <span class="inbox-main">
                                <b>{{ $it->subject }}</b>
                                <small class="muted">{{ $it->from_name ?: $it->from_email ?: '—' }}@if ($it->company_name || $it->company) · {{ $it->company?->name ?? $it->company_name }}@endif</small>
                            </span>
                            <span class="inbox-side">
                                <small class="muted nowrap">{{ $it->received_at?->locale(app()->getLocale())->diffForHumans(short: true) }}</small>
                                @if ($it->status !== 'new')<span @class(['pill', 'pill-warn' => $it->status === 'asked', 'pill-ok' => in_array($it->status, ['converted', 'linked']), 'pill-off' => $it->status === 'dismissed'])>{{ __('deals.incoming.statuses.'.$it->status) }}</span>@endif
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="muted inbox-empty">{{ $status === 'new' ? __('deals.incoming.empty') : __('deals.incoming.empty_handled') }}</li>
                @endforelse
            </ul>
            <x-pager :items="$items"/>
        </section>

        <aside class="stack">
            @if ($selected)
                <section class="card">
                    <div class="card-head"><h2>{{ $selected->subject }}</h2><span class="pill">{{ __('deals.channels.'.$selected->channel) }}</span></div>
                    <dl class="facts">
                        <dt>{{ __('deals.incoming.from') }}</dt><dd>{{ $selected->from_name ?: '—' }}</dd>
                        @if ($selected->from_email)<dt>{{ __('deals.email') }}</dt><dd><a href="mailto:{{ $selected->from_email }}">{{ $selected->from_email }}</a></dd>@endif
                        @if ($selected->from_phone)<dt>{{ __('deals.phone') }}</dt><dd><a href="tel:{{ $selected->from_phone }}">{{ $selected->from_phone }}</a></dd>@endif
                        <dt>{{ __('deals.incoming.company') }}</dt><dd>@if ($selected->company)<a href="{{ route('companies.show', $selected->company) }}">{{ $selected->company->name }}</a> <span class="pill pill-ok">{{ __('deals.incoming.known') }}</span>@else{{ $selected->company_name ?: '—' }}@endif</dd>
                        @if ($selected->module)<dt>{{ __('deals.fields.module') }}</dt><dd>{{ __('deals.modules.'.$selected->module) }}</dd>@endif
                        <dt>{{ __('deals.incoming.received') }}</dt><dd>{{ $selected->received_at?->format('d/m/Y H:i') }}</dd>
                    </dl>
                    @if ($selected->body)<div class="message-body">{!! nl2br(e($selected->body)) !!}</div>@endif
                    @if ($selected->handled_note)<p class="muted small mt">{{ __('deals.incoming.statuses.'.$selected->status) }}: {{ $selected->handled_note }}</p>@endif
                    @if ($selected->deal_id)<p class="mt"><a href="{{ route('deals.show', $selected->deal_id) }}">{{ __('deals.incoming.go_deal') }}</a></p>@endif
                </section>

                @if ($canWork && in_array($selected->status, ['new', 'asked'], true))
                    <section class="card">
                        <h2>{{ __('deals.incoming.make_deal') }}</h2>
                        <form method="post" action="{{ route('incoming.convert', $selected) }}" class="stack-sm">
                            @csrf
                            <label class="field"><span>{{ __('deals.fields.title') }}</span><input name="title" required maxlength="200" value="{{ old('title', $selected->subject) }}"></label>
                            <label class="field"><span>{{ __('deals.fields.module') }}</span><select name="module">@foreach (\App\Models\Sales\Deal::MODULES as $m)<option value="{{ $m }}" @selected(old('module', $selected->module ?? 'dryhire') === $m)>{{ __('deals.modules.'.$m) }}</option>@endforeach</select></label>
                            <label class="field"><span>{{ __('deals.incoming.company') }}</span>
                                <select name="company">
                                    @if ($selected->company)<option value="{{ $selected->company_id }}" selected>{{ $selected->company->name }}</option>@endif
                                    <option value="new" @selected(! $selected->company && $selected->company_name)>{{ __('deals.incoming.new_company') }}</option>
                                    <option value="private" @selected(! $selected->company && ! $selected->company_name)>{{ __('deals.private') }}</option>
                                    <optgroup label="{{ __('deals.incoming.existing') }}">@foreach ($companies as $c)@if ($c->id !== $selected->company_id)<option value="{{ $c->id }}">{{ $c->name }}</option>@endif @endforeach</optgroup>
                                </select>
                            </label>
                            <label class="field"><span>{{ __('deals.incoming.new_company_name') }}</span><input name="new_company" maxlength="160" value="{{ old('new_company', $selected->company_name) }}"><small>{{ __('deals.incoming.new_company_hint') }}</small></label>
                            <label class="field"><span>{{ __('deals.fields.owner_id') }}</span><select name="owner_id">@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($o->id === auth()->id())>{{ $o->name }}</option>@endforeach</select></label>
                            <label class="field"><span>{{ __('deals.fields.value') }}</span><input type="number" step="0.01" min="0" name="value" value="{{ old('value') }}"></label>
                            <button class="primary" type="submit">{{ __('deals.incoming.convert') }}</button>
                        </form>
                    </section>

                    <section class="card">
                        @if ($openDeals->isNotEmpty())
                            <details>
                                <summary>{{ __('deals.incoming.link') }}</summary>
                                <form method="post" action="{{ route('incoming.link', $selected) }}" class="stack-sm mt">
                                    @csrf
                                    <select name="deal_id" required aria-label="{{ __('deals.incoming.link') }}">
                                        @foreach ($openDeals->sortByDesc(fn ($d) => $d->company_id && $d->company_id === $selected->company_id) as $d)<option value="{{ $d->id }}">{{ $d->number }} · {{ $d->title }}@if ($d->company) ({{ $d->company->name }})@endif</option>@endforeach
                                    </select>
                                    <button class="btn" type="submit">{{ __('deals.incoming.link_go') }}</button>
                                </form>
                            </details>
                        @endif
                        <details class="mt">
                            <summary>{{ __('deals.incoming.ask') }}</summary>
                            <form method="post" action="{{ route('incoming.ask', $selected) }}" class="stack-sm mt">
                                @csrf
                                <textarea name="note" rows="2" required maxlength="500" placeholder="{{ __('deals.incoming.ask_hint') }}" aria-label="{{ __('deals.incoming.ask') }}"></textarea>
                                <button class="btn" type="submit">{{ __('deals.incoming.ask_go') }}</button>
                            </form>
                        </details>
                        <details class="mt">
                            <summary>{{ __('deals.incoming.dismiss') }}</summary>
                            <form method="post" action="{{ route('incoming.dismiss', $selected) }}" class="stack-sm mt">
                                @csrf
                                <input name="note" maxlength="500" placeholder="{{ __('deals.incoming.dismiss_hint') }}" aria-label="{{ __('deals.note') }}">
                                <button class="btn btn-danger" type="submit">{{ __('deals.incoming.dismiss') }}</button>
                            </form>
                        </details>
                    </section>
                @endif
            @endif

            @if ($canWork)
                <section class="card">
                    <details @if (! $selected || $errors->has('subject')) open @endif>
                        <summary><b>{{ __('deals.incoming.log') }}</b></summary>
                        <p class="muted small">{{ __('deals.incoming.log_hint') }}</p>
                        <form method="post" action="{{ route('incoming.store') }}" class="stack-sm">
                            @csrf
                            <select name="channel" aria-label="{{ __('deals.incoming.channel') }}">@foreach (\App\Models\Sales\IncomingMessage::CHANNELS as $c)<option value="{{ $c }}" @selected(old('channel', 'phone') === $c)>{{ __('deals.channels.'.$c) }}</option>@endforeach</select>
                            <input name="subject" required maxlength="200" value="{{ old('subject') }}" placeholder="{{ __('deals.incoming.subject') }}" aria-label="{{ __('deals.incoming.subject') }}">
                            <input name="from_name" maxlength="160" value="{{ old('from_name') }}" placeholder="{{ __('deals.incoming.from') }}" aria-label="{{ __('deals.incoming.from') }}">
                            <input name="company_name" maxlength="160" value="{{ old('company_name') }}" placeholder="{{ __('deals.incoming.company') }}" aria-label="{{ __('deals.incoming.company') }}">
                            <input type="email" name="from_email" maxlength="190" value="{{ old('from_email') }}" placeholder="{{ __('deals.email') }}" aria-label="{{ __('deals.email') }}">
                            <input name="from_phone" maxlength="40" value="{{ old('from_phone') }}" placeholder="{{ __('deals.phone') }}" aria-label="{{ __('deals.phone') }}">
                            <select name="module" aria-label="{{ __('deals.fields.module') }}"><option value="">{{ __('deals.incoming.module_unknown') }}</option>@foreach (\App\Models\Sales\Deal::MODULES as $m)<option value="{{ $m }}">{{ __('deals.modules.'.$m) }}</option>@endforeach</select>
                            <textarea name="body" rows="4" maxlength="10000" placeholder="{{ __('deals.incoming.body') }}" aria-label="{{ __('deals.incoming.body') }}">{{ old('body') }}</textarea>
                            <button class="btn" type="submit">{{ __('deals.incoming.add') }}</button>
                        </form>
                    </details>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
