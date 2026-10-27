<x-layouts.member :title="__('afrit6.login.title')">
    <div class="login-kicker">AFRIT 6 · CLUK WORKSPACE</div>
    <h1>{{ __('afrit6.login.title') }}</h1>
    <p>{{ __('afrit6.login.sub') }}</p>
    @include('afrit6._messages')
    <form method="post" action="{{ route('afrit6.login.attempt') }}" class="member-form">
        @csrf
        <label class="mfield"><span>{{ __('afrit6.fields.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus></label>
        <label class="mfield"><span>{{ __('afrit6.fields.password') }}</span><input type="password" name="password" required autocomplete="current-password"></label>
        <div class="mrow">
            <label class="mcheck"><input type="checkbox" name="remember" value="1"> {{ __('afrit6.login.remember') }}</label>
            <a href="{{ route('afrit6.password.request') }}">{{ __('afrit6.login.forgot') }}</a>
        </div>
        <button class="ms-btn member-btn" type="submit">{{ __('afrit6.login.button') }}</button>
    </form>
    <p class="login-note">{{ __('afrit6.login.new') }} <a href="{{ route('afrit6.register') }}">{{ __('afrit6.login.register') }}</a></p>
</x-layouts.member>
