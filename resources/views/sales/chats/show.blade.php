<x-layouts.app :title="__('chats.title').' · '.($chat->name ?: __('chats.visitor'))">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('chats.index') }}">{{ mb_strtoupper(__('chats.title')) }}</a></div>
            <h1>{{ $chat->name ?: ($chat->email ?: __('chats.visitor')) }}</h1>
            <p class="muted"><span @class(['pill', 'pill-warn' => $chat->status === 'waiting', 'pill-info' => $chat->status === 'staff', 'pill-off' => in_array($chat->status, ['closed', 'bot'], true)]) data-status>{{ __('chats.statuses.'.$chat->status) }}</span> · {{ $teamOpen ? __('chats.office_open') : __('chats.office_closed') }}</p>
        </div>
        @if ($canWork && $chat->status !== 'closed')
            <form method="post" action="{{ route('chats.close', $chat) }}" onsubmit="return confirm(@js(__('chats.close_confirm')))">@csrf<button class="btn" type="submit">{{ __('chats.close') }}</button></form>
        @endif
    </div>
    @include('partials.flash')
    <div class="split">
        <section class="card">
            <div class="tc-log" id="tc-log" data-poll="{{ route('chats.poll', $chat) }}" data-last="{{ $chat->messages->max('id') ?? 0 }}">
                @foreach ($chat->messages as $m)@include('sales.chats._line', ['m' => $m])@endforeach
            </div>
            @if ($canWork && $chat->status !== 'closed')
                <form method="post" action="{{ route('chats.reply', $chat) }}" class="tc-reply" id="tc-reply">@csrf
                    <textarea name="text" rows="3" maxlength="4000" required placeholder="{{ __('chats.reply_ph', ['lang' => config('cluk.locales.'.$chat->locale, $chat->locale)]) }}" aria-label="{{ __('chats.reply') }}"></textarea>
                    <div class="inline-form"><button class="primary" type="submit">{{ __('chats.send') }}</button><small class="muted">{{ $chat->email ? __('chats.mail_hint', ['email' => $chat->email, 'n' => \App\Services\Website\TeamChats::LEFT_MINUTES]) : __('chats.no_email') }}</small></div>
                </form>
            @elseif ($chat->status === 'closed')
                <p class="muted">{{ __('chats.is_closed') }}</p>
            @endif
        </section>
        <aside class="stack">
            <section class="card">
                <h2>{{ __('chats.visitor_title') }}</h2>
                <dl class="facts">
                    <dt>{{ __('chats.f.name') }}</dt><dd>{{ $chat->name ?: '—' }}</dd>
                    <dt>{{ __('chats.f.email') }}</dt><dd>@if ($chat->email)<a href="mailto:{{ $chat->email }}">{{ $chat->email }}</a>@else—@endif</dd>
                    <dt>{{ __('chats.f.phone') }}</dt><dd>@if ($chat->phone)@include('partials.phone', ['number' => $chat->phone, 'country' => 'BE'])@else—@endif</dd>
                    <dt>{{ __('chats.f.language') }}</dt><dd>{{ config('cluk.locales.'.$chat->locale, $chat->locale) }}</dd>
                    <dt>{{ __('chats.f.page') }}</dt><dd>{{ $chat->page ?: '—' }}</dd>
                    <dt>{{ __('chats.f.started') }}</dt><dd>{{ $chat->created_at?->timezone('Europe/Brussels')->format('d/m/Y H:i') }}</dd>
                    @if ($chat->customer_account_id)<dt>{{ __('chats.f.login') }}</dt><dd><a href="{{ route('web-accounts.index', ['s' => 'active']) }}">{{ __('chats.f.has_login') }}</a></dd>@endif
                    @if ($chat->incoming)<dt>{{ __('chats.f.incoming') }}</dt><dd><a href="{{ route('incoming.index', ['open' => $chat->incoming->id]) }}">{{ \Illuminate\Support\Str::limit($chat->incoming->subject, 60) }}</a></dd>@endif
                </dl>
            </section>
            <section class="card">
                <h2>{{ __('chats.company_title') }}</h2>
                @if ($chat->company)
                    <p><a href="{{ route('companies.show', $chat->company) }}"><b>{{ $chat->company->name }}</b></a>@if ($chat->contact) · {{ $chat->contact->name ?? trim($chat->contact->first_name.' '.$chat->contact->last_name) }}@endif</p>
                @else
                    <p class="muted">{{ $candidates->isEmpty() ? __('chats.no_match') : trans_choice('chats.matches', $candidates->count(), ['n' => $candidates->count()]) }}</p>
                @endif
                @if ($canWork)
                    <form method="post" action="{{ route('chats.link', $chat) }}" class="stack-sm">@csrf
                        <label class="field"><span>{{ __('chats.link_to') }}</span>
                            <select name="company_id" data-pick>
                                <option value="">{{ __('chats.no_company') }}</option>
                                @if ($candidates->isNotEmpty())<optgroup label="{{ __('chats.same_email') }}">@foreach ($candidates as $c)<option value="{{ $c->id }}" @selected($chat->company_id === $c->id)>{{ $c->name }}@if ($c->city) · {{ $c->city }}@endif @if ($c->vat_number) · {{ $c->vat_number }}@endif</option>@endforeach</optgroup>@endif
                                <optgroup label="{{ __('chats.all_companies') }}">@foreach ($companies as $c)@unless ($candidates->contains('id', $c->id))<option value="{{ $c->id }}" @selected($chat->company_id === $c->id)>{{ $c->name }}</option>@endunless @endforeach</optgroup>
                            </select>
                        </label>
                        <div><button class="btn" type="submit">{{ __('chats.link_go') }}</button></div>
                        <small class="muted">{{ __('chats.link_hint') }}</small>
                    </form>
                @endif
            </section>
            @include('web._activity', ['act' => $act])
        </aside>
    </div>
    <style>
        .tc-log{display:grid;gap:10px;max-height:62vh;overflow:auto;padding:4px}
        .tc-msg{max-width:82%;padding:8px 12px;border-radius:12px;background:var(--surface-subtle);border:1px solid var(--line)}
        .tc-visitor{justify-self:start}
        .tc-clukie{justify-self:start;background:var(--brand-tint)}
        .tc-staff{justify-self:end;background:var(--info-bg)}
        .tc-who{display:block;color:var(--muted);font-size:12px;margin-bottom:2px}
        .tc-b{line-height:1.5;overflow-wrap:anywhere}
        .tc-note{justify-self:center;font-size:12px;color:var(--muted)}
        .tc-cards{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
        .tc-card{display:grid;gap:2px;width:150px;font-size:12px;text-decoration:none;color:inherit;border:1px solid var(--line);border-radius:8px;padding:6px;background:var(--surface)}
        .tc-card img{width:100%;height:80px;object-fit:contain}
        .tc-reply{display:grid;gap:8px;margin-top:12px}
        .tc-reply textarea{width:100%}
    </style>
    <script>
    (function () {
        var log = document.getElementById('tc-log'), form = document.getElementById('tc-reply');
        if (!log) return;
        log.scrollTop = log.scrollHeight;
        function poll() {
            fetch(log.dataset.poll + '?after=' + log.dataset.last, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j.html) { log.insertAdjacentHTML('beforeend', j.html); log.scrollTop = log.scrollHeight; }
                    log.dataset.last = j.last;
                }).catch(function () {}).finally(function () { setTimeout(poll, 5000); });
        }
        setTimeout(poll, 5000);
        if (form) {
            var ta = form.querySelector('textarea');
            ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); } });
        }
    })();
    </script>
</x-layouts.app>
