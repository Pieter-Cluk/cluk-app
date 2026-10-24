{{-- Shared frame of the rental documents (R-PICK, R-DN, R-RET, R-PF). --}}
@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $code }} · {{ $order->title }}</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
</head>
<body class="doc-body">
<div class="doc-bar no-print"><button type="button" class="primary" onclick="window.print()">{{ __('quotes.print_save') }}</button></div>
<article class="doc">
    <header class="doc-head">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="34">
        <div class="doc-me"><b>{{ trim($me->legal_name.' '.$me->legal_form) }}</b><br>{{ $me->street }}, {{ $me->postcode }} {{ $me->city }}<br>{{ \App\Support\VatNumber::format($me->vat_number) }}@if ($me->email) · {{ $me->email }}@endif</div>
    </header>
    <div class="doc-title">
        <div>
            <div class="doc-kind">{{ mb_strtoupper(__('docs.titles.'.$type)) }} · <span class="doc-flag">{{ mb_strtoupper(__('docs.flags.'.$type)) }}</span></div>
            <h1>{{ $order->title }}</h1>
        </div>
        <div class="doc-code"><b>{{ $code }}</b><br>{{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="doc-parties">
        <div>
            <div class="doc-label">{{ mb_strtoupper(__('docs.party.'.$type)) }}</div>
            @if ($order->company)
                <b>{{ $order->company->name }}</b><br>
                @if ($order->contact){{ __('quotes.attn', ['name' => $order->contact->name]) }}<br>@endif
                @if ($order->company->street){{ $order->company->street }}<br>{{ $order->company->postcode }} {{ $order->company->city }}<br>@endif
                @if ($type === 'pf' && $order->company->vat_number){{ $order->company->formattedVat() }}@endif
            @else
                <b>{{ $order->contact?->name ?? $order->confirmed_name ?? '—' }}</b>
            @endif
        </div>
        <dl class="doc-meta">
            <dt>{{ __('docs.order') }}</dt><dd>{{ $order->number }}</dd>
            <dt>{{ __('quotes.doc_use') }}</dt><dd>{{ $order->use_from->format('d/m/Y') }} – {{ $order->use_until->format('d/m/Y') }}</dd>
            <dt>{{ __('quotes.doc_pickup') }}</dt><dd>{{ $order->out_from->format('d/m/Y H:i') }}</dd>
            <dt>{{ __('quotes.doc_return') }}</dt><dd>{{ $order->out_until->format('d/m/Y H:i') }}</dd>
            @if ($order->location)<dt>{{ __('quotes.location') }}</dt><dd>{{ $order->location }}</dd>@endif
            @if ($order->logistics !== 'open')<dt>{{ __('quotes.transport.title') }}</dt><dd>{{ $order->logisticsText() }}</dd>@endif
            @if ($order->po_number)<dt>{{ __('quotes.po') }}</dt><dd>{{ $order->po_number }}</dd>@endif
        </dl>
    </div>
    @yield('body')
    @hasSection('notes')
        <section class="doc-notes">@yield('notes')</section>
    @endif
    @hasSection('signs')
        <div class="doc-signs">@yield('signs')</div>
    @endif
    @if (in_array($type, ['dn', 'pf', 'ch'], true))@include('partials.doc-extras', ['type' => 'R-'.strtoupper($type)])@endif
    <footer class="doc-foot">
        {{ trim($me->legal_name.' '.$me->legal_form) }} · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}@if ($bank) · IBAN {{ $bank->formattedIban() }}@endif
    </footer>
</article>
</body>
</html>
