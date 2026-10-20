<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
</head>
<body>
<main class="page">
    <div class="card">
        <h1 style="margin-top:0">{{ __('login.welcome', ['name' => auth()->user()->first_name]) }}</h1>
        <h2 style="font-size:15px">{{ __('login.your_modules') }}</h2>
        <div class="chips">
            @foreach (auth()->user()->allowedModules() as $module)
                <span class="chip">{{ __('cluk.modules.'.$module) }}</span>
            @endforeach
        </div>
        <p style="color:var(--muted)">{{ __('login.coming') }}</p>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="btn" type="submit">{{ __('login.logout') }}</button>
        </form>
    </div>
</main>
</body>
</html>
