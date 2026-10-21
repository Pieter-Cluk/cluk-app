<x-layouts.member :title="__('afrit6.register.details_title')">
    @include('afrit6._steps', ['step' => 2])
    <h1>{{ __('afrit6.register.details_title') }}</h1>
    @if (($reg['vies_status'] ?? null) === 'valid')
        <div class="member-ok">✓ {{ __('afrit6.register.vies_valid') }}<br><b>{{ $reg['vies_name'] ?? $reg['company_name'] }}</b>@if (!empty($reg['vat_number'])) · {{ \App\Support\VatNumber::format($reg['vat_number']) }}@endif<br>{{ $reg['vies_address'] ?? $reg['address'] }} · <a href="{{ route('afrit6.register') }}">{{ __('afrit6.register.change_company') }}</a></div>
    @else
        @if (($reg['vies_status'] ?? null) === 'invalid')<div class="member-warn">{{ __('afrit6.register.vies_invalid') }}</div>@elseif (($reg['vies_status'] ?? null) === 'unavailable')<div class="member-warn">{{ __('afrit6.register.vies_unavailable') }}</div>@endif
        <p>{{ $reg['company_name'] ?? $reg['vies_name'] }}@if (!empty($reg['vat_number'])) · {{ \App\Support\VatNumber::format($reg['vat_number']) }}@endif · <a href="{{ route('afrit6.register') }}">{{ __('afrit6.register.change_company') }}</a></p>
    @endif
    @include('afrit6._messages')
    <form method="post" action="{{ route('afrit6.register.store') }}" class="member-form">
        @csrf
        <div class="mgrid">
            <label class="mfield"><span>{{ __('afrit6.fields.first_name') }}</span><input name="first_name" value="{{ old('first_name') }}" required maxlength="80" autocomplete="given-name"></label>
            <label class="mfield"><span>{{ __('afrit6.fields.last_name') }}</span><input name="last_name" value="{{ old('last_name') }}" required maxlength="80" autocomplete="family-name"></label>
        </div>
        <label class="mfield"><span>{{ __('afrit6.fields.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email"></label>
        <label class="mfield"><span>{{ __('afrit6.fields.mobile') }}</span><input name="mobile" value="{{ old('mobile') }}" maxlength="40" inputmode="tel" autocomplete="tel"></label>
        <label class="mfield"><span>{{ __('afrit6.fields.choose_password') }}</span><input type="password" name="password" required autocomplete="new-password" minlength="10"></label>
        <ul class="pw-rules">
            <li>{{ __('afrit6.password_rules.length') }}</li>
            <li>{{ __('afrit6.password_rules.mix') }}</li>
            <li>{{ __('afrit6.password_rules.personal') }}</li>
        </ul>
        <label class="mcheck"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> {{ __('afrit6.register.terms') }}</label>
        @include('afrit6._terms-ticks')
        <label class="mcheck"><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter'))> {{ __('afrit6.register.newsletter') }}</label>
        <div class="mrow">
            <a href="{{ route('afrit6.register') }}">{{ __('afrit6.register.back') }}</a>
            <button class="ms-btn member-btn inline" type="submit">{{ __('afrit6.register.create') }}</button>
        </div>
    </form>
</x-layouts.member>
