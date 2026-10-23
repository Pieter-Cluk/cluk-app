<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('login.code.title') }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
@include('partials.favicon')
</head>
<body>
<div class="login">
    <main class="login-main">
        <div class="login-box">
            <div class="login-panel">
                <div class="login-kicker">{{ __('login.tagline') }}</div>
                <h1>{{ __('login.code.title') }}</h1>
                @if (session('status'))<div class="flash flash-ok" role="status">{{ session('status') }}</div>@endif
                @if ($sent)
                    <form method="post" action="{{ route('login.code.verify') }}" class="stack-sm">@csrf
                        <label class="field"><span>{{ __('login.code.code') }}</span>
                            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus class="code-input" aria-describedby="code-hint"></label>
                        @error('code')<div class="login-error" role="alert">{{ $message }}</div>@enderror
                        <small class="muted" id="code-hint">{{ __('login.code.code_hint', ['minutes' => \App\Models\StaffLoginCode::MINUTES]) }}</small>
                        <button class="primary" type="submit">{{ __('login.code.sign_in') }}</button>
                    </form>
                    <form method="post" action="{{ route('login.code.send') }}" class="mt">@csrf<input type="hidden" name="email" value="{{ $email }}"><button class="linkbtn" type="submit">{{ __('login.code.again') }}</button></form>
                @else
                    <p>{{ __('login.code.intro', ['domain' => \App\Http\Controllers\Auth\EmailCodeLoginController::domain()]) }}</p>
                    <form method="post" action="{{ route('login.code.send') }}" class="stack-sm">@csrf
                        <label class="field"><span>{{ __('login.code.email') }}</span><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@{{ \App\Http\Controllers\Auth\EmailCodeLoginController::domain() }}"></label>
                        @error('email')<div class="login-error" role="alert">{{ $message }}</div>@enderror
                        <button class="primary" type="submit">{{ __('login.code.send') }}</button>
                    </form>
                @endif
                <p class="login-note"><a href="{{ route('login') }}">{{ __('login.code.back') }}</a></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
