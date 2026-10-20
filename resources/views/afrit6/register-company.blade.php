@php($manual = session('need_manual') || $errors->has('company_name') || ($reg['vies_status'] ?? null) && ($reg['vies_status'] ?? null) !== 'valid')
<x-layouts.member :title="__('afrit6.register.company_title')">
    @include('afrit6._steps', ['step' => 1])
    <h1>{{ __('afrit6.register.company_title') }}</h1>
    <p>{{ __('afrit6.register.company_sub') }}</p>
    @include('afrit6._messages')
    @if (($reg['vies_status'] ?? null) === 'valid')
        <div class="member-ok">✓ {{ __('afrit6.register.vies_valid') }}<br><b>{{ $reg['vies_name'] }}</b><br>{{ $reg['vies_address'] }}</div>
    @elseif (($reg['vies_status'] ?? null) === 'invalid')
        <div class="member-warn">{{ __('afrit6.register.vies_invalid') }}</div>
    @elseif (($reg['vies_status'] ?? null) === 'unavailable')
        <div class="member-warn">{{ __('afrit6.register.vies_unavailable') }}</div>
    @elseif (session('need_manual'))
        <div class="member-warn">{{ __('afrit6.register.need_manual') }}</div>
    @endif
    <form method="post" action="{{ route('afrit6.register.company') }}" class="member-form">
        @csrf
        <div class="mgrid">
            <label class="mfield"><span>{{ __('afrit6.fields.country') }}</span>
                <select name="country">
                    @foreach (\App\Http\Controllers\Afrit6\MemberAuthController::COUNTRIES as $c)
                        <option value="{{ $c }}" @selected(old('country', $reg['country'] ?? 'BE') === $c)>{{ $c === 'OTHER' ? __('afrit6.register.other_country') : $c }}</option>
                    @endforeach
                </select></label>
            <label class="mfield"><span>{{ __('afrit6.fields.vat_number') }}</span>
                <input name="vat_number" value="{{ old('vat_number', $reg['vat_input'] ?? '') }}" placeholder="0123.456.789" maxlength="24"></label>
        </div>
        <p class="login-note left">{{ __('afrit6.register.vat_hint') }}</p>
        @if ($manual)
            <label class="mfield"><span>{{ __('afrit6.fields.company_name') }}</span><input name="company_name" value="{{ old('company_name', $reg['company_name'] ?? '') }}" maxlength="160"></label>
            <label class="mfield"><span>{{ __('afrit6.fields.address') }}</span><input name="address" value="{{ old('address', $reg['address'] ?? '') }}" maxlength="255" placeholder="{{ __('afrit6.register.address_hint') }}"></label>
        @endif
        <div class="mrow">
            <a href="{{ route('afrit6.login') }}">{{ __('afrit6.register.back_to_login') }}</a>
            <button class="ms-btn member-btn inline" type="submit">{{ __('afrit6.register.next') }}</button>
        </div>
    </form>
</x-layouts.member>
