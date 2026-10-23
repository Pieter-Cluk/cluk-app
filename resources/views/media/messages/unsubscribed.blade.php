<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CLUK</title><link rel="stylesheet" href="{{ asset('css/cluk.css') }}">@include('partials.favicon')
</head>
<body>
<div class="login"><main class="login-main"><div class="login-box"><div class="login-panel">
    <div class="login-kicker">CLUK</div>
    <h1>{{ __('messages.unsub.title_'.$list) }}</h1>
    @if ($done)
        <p>{{ __('messages.unsub.done', ['email' => $send->email]) }}</p>
    @else
        <p>{{ __('messages.unsub.ask', ['email' => $send->email]) }}</p>
        <form method="post">@csrf<button class="primary" type="submit">{{ __('messages.unsub.go') }}</button></form>
    @endif
</div></div></main></div>
</body>
</html>
