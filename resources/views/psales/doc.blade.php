{{-- Sales documents: S-QUO, S-OC, S-PICK, S-DN, S-PF; also the customer's page (public). --}}
@php($bank = \App\Models\BankAccount::where('on_documents', true)->orderByDesc('is_main')->first())
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($public = $public ?? false)
@php($docType = ['quo' => 'S-QUO', 'oc' => 'S-OC', 'pick' => 'S-PICK', 'dn' => 'S-DN', 'pf' => 'S-PF'][$type])
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $code }} · {{ $q->title }}</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
</head>
<body class="doc-body">
<div class="doc-bar no-print"><button type="button" class="primary" onclick="window.print()">{{ __('quotes.print_save') }}</button></div>
@if ($public)
    <div class="doc-public no-print">
        @if (session('status'))<div class="flash flash-ok" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="flash flash-err" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        @if ($q->status === 'sent' && (! $q->valid_until || $q->valid_until->gte(today())))
            <form method="post" action="{{ route('psales.public.accept', $q->token) }}" class="card stack-sm">@csrf
                <h2>{{ __('psales.public.accept_title') }}</h2>
                <label class="field"><span>{{ __('psales.public.name') }}</span><input name="name" required maxlength="120" value="{{ old('name', $q->contact?->name) }}"></label>
                <label class="field"><span>{{ __('quotes.po') }}</span><input name="po_number" maxlength="80" value="{{ old('po_number') }}"></label>
                <label class="check"><input type="checkbox" name="terms" value="1" required> <span>{!! __('psales.public.terms', ['url' => e(route('terms', app()->getLocale()))]) !!}</span></label>
                <div class="inline-form"><button class="primary" type="submit">{{ __('psales.public.accept') }}</button></div>
            </form>
            <details class="card"><summary>{{ __('psales.public.decline') }}</summary>
                <form method="post" action="{{ route('psales.public.decline', $q->token) }}" class="stack-sm mt">@csrf<input name="reason" maxlength="500" placeholder="{{ __('psales.public.reason') }}" aria-label="{{ __('psales.public.reason') }}"><div><button class="btn" type="submit">{{ __('psales.public.decline_go') }}</button></div></form>
            </details>
        @else
            <p class="flash flash-ok">{{ __('psales.public.state.'.$q->status) }}</p>
        @endif
    </div>
@endif
<article class="doc">
    <header class="doc-head">
        <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="34">
        <div class="doc-me"><b>{{ trim($me->legal_name.' '.$me->legal_form) }}</b><br>{{ $me->street }}, {{ $me->postcode }} {{ $me->city }}<br>{{ \App\Support\VatNumber::format($me->vat_number) }}@if ($me->email) · {{ $me->email }}@endif</div>
    </header>
    <div class="doc-title">
        <div>
            <div class="doc-kind">{{ mb_strtoupper(__('documents.types.'.$docType)) }}@if (! $prices) · <span class="doc-flag">{{ mb_strtoupper(__('psales.doc.no_prices')) }}</span>@endif @if ($type === 'pf') · <span class="doc-flag">{{ mb_strtoupper(__('docs.flags.pf')) }}</span>@endif</div>
            <h1>{{ $q->title }}</h1>
        </div>
        <div class="doc-code"><b>{{ $code }}</b><br>{{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="doc-parties">
        <div>
            <div class="doc-label">{{ mb_strtoupper(__('psales.doc.party_'.($type === 'quo' ? 'quo' : 'other'))) }}</div>
            <b>{{ $q->customerName() }}</b><br>
            @if ($q->contact){{ __('quotes.attn', ['name' => $q->contact->name]) }}<br>@endif
            @if ($q->company?->street){{ $q->company->street }}<br>{{ $q->company->postcode }} {{ $q->company->city }}<br>@endif
            @if ($q->company?->vat_number){{ $q->company->formattedVat() }}@endif
        </div>
        <dl class="doc-meta">
            <dt>{{ __('psales.doc.number') }}</dt><dd>{{ $q->number }}</dd>
            @if ($type === 'quo' && $q->valid_until)<dt>{{ __('psales.valid_until') }}</dt><dd>{{ $q->valid_until->format('d/m/Y') }}</dd>@endif
            @if ($q->po_number)<dt>{{ __('quotes.po') }}</dt><dd>{{ $q->po_number }}</dd>@endif
            <dt>{{ __('psales.delivery') }}</dt><dd>{{ __('psales.delivery_modes.'.$q->delivery_mode) }}</dd>
            @if ($q->owner)<dt>{{ __('psales.doc.contact') }}</dt><dd>{{ $q->owner->name }}</dd>@endif
        </dl>
    </div>
    @if ($q->intro && in_array($type, ['quo', 'oc'], true))<p class="doc-intro">{!! nl2br(e($q->intro)) !!}</p>@endif
    <table class="doc-table @if (! $prices) doc-check @endif">
        <thead><tr><th class="num">{{ __('quotes.cols.qty') }}</th><th>{{ __('quotes.cols.description') }}</th>
            @if ($prices)<th class="num">{{ __('psales.cols.list') }}</th><th class="num">{{ __('psales.cols.disc') }}</th><th class="num">{{ __('quotes.cols.amount') }}</th>@else<th>{{ __('psales.doc.item_no') }}</th><th class="box">{{ __('psales.doc.check') }}</th>@endif
            @if ($type === 'oc')<th>{{ __('psales.cols.delivery') }}</th>@endif</tr></thead>
        <tbody>
            @foreach ($q->lines as $l)
                @if ($l->kind === 'text')
                    <tr class="doc-text"><td></td><td colspan="{{ $prices ? 4 : 3 }}"><i>{{ $l->name }}</i></td></tr>
                @elseif ($prices || $l->kind === 'item')
                    <tr><td class="num"><b>{{ $l->qty }}</b></td><td>{{ $l->name }}@if ($l->description)<br><small>{{ $l->description }}</small>@endif</td>
                        @if ($prices)<td class="num nowrap">{{ $eur($l->list_cents) }}</td><td class="num">{{ $l->discount_bp ? rtrim(rtrim(number_format($l->discount_bp / 100, 2, ',', ''), '0'), ',').'%' : '' }}</td><td class="num nowrap">{{ $eur($l->amount_cents) }}</td>
                        @else<td><small>{{ $l->item?->number }}</small></td><td class="box">☐</td>@endif
                        @if ($type === 'oc')<td>{{ $l->delivery_date?->format('d/m/Y') ?? '—' }}</td>@endif
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    @if ($prices)
        <div class="doc-totals">
            <div class="strong"><span>{{ __('quotes.total_excl') }}</span><span>{{ $eur($q->net_cents) }}</span></div>
            <div class="muted"><span>{{ __('quotes.vat', ['pct' => $q->vatPercent()]) }}</span><span>{{ $eur($q->vat_cents) }}</span></div>
            <div class="grand"><span>{{ $type === 'pf' ? __('docs.pf.to_pay') : __('quotes.total') }}</span><span>{{ $eur($q->gross_cents) }}</span></div>
        </div>
    @endif
    <section class="doc-notes">
        @if ($type === 'quo')
            <b>{{ __('quotes.doc_notes') }}</b>
            @if ($q->notes)<p>{!! nl2br(e($q->notes)) !!}</p>@endif
            <p>{{ __('psales.doc.quo_note', ['date' => $q->valid_until?->format('d/m/Y') ?? '—']) }}</p>
        @elseif ($type === 'oc')
            <b>{{ __('psales.doc.oc_title') }}</b><p>{{ __('psales.doc.oc_note') }}</p>
        @elseif ($type === 'pf')
            <b>{{ __('docs.notes_title.pf') }}</b>
            <p><b>{{ __('docs.pf.not_invoice') }}</b> {{ __('psales.doc.pf_when') }}</p>
            <dl class="doc-meta pay">
                <dt>{{ __('docs.pf.beneficiary') }}</dt><dd>{{ trim($me->legal_name.' '.$me->legal_form) }}</dd>
                <dt>IBAN</dt><dd>{{ $bank?->formattedIban() ?? __('docs.pf.no_bank') }}</dd>
                @if ($bank?->bic)<dt>BIC</dt><dd>{{ $bank->bic }}</dd>@endif
                <dt>{{ __('docs.pf.reference') }}</dt><dd>{{ $code }}</dd>
            </dl>
        @elseif ($type === 'pick')
            <b>{{ __('psales.doc.pick_title') }}</b><p>{{ __('psales.doc.pick_note') }}</p>
        @else
            <b>{{ __('psales.doc.dn_title') }}</b><p>{{ __('psales.doc.dn_note', ['order' => $q->docCode('S-OC')]) }}</p>
        @endif
        @if (in_array($type, ['quo', 'oc', 'dn', 'pf'], true))<p>{{ __('quotes.doc_terms_link', ['url' => preg_replace('#^https?://#', '', route('terms', app()->getLocale()))]) }}</p>@endif
    </section>
    @if (in_array($type, ['quo', 'oc', 'dn', 'pf'], true))@include('partials.doc-extras', ['type' => $docType])@endif
    @if (in_array($type, ['pick', 'dn'], true))
        <div class="doc-signs"><div class="sign"><span>{{ __($type === 'pick' ? 'docs.signs.picked' : 'docs.signs.released') }}</span></div><div class="sign"><span>{{ __($type === 'pick' ? 'docs.signs.checked' : 'docs.signs.received') }}</span></div></div>
    @endif
    <footer class="doc-foot">
        {{ trim($me->legal_name.' '.$me->legal_form) }} · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}@if ($bank) · IBAN {{ $bank->formattedIban() }}@endif
    </footer>
</article>
</body>
</html>
