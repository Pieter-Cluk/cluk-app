<x-layouts.member :title="__('afrit6.forgot.title')">
    <h1>{{ __('afrit6.forgot.title') }}</h1>
    <p>{{ __('afrit6.forgot.sub') }}</p>
    @include('afrit6._messages')
    <form method="post" action="{{ route('afrit6.password.email') }}" class="member-form">
        @csrf
        <label class="mfield"><span>{{ __('afrit6.fields.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
        <button class="ms-btn member-btn" type="submit">{{ __('afrit6.forgot.button') }}</button>
    </form>
    <p class="login-note"><a href="{{ route('afrit6.login') }}">{{ __('afrit6.register.back_to_login') }}</a></p>
</x-layouts.member>
