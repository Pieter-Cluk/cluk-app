{{-- The customer's page for return costs: lines with the photos, the deadline, and a way to react. --}}
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($net = (int) $charges->sum('amount_cents'))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('checks.public.title') }} · {{ $order->title }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}?v={{ @filemtime(public_path('css/cluk.css')) }}">
@include('partials.favicon')
</head>
<body class="doc-body">
<header class="pub-top">
    <div class="pub-wrap pub-row">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="30">
        <nav class="pub-langs" aria-label="{{ __('login.language') }}">
            @foreach (config('cluk.locales') as $code => $name)
                <a href="?lang={{ $code }}" @class(['pub-lang', 'pub-lang-on' => app()->getLocale() === $code]) lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</a>
            @endforeach
        </nav>
    </div>
</header>
<main class="pub-wrap pub-main">
    <div>
        <div class="doc-label">{{ mb_strtoupper(__('checks.public.title')) }} · {{ $order->number }}</div>
        <h1 class="pub-h1">{{ $order->title }}</h1>
        <p class="muted">{{ __('quotes.for', ['customer' => $order->customerName()]) }}@if ($n->sentBy) · {{ __('quotes.prepared_short', ['name' => $n->sentBy->name]) }}@endif</p>
    </div>
    @if (session('status') || $n->status === 'objected')
        <div class="note note-ok" role="status">{{ __('checks.public.thanks') }}</div>
    @elseif ($n->status === 'invoiced')
        <div class="note note-ok" role="status">{{ __('checks.public.invoiced') }}</div>
    @elseif ($n->status === 'sent')
        <div class="note note-warn">{{ __('checks.public.deadline', ['date' => $n->deadline->locale(app()->getLocale())->translatedFormat('j F Y')]) }}</div>
    @endif
    <section class="card">
        <p>{{ __('checks.public.intro') }}</p>
        <div class="table-wrap"><table class="doc-table">
            <thead><tr><th>{{ __('checks.public.what') }}</th><th class="num">{{ __('checks.public.qty') }}</th><th class="num">{{ __('checks.public.amount') }}</th></tr></thead>
            <tbody>
                @foreach ($charges as $c)
                    <tr><td>{{ $c->label }}
                        @if ($c->check && $c->check->photos)<div class="ch-photos">@foreach ($c->check->photos as $pi => $ph)<a href="{{ route('costs.photo', [$n->token, $c->check, $pi]) }}" target="_blank"><img src="{{ route('costs.photo', [$n->token, $c->check, $pi]) }}" alt="{{ __('checks.public.photo') }}" loading="lazy"></a>@endforeach</div>@endif</td>
                        <td class="num">{{ $c->qty }}</td><td class="num nowrap">{{ $eur($c->amount_cents) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="2" class="num">{{ __('checks.public.net') }}</td><td class="num nowrap">{{ $eur($net) }}</td></tr>
                <tr><td colspan="2" class="num">{{ __('checks.public.vat', ['pct' => rtrim(rtrim(number_format($vatBp / 100, 2, ',', ''), '0'), ',')]) }}</td><td class="num nowrap">{{ $eur((int) round($net * $vatBp / 10000)) }}</td></tr>
            </tfoot>
        </table></div>
    </section>
    @if ($n->status === 'sent' && ! session('status'))
        <section class="card">
            <h2>{{ __('checks.public.react_title') }}</h2>
            <p class="muted">{{ __('checks.public.react_hint') }}</p>
            <form method="post" action="{{ route('costs.react', $n->token) }}" class="stack-sm">@csrf
                <textarea name="text" rows="4" maxlength="2000" required aria-label="{{ __('checks.public.react_title') }}"></textarea>
                @error('text')<p class="flash flash-err">{{ $message }}</p>@enderror
                <div><button class="btn" type="submit">{{ __('checks.public.react_go') }}</button></div>
            </form>
        </section>
    @endif
    <p class="muted small">{{ $me->legal_name ?? 'CLUK BV' }}</p>
</main>
</body>
</html>
