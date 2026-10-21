@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $quote->code() }} · {{ $quote->title }}</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
</head>
<body class="doc-body">
<div class="doc-bar no-print"><button type="button" class="primary" onclick="window.print()">{{ __('quotes.print_save') }}</button></div>
<article class="doc">
    <header class="doc-head">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="34">
        <div class="doc-me">
            <b>{{ trim($me->legal_name.' '.$me->legal_form) }}</b><br>
            {{ $me->street }}, {{ $me->postcode }} {{ $me->city }}<br>
            {{ \App\Support\VatNumber::format($me->vat_number) }}@if ($me->email) · {{ $me->email }}@endif
        </div>
    </header>
    <div class="doc-title">
        <div>
            <div class="doc-kind">{{ mb_strtoupper(__('quotes.doc_title')) }}</div>
            <h1>{{ $quote->title }}</h1>
        </div>
        <div class="doc-code"><b>{{ $quote->number }}</b><br>{{ __('quotes.version', ['n' => $quote->version]) }} · {{ ($quote->sent_at ?? $quote->updated_at)->format('d/m/Y') }}</div>
    </div>
    <div class="doc-parties">
        <div>
            <div class="doc-label">{{ mb_strtoupper(__('quotes.quoted_to')) }}</div>
            @if ($quote->company)
                <b>{{ $quote->company->name }}</b><br>
                @if ($quote->contact){{ __('quotes.attn', ['name' => $quote->contact->name]) }}<br>@endif
                @if ($quote->company->street){{ $quote->company->street }}<br>{{ $quote->company->postcode }} {{ $quote->company->city }}<br>@endif
                @if ($quote->company->vat_number){{ $quote->company->formattedVat() }}@endif
            @else
                <b>{{ $quote->contact?->name ?? '—' }}</b>
            @endif
        </div>
        <dl class="doc-meta">
            <dt>{{ __('quotes.doc_use') }}</dt><dd>{{ $quote->use_from->format('d/m/Y') }} – {{ $quote->use_until->format('d/m/Y') }}</dd>
            <dt>{{ __('quotes.doc_pickup') }}</dt><dd>{{ $quote->out_from->format('d/m/Y H:i') }}</dd>
            <dt>{{ __('quotes.doc_return') }}</dt><dd>{{ $quote->out_until->format('d/m/Y H:i') }}</dd>
            @if ($quote->location)<dt>{{ __('quotes.location') }}</dt><dd>{{ $quote->location }}</dd>@endif
            @if ($quote->po_number)<dt>{{ __('quotes.po') }}</dt><dd>{{ $quote->po_number }}</dd>@endif
        </dl>
    </div>
    @if ($quote->intro)<p class="doc-intro">{!! nl2br(e($quote->intro)) !!}</p>@endif
    @include('sales.quotes._doc-lines')
    <section class="doc-notes">
        <b>{{ __('quotes.doc_notes') }}</b>
        @if ($quote->notes)<p>{!! nl2br(e($quote->notes)) !!}</p>@endif
        <p>{{ __('quotes.doc_terms', ['valid' => ($quote->valid_until ?? today()->addDays($me->quote_valid_days ?: 14))->format('d/m/Y'), 'days' => $me->payment_term_days ?: 30]) }}</p>
        <p>{{ __('quotes.doc_factor') }}</p>
        <p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', url('/terms'))]) }}</p>
    </section>
    <footer class="doc-foot">
        {{ trim($me->legal_name.' '.$me->legal_form) }} · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}@if ($bank) · IBAN {{ $bank->formattedIban() }}@endif
        @if ($quote->owner)<br>{{ __('quotes.prepared_by', ['name' => $quote->owner->name, 'email' => $quote->owner->email]) }}@endif
    </footer>
</article>
</body>
</html>
