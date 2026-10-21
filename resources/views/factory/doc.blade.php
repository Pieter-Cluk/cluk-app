{{-- Cluk Factory documents for the customer: the booking confirmation to sign (F-BC) and the proforma (F-PF). --}}
@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($fmt = fn ($d, $f = 'l j F Y') => $d->locale(app()->getLocale())->translatedFormat($f))
@php($code = ($type === 'pf' ? 'F-PF-' : 'F-BC-').\Illuminate\Support\Str::after($b->number, 'F-'))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $code }} · {{ $b->title }}</title>
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
            <div class="doc-kind">CLUK FACTORY · {{ mb_strtoupper(__('factory.doc.titles.'.$type)) }}@if ($type === 'pf') · <span class="doc-flag">{{ mb_strtoupper(__('docs.flags.pf')) }}</span>@endif</div>
            <h1>{{ $b->title }}</h1>
        </div>
        <div class="doc-code"><b>{{ $code }}</b><br>{{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="doc-parties">
        <div>
            <div class="doc-label">{{ mb_strtoupper(__('factory.doc.party')) }}</div>
            <b>{{ $b->customerName() }}</b><br>
            @if ($b->contact?->name ?? $b->req_name){{ __('quotes.attn', ['name' => $b->contact?->name ?? $b->req_name]) }}<br>@endif
            @if ($b->company?->street){{ $b->company->street }}<br>{{ $b->company->postcode }} {{ $b->company->city }}<br>@endif
            @if ($b->company?->vat_number){{ $b->company->formattedVat() }}@elseif ($b->req_vat){{ $b->req_vat }}@endif
        </div>
        <dl class="doc-meta">
            <dt>{{ __('factory.doc.booking') }}</dt><dd>{{ $b->number }}</dd>
            <dt>{{ __('factory.parts.event') }}</dt><dd>{{ $fmt($b->start_date, 'D d/m/Y') }}@if ($b->days > 1) – {{ $fmt($b->lastEventDay(), 'D d/m/Y') }}@endif</dd>
            <dt>{{ __('factory.times.load_in') }}</dt><dd>{{ $fmt($b->firstDay(), 'D d/m') }} · {{ $b->load_in_time }}</dd>
            <dt>{{ __('factory.times.doors') }}</dt><dd>{{ $b->doors_time }}</dd>
            <dt>{{ __('factory.times.end') }}</dt><dd>{{ $b->end_time }}</dd>
            <dt>{{ __('factory.times.load_out') }}</dt><dd>{{ $b->breakdown_days ? $fmt($b->lastDay(), 'D d/m').($b->load_out_time ? ' · '.$b->load_out_time : '') : __('factory.same_night').($b->load_out_time ? ' · '.$b->load_out_time : '') }}</dd>
            <dt>{{ __('quotes.location') }}</dt><dd>{{ config('factory.address') }}</dd>
        </dl>
    </div>

    @if ($type === 'bc')
        <table class="doc-table">
            <tbody>
                <tr><td>{{ __('factory.space') }}</td><td><b>{{ $b->size }}</b> · {{ __('factory.book.floor', ['floor' => config('factory.sizes.'.$b->size.'.0'), 'ent' => config('factory.sizes.'.$b->size.'.1')]) }}</td></tr>
                <tr><td>{{ __('factory.level') }}</td><td><b>{{ __('factory.levels.'.$b->level) }}</b></td></tr>
                <tr><td>{{ __('factory.book.guests') }}</td><td><b>{{ $b->guests }}</b></td></tr>
                <tr><td>{{ __('factory.event_type') }}</td><td>{{ __('factory.types.'.$b->event_type) }}</td></tr>
                <tr><td>{{ __('factory.extras_title') }}</td><td>{{ collect($b->extras ?? [])->map(fn ($x) => __('factory.extras.'.$x))->implode(', ') ?: '—' }}</td></tr>
                @if ($q)<tr><td>{{ __('factory.doc.price') }}</td><td><b>{{ $eur($q->net_cents) }}</b> {{ __('factory.doc.excl') }} · {{ __('factory.doc.as_quote', ['quote' => $q->code()]) }}</td></tr>@endif
            </tbody>
        </table>
    @else
        <p class="doc-intro">{{ __('factory.doc.pf_intro', ['quote' => $q->code()]) }}</p>
        <table class="doc-table">
            <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th><th class="num">{{ __('quotes.cols.rate') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th></tr></thead>
            <tbody>
                @foreach ($q->lines as $line)
                    @if ($line->kind === 'text')
                        <tr class="doc-text"><td></td><td colspan="3"><i>{{ $line->name }}</i></td></tr>
                    @else
                        <tr><td class="num">{{ $line->qtyLabel() }}</td><td>{{ $line->name }}</td><td class="num nowrap">{{ $eur($line->unit_price_cents) }}</td><td class="num nowrap">{{ $eur($line->amount_cents) }}</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <div class="doc-totals">
            @if ($q->discount_cents)
                <div><span>{{ __('quotes.subtotal_plain') }}</span><span>{{ $eur($q->subtotal_cents) }}</span></div>
                <div><span>{{ __('quotes.project_discount') }}</span><span>− {{ $eur($q->discount_cents) }}</span></div>
            @endif
            <div class="strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($q->net_cents) }}</span></div>
            <div class="muted"><span>{{ __('quotes.vat', ['pct' => $q->vatPercent()]) }}</span><span>{{ $eur($q->vat_cents) }}</span></div>
            <div class="grand"><span>{{ __('docs.pf.to_pay') }}</span><span>{{ $eur($q->gross_cents) }}</span></div>
        </div>
    @endif

    <section class="doc-notes">
        @if ($type === 'bc')
            <b>{{ __('factory.doc.rules_title') }}</b>
            <ul>@foreach (explode('|', __('factory.doc.rules', ['guests' => $b->guests])) as $rule)<li>{{ $rule }}</li>@endforeach</ul>
            <p>{{ __('factory.doc.sign_note') }}</p>
        @else
            <b>{{ __('docs.notes_title.pf') }}</b>
            <p><b>{{ __('docs.pf.not_invoice') }}</b> {{ __('factory.doc.pf_when') }}</p>
            <dl class="doc-meta pay">
                <dt>{{ __('docs.pf.beneficiary') }}</dt><dd>{{ trim($me->legal_name.' '.$me->legal_form) }}</dd>
                <dt>IBAN</dt><dd>{{ $bank?->formattedIban() ?? __('docs.pf.no_bank') }}</dd>
                @if ($bank?->bic)<dt>BIC</dt><dd>{{ $bank->bic }}</dd>@endif
                <dt>{{ __('docs.pf.reference') }}</dt><dd>{{ $code }}</dd>
            </dl>
        @endif
        <p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', route('terms', app()->getLocale()))]) }}</p>
    </section>
    @if ($type === 'bc')
        <div class="doc-signs">
            <div class="sign"><span>{{ __('factory.doc.sign_cluk') }}</span></div>
            <div class="sign"><span>{{ __('factory.doc.sign_customer') }}</span></div>
        </div>
    @endif
    <footer class="doc-foot">
        {{ trim($me->legal_name.' '.$me->legal_form) }} · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}@if ($bank) · IBAN {{ $bank->formattedIban() }}@endif
    </footer>
</article>
</body>
</html>
