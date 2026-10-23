@props(['title' => null, 'wide' => false])
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ? $title.' · ' : '' }}Afrit 6</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
</head>
<body>
<div class="login member">
    <header class="login-top member-top">
        <a class="member-brand" href="{{ route('afrit6.login') }}"><span class="six">6</span> AFRIT 6</a>
        <div class="member-right">
        @include('partials.site-switch', ['current' => 'afrit6'])
        <nav class="login-langs" aria-label="{{ __('login.language') }}">
            @foreach (config('cluk.locales') as $code => $name)
                <form method="post" action="{{ route('locale.switch', $code) }}">
                    @csrf
                    <button type="submit" title="{{ $name }}" aria-current="{{ app()->getLocale() === $code ? 'true' : 'false' }}">{{ strtoupper($code) }}</button>
                </form>
            @endforeach
        </nav>
        </div>
    </header>
    <main class="login-main member-main">
        <div @class(['login-box', 'member-box-wide' => $wide])>
            <div class="login-panel member-panel">
                {{ $slot }}
            </div>
            <p class="login-member">{{ __('afrit6.footer') }}</p>
        </div>
    </main>
</div>
</body>
</html>
