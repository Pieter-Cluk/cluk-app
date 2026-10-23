<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('login.title') }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
@include('partials.favicon')
</head>
<body>
<div class="login">
    <header class="login-top">
        <nav class="login-langs" aria-label="{{ __('login.language') }}">
            @foreach (config('cluk.locales') as $code => $name)
                <form method="post" action="{{ route('locale.switch', $code) }}">
                    @csrf
                    <button type="submit" title="{{ $name }}" aria-current="{{ app()->getLocale() === $code ? 'true' : 'false' }}">{{ strtoupper($code) }}</button>
                </form>
            @endforeach
        </nav>
    </header>

    <main class="login-main">
        <div class="login-box">
            <div class="login-panel">
                <div class="login-kicker">{{ __('login.tagline') }}</div>
                <h1>{{ __('login.title') }}</h1>
                <p>{{ __('login.sub') }}</p>

                @if (session('login_error'))
                    <div class="login-error" role="alert">
                        {{ __('login.error_'.session('login_error'), ['email' => session('login_email', '')]) }}
                    </div>
                @endif

                <a class="ms-btn" href="{{ route('login.microsoft') }}">
                    <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><rect width="9.5" height="9.5" fill="#f25022"/><rect x="10.5" width="9.5" height="9.5" fill="#7fba00"/><rect y="10.5" width="9.5" height="9.5" fill="#00a4ef"/><rect x="10.5" y="10.5" width="9.5" height="9.5" fill="#ffb900"/></svg>
                    {{ __('login.button') }}
                </a>
                <a class="code-link" href="{{ route('login.code') }}">{{ __('login.code.link') }}</a>
                <p class="login-note">{{ __('login.no_access') }}</p>
            </div>
            <p class="login-member"><a href="/afrit6">{{ __('login.member_link') }}</a></p>
        </div>
    </main>
</div>
</body>
</html>
