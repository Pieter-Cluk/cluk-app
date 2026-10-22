@php($fmt = fn ($d) => $d?->timezone(config('app.timezone'))->format('d/m/Y H:i'))
<x-layouts.app :title="$member->name">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('afrit6.staff.index') }}">{{ mb_strtoupper(__('nav.items.afrit6')) }}</a></div>
            <h1>{{ $member->name }}</h1>
            <p class="muted">{{ $member->companyName() }} · {{ __('afrit6.staff.status.'.$member->status) }}</p>
        </div>
    </div>
    @include('partials.flash')

    <div class="split">
        <div class="stack">
            <section class="card">
                <h2>{{ __('afrit6.staff.registration') }}</h2>
                <dl class="facts">
                    <dt>{{ __('afrit6.fields.email') }}</dt><dd>{{ $member->email }} · {{ $member->email_verified_at ? __('afrit6.staff.confirmed_on', ['date' => $fmt($member->email_verified_at)]) : __('afrit6.staff.not_confirmed') }}</dd>
                    <dt>{{ __('afrit6.fields.mobile') }}</dt><dd>{{ $member->mobile ?? '—' }}</dd>
                    <dt>{{ __('afrit6.fields.company_name') }}</dt><dd>{{ $member->reg_company_name ?? '—' }}</dd>
                    <dt>{{ __('afrit6.fields.vat_number') }}</dt><dd>{{ $member->formattedRegVat() ?: '—' }}@if ($member->vies_status) · <span @class(['vies', 'vies-'.$member->vies_status, 'vies-unavailable' => $member->vies_reason === 'name_differs'])>{{ __('companies.vies.short_'.$member->vies_status) }}</span>@endif
                        @include('partials.vies-reason', ['reason' => $member->vies_reason, 'viesName' => $member->vies_name, 'given' => $member->reg_company_name, 'checked' => $member->vies_checked_at])
                        @if ($member->reg_vat_number && \App\Services\Vies::appliesTo($member->reg_vat_number) && auth()->user()->canUseModule('afrit6', 2))<form method="post" action="{{ route('afrit6.staff.vies', $member) }}">@csrf<button class="linkbtn" type="submit">{{ __('companies.vies.check_again') }}</button></form>@endif</dd>
                    <dt>{{ __('afrit6.fields.address') }}</dt><dd>{{ $member->reg_address ?? '—' }}</dd>
                    <dt>{{ __('afrit6.fields.country') }}</dt><dd>{{ $member->reg_country }}</dd>
                    <dt>{{ __('afrit6.staff.language') }}</dt><dd>{{ config('cluk.locales')[$member->locale] ?? $member->locale }}</dd>
                    <dt>{{ __('afrit6.staff.newsletter') }}</dt><dd>{{ $member->newsletter ? __('companies.yes') : __('companies.no') }}</dd>
                    <dt>{{ __('afrit6.staff.terms') }}</dt><dd>{{ $fmt($member->terms_accepted_at) ?? '—' }}</dd>
                    <dt>{{ __('terms.staff_accepted') }}</dt><dd>{{ $member->hasCurrentTerms() ? __('terms.staff_accepted_on', ['date' => $fmt($member->payment_terms_accepted_at), 'version' => $member->terms_version]) : __('terms.staff_not_accepted') }}</dd>
                    <dt>{{ __('afrit6.staff.registered') }}</dt><dd>{{ $fmt($member->created_at) }}</dd>
                    <dt>{{ __('afrit6.staff.last_login') }}</dt><dd>{{ $fmt($member->last_login_at) ?? __('users.index.never') }}</dd>
                    <dt>{{ __('afrit6.staff.password') }}</dt><dd class="muted">{{ __('afrit6.staff.password_note') }}</dd>
                </dl>
            </section>

            @if ($canDecide && $member->status === \App\Models\Member::PENDING)
                <section class="card">
                    <h2>{{ __('afrit6.staff.decide') }}</h2>
                    @if ($match)
                        <p>{{ __('afrit6.staff.match', ['name' => $match->name]) }} <a href="{{ route('companies.show', $match) }}">{{ __('afrit6.staff.open_company') }}</a></p>
                    @else
                        <p class="muted">{{ __('afrit6.staff.new_company', ['name' => $member->reg_company_name ?: $member->name]) }}</p>
                    @endif
                    <form method="post" action="{{ route('afrit6.staff.approve', $member) }}">
                        @csrf
                        @unless ($member->email_verified_at)
                            <label class="check"><input type="checkbox" name="email_checked" value="1"><span>{{ __('afrit6.staff.email_checked') }}</span></label>
                        @endunless
                        <div class="form-actions"><button class="primary" type="submit">{{ __('afrit6.staff.approve') }}</button></div>
                    </form>
                    <form method="post" action="{{ route('afrit6.staff.decline', $member) }}">
                        @csrf
                        <label class="field"><span>{{ __('afrit6.staff.decline_reason') }}</span><input name="reason" maxlength="500" required></label>
                        <div class="form-actions"><button class="btn btn-danger" type="submit">{{ __('afrit6.staff.decline') }}</button></div>
                    </form>
                </section>
            @endif
            @include('afrit6.staff._member-workspace')
        </div>

        <aside class="stack">
            <section class="card">
                <h2>{{ __('afrit6.staff.access') }}</h2>
                <p><span @class(['pill', 'pill-ok' => $member->isActive(), 'pill-warn' => $member->status === 'pending', 'pill-off' => in_array($member->status, ['declined', 'blocked'], true)])>{{ __('afrit6.staff.status.'.$member->status) }}</span></p>
                @if ($member->company)<p>{{ __('afrit6.staff.company') }}: <a href="{{ route('companies.show', $member->company) }}">{{ $member->company->name }}</a></p>@endif
                @if ($member->decline_reason)<p class="muted small">{{ __('afrit6.staff.reason') }}: {{ $member->decline_reason }}</p>@endif
                @if ($member->decidedBy)<p class="muted small">{{ __('afrit6.staff.decided_by', ['name' => $member->decidedBy->name]) }}</p>@endif
                @if ($canDecide)
                    <form method="post" action="{{ route('afrit6.staff.reset', $member) }}">@csrf<button class="btn" type="submit">{{ __('afrit6.staff.send_reset') }}</button></form>
                    @if ($member->status === \App\Models\Member::BLOCKED)
                        <form method="post" action="{{ route('afrit6.staff.unblock', $member) }}" class="mt">@csrf<button class="btn" type="submit">{{ __('afrit6.staff.unblock') }}</button></form>
                    @else
                        <form method="post" action="{{ route('afrit6.staff.block', $member) }}" class="mt" onsubmit="return confirm(@js(__('afrit6.staff.block_confirm', ['name' => $member->name])))">@csrf<button class="btn btn-danger" type="submit">{{ __('afrit6.staff.block') }}</button></form>
                    @endif
                @endif
            </section>
            <section class="card">
                <h2>{{ __('users.edit.activity') }}</h2>
                @forelse ($activity as $log)
                    <div class="log-line">
                        <small class="muted">{{ $fmt($log->created_at) }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small>
                        <div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div>
                    </div>
                @empty
                    <p class="muted">{{ __('users.edit.no_activity') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.app>
