@extends('rental.docs.layout')
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
@section('body')
    <p class="doc-intro">{{ __('docs.ch.intro', ['order' => $order->number]) }}</p>
    <table class="doc-table">
        <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th></tr></thead>
        <tbody>
            @foreach ($charges as $c)
                <tr><td class="num">{{ $c->qty }}</td><td>{{ $c->label }}</td><td class="num nowrap">{{ $eur($c->amount_cents) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="doc-totals">
        <div class="strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($net) }}</span></div>
        <div class="muted"><span>{{ __('quotes.vat', ['pct' => $order->vatPercent()]) }}</span><span>{{ $eur($vatCents) }}</span></div>
        <div class="grand"><span>{{ __('docs.pf.to_pay') }}</span><span>{{ $eur($net + $vatCents) }}</span></div>
    </div>
@endsection
@section('notes')
    <b>{{ __('docs.notes_title.pf') }}</b>
    <p><b>{{ __('docs.pf.not_invoice') }}</b> {{ __('docs.ch.when') }}</p>
    <dl class="doc-meta pay">
        <dt>{{ __('docs.pf.beneficiary') }}</dt><dd>{{ trim($me->legal_name.' '.$me->legal_form) }}</dd>
        <dt>IBAN</dt><dd>{{ $bank?->formattedIban() ?? __('docs.pf.no_bank') }}</dd>
        @if ($bank?->bic)<dt>BIC</dt><dd>{{ $bank->bic }}</dd>@endif
        <dt>{{ __('docs.pf.reference') }}</dt><dd>{{ $code }}</dd>
    </dl>
    <p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', route('terms', app()->getLocale()))]) }}</p>
@endsection
