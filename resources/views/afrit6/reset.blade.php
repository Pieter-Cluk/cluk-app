<x-layouts.member :title="__('afrit6.forgot.new_title')">
    <h1>{{ __('afrit6.forgot.new_title') }}</h1>
    @include('afrit6._messages')
    <form method="post" action="{{ route('afrit6.password.update') }}" class="member-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label class="mfield"><span>{{ __('afrit6.fields.email') }}</span><input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></label>
        <label class="mfield"><span>{{ __('afrit6.fields.choose_password') }}</span><input type="password" name="password" required autocomplete="new-password" minlength="10"></label>
        <ul class="pw-rules">
            <li>{{ __('afrit6.password_rules.length') }}</li>
            <li>{{ __('afrit6.password_rules.mix') }}</li>
            <li>{{ __('afrit6.password_rules.personal') }}</li>
        </ul>
        <button class="ms-btn member-btn" type="submit">{{ __('afrit6.forgot.save') }}</button>
    </form>
</x-layouts.member>
