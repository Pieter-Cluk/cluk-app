<x-layouts.app :title="__('web.staff.title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('companies.index') }}">{{ mb_strtoupper(__('nav.items.companies')) }}</a></div>
            <h1>{{ __('web.staff.title') }}</h1>
            <p class="muted">{{ __('web.staff.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif
    <nav class="tabs">
        @foreach (\App\Http\Controllers\WebAccountController::TABS as $t)<a @class(['tab', 'tab-on' => $tab === $t]) href="{{ route('web-accounts.index', ['s' => $t]) }}">{{ __('web.statuses.'.$t) }} <small class="muted">{{ $counts[$t] }}</small></a>@endforeach
    </nav>

    @php($readyIds = $suggest->filter(fn ($s) => $s['ready'])->keys())
    @if ($canEdit && $tab === 'pending' && $readyIds->isNotEmpty())
        <section class="card wa-ready mt">
            <div><b>{{ trans_choice('web.staff.ready_n', $readyIds->count(), ['n' => $readyIds->count()]) }}</b><br><small class="muted">{{ __('web.staff.ready_hint') }}</small></div>
            <form method="post" action="{{ route('web-accounts.approve-ready') }}" onsubmit="return confirm(@js(trans_choice('web.staff.ready_confirm', $readyIds->count(), ['n' => $readyIds->count()])))">@csrf
                @foreach ($readyIds as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach
                <button class="primary" type="submit">{{ trans_choice('web.staff.ready_go', $readyIds->count(), ['n' => $readyIds->count()]) }}</button>
            </form>
        </section>
    @endif

    <div class="stack mt">
        @forelse ($accounts as $a)
            <section class="card">
                <div class="wa-head">
                    <div>
                        <b>{{ $a->name() }}</b> · <a href="mailto:{{ $a->email }}">{{ $a->email }}</a>
                        @if ($a->email_verified_at)<span class="pill pill-ok">{{ __('web.staff.email_ok') }}</span>@else<span class="pill pill-warn">{{ __('web.staff.email_not_ok') }}</span>@endif
                        <br><small class="muted">{{ collect([$a->company_name, $a->vat_number, $a->phone, strtoupper($a->locale)])->filter()->implode(' · ') }} · {{ __('web.staff.registered', ['date' => $a->created_at->format('d/m/Y H:i')]) }}</small>
                        @if ($a->company || $a->contact)<br><small>{{ __('web.staff.linked') }}: @if ($a->company)<a href="{{ route('companies.show', $a->company) }}">{{ $a->company->name }}</a> · @endif{{ $a->contact?->first_name }} {{ $a->contact?->last_name }}@if ($a->company) · <b>{{ __('site.account.roles.'.$a->role) }}</b>@endif @if ($a->invited_by_id) · {{ __('web.staff.invited') }}@endif</small>@endif
                        @if ($a->last_login_at)<br><small class="muted">{{ __('web.staff.last_login', ['date' => $a->last_login_at->format('d/m/Y H:i')]) }}</small>@endif
                        @if ($a->blocked_reason)<br><small class="muted">{{ __('web.staff.reason') }}: {{ $a->blocked_reason }}</small>@endif
                    </div>
                </div>
                @if ($canEdit)
                    @if ($a->status === 'pending')
                        @php($s = $suggest[$a->id])
                        <ul class="wa-checks mt">
                            @foreach ($s['checks'] as [$state, $key, $params])<li class="wa-{{ $state }}"><span aria-hidden="true">{{ ['ok' => '✓', 'warn' => '!', 'info' => 'i'][$state] }}</span> {{ __('web.checks.'.$key, $params) }}</li>@endforeach
                        </ul>
                        @if ($s['ready'])<p class="pill pill-ok">{{ __('web.staff.is_ready') }}</p>@endif
                        <form method="post" action="{{ route('web-accounts.approve', $a) }}" class="inline-form mt">@csrf
                            <label class="field"><span>{{ __('web.staff.company') }}</span>
                                <select name="company" required>
                                    @foreach ($s['companies'] as $c)<option value="{{ $c->id }}" @selected($s['choice'] === (string) $c->id)>{{ $c->name }}@if ($c->vat_number) · {{ $c->vat_number }}@endif @if ($c->city) · {{ $c->city }}@endif</option>@endforeach
                                    @if ($a->company_name)<option value="new" @selected($s['choice'] === 'new')>{{ __('web.staff.new_company', ['name' => $a->company_name]) }}</option>@endif
                                    <option value="none" @selected($s['choice'] === 'none')>{{ __('web.staff.private') }}</option>
                                </select>
                                <small>{{ __('web.staff.company_hint') }}</small>
                            </label>
                            @if ($s['contact'])<input type="hidden" name="contact_id" value="{{ $s['contact']->id }}">@endif
                            <button @class(['primary' => $s['ready'], 'btn' => ! $s['ready']]) type="submit" @disabled(! $a->email_verified_at)>{{ __($s['ready'] ? 'web.staff.confirm' : 'web.staff.approve') }}</button>
                        </form>
                    @endif
                    <div class="inline-form mt">
                        @if ($a->company_id && $a->status === 'active')
                            <form method="post" action="{{ route('web-accounts.role', $a) }}" class="inline-form">@csrf
                                <select name="role" aria-label="{{ __('site.account.col_role') }}" onchange="this.form.submit()">@foreach (\App\Models\Web\CustomerAccount::ROLES as $r)<option value="{{ $r }}" @selected($a->role === $r)>{{ __('site.account.roles.'.$r) }}</option>@endforeach</select>
                            </form>
                        @endif
                        @if ($a->status !== 'blocked')
                            <form method="post" action="{{ route('web-accounts.reset', $a) }}">@csrf<button class="btn" type="submit">{{ __('web.staff.send_reset') }}</button></form>
                            <form method="post" action="{{ route('web-accounts.block', $a) }}" class="inline-form">@csrf<input name="reason" maxlength="255" placeholder="{{ __('web.staff.reason') }}" aria-label="{{ __('web.staff.reason') }}"><button class="btn" type="submit">{{ __('web.staff.block') }}</button></form>
                        @else
                            <form method="post" action="{{ route('web-accounts.unblock', $a) }}">@csrf<button class="btn" type="submit">{{ __('web.staff.unblock') }}</button></form>
                        @endif
                    </div>
                @endif
            </section>
        @empty
            <p class="muted">{{ __('web.staff.none') }}</p>
        @endforelse
    </div>
    <x-pager :items="$accounts"/>
</x-layouts.app>
