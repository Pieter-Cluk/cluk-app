@php($open = $quote->isOpenForCustomer())
@php($answered = session('answered'))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $quote->title }} · CLUK</title>
<link rel="stylesheet" href="{{ asset('css/cluk.css') }}">
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
        <div class="doc-label">{{ mb_strtoupper(__('quotes.doc_title')) }} · {{ $quote->number }} · {{ mb_strtoupper(__('quotes.version', ['n' => $quote->version])) }} · {{ ($quote->sent_at ?? $quote->updated_at)->format('d/m/Y') }}</div>
        <h1 class="pub-h1">{{ $quote->title }}</h1>
        <p class="muted">{{ __('quotes.for', ['customer' => $quote->customerName()]) }}@if ($quote->company && $quote->contact) · {{ $quote->contact->name }}@endif @if ($quote->owner) · {{ __('quotes.prepared_short', ['name' => $quote->owner->name]) }}@endif</p>
    </div>

    @if ($answered === 'accepted' || ($quote->status === 'accepted' && ! $answered))
        <div class="note note-ok" role="status">{{ __('quotes.public.done_accepted', ['name' => \Illuminate\Support\Str::before((string) $quote->answered_name, ' ')]) }}@if ($quote->po_number) {{ __('quotes.public.done_po', ['po' => $quote->po_number]) }}@endif</div>
    @elseif ($answered === 'change' || ($quote->status === 'change' && ! $answered))
        <div class="note note-ok" role="status">{{ __('quotes.public.done_change', ['name' => $quote->owner?->first_name ?? 'CLUK']) }}</div>
    @elseif ($answered === 'declined' || $quote->status === 'declined')
        <div class="note note-ok" role="status">{{ __('quotes.public.done_declined') }}</div>
    @elseif ($quote->status === 'replaced')
        <div class="note note-warn" role="status">{{ __('quotes.public.replaced') }}@if ($newer) <a href="{{ route('quote.public', $newer->token) }}">{{ __('quotes.public.see_newer') }}</a>@endif</div>
    @elseif ($quote->isExpired())
        <div class="note note-warn" role="status">{{ __('quotes.public.expired', ['date' => $quote->valid_until->format('d/m/Y')]) }}</div>
    @elseif ($open && $quote->reserved_until && $quote->reserved_until->isFuture())
        <div class="note note-warn">{!! __('quotes.public.reserved', ['date' => '<b>'.e($quote->reserved_until->locale(app()->getLocale())->translatedFormat('j F Y, H:i')).'</b>']) !!}</div>
    @endif

    <section class="card">
        <div class="pub-facts">
            <div><div class="doc-label">{{ mb_strtoupper(__('quotes.doc_pickup')) }}</div><b>{{ $quote->out_from->locale(app()->getLocale())->translatedFormat('D j M · H:i') }}</b></div>
            <div><div class="doc-label">{{ mb_strtoupper(__('quotes.doc_return')) }}</div><b>{{ $quote->out_until->locale(app()->getLocale())->translatedFormat('D j M · H:i') }}</b></div>
            <div><div class="doc-label">{{ mb_strtoupper(__('quotes.doc_use')) }}</div><b>{{ trans_choice('quotes.used_days', $quote->usedDays(), ['n' => $quote->usedDays()]) }}</b></div>
            @if ($quote->location)<div><div class="doc-label">{{ mb_strtoupper(__('quotes.location')) }}</div><b>{{ $quote->location }}</b></div>@endif
            @if ($quote->logistics !== 'open')<div><div class="doc-label">{{ mb_strtoupper(__('quotes.transport.title')) }}</div><b>{{ $quote->logisticsText() }}</b></div>@endif
        </div>
        @if ($quote->intro)<p>{!! nl2br(e($quote->intro)) !!}</p>@endif
        <div class="table-wrap">@include('sales.quotes._doc-lines')</div>
        @if ($quote->notes)<p class="muted small">{!! nl2br(e($quote->notes)) !!}</p>@endif
        <p><a href="{{ route('quote.public.print', $quote->token) }}" target="_blank" rel="noopener">{{ __('quotes.public.print') }}</a></p>
    </section>
    @if ($pbanner = \App\Models\Media\Message::inUse('banner'))@include('media.messages._banner', ['b' => $pbanner, 'loc' => app()->getLocale()])@endif

    @if ($open && ! $answered)
        @if ($errors->any())<div class="flash flash-err" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <section class="card">
            <h2>{{ __('quotes.public.confirm') }}</h2>
            <form method="post" action="{{ route('quote.public.accept', $quote->token) }}">
                @csrf
                <div class="form-grid">
                    <label class="field"><span>{{ __('quotes.public.po') }}</span><input name="po_number" maxlength="80" value="{{ old('po_number') }}" placeholder="{{ __('quotes.public.po_hint') }}"></label>
                    <label class="field"><span>{{ __('quotes.confirmed_by') }}</span><input name="name" required maxlength="120" value="{{ old('name', $quote->contact?->name) }}" autocomplete="name"></label>
                </div>
                <p class="muted small">{{ __('quotes.public.po_note') }}</p>
                <label class="check"><input type="checkbox" name="terms" value="1" required> <span>{!! __('quotes.public.terms_link', ['link' => '<a href="'.e(route('terms', app()->getLocale())).'" target="_blank" rel="noopener">'.e(__('quotes.public.terms_name')).'</a>']) !!}</span></label>
                <div class="form-actions"><button class="primary" type="submit">{{ __('quotes.public.accept') }}</button></div>
            </form>
            <details class="mt">
                <summary>{{ __('quotes.public.change') }}</summary>
                <form method="post" action="{{ route('quote.public.change', $quote->token) }}" class="stack-sm mt">
                    @csrf
                    <label class="field"><span>{{ __('quotes.public.change_what') }}</span><textarea name="message" rows="3" required maxlength="3000">{{ old('message') }}</textarea></label>
                    <input type="hidden" name="name" value="{{ $quote->contact?->name }}">
                    <button class="btn" type="submit">{{ __('quotes.public.change_send', ['name' => $quote->owner?->first_name ?? 'CLUK']) }}</button>
                </form>
            </details>
            <details class="mt">
                <summary>{{ __('quotes.public.decline') }}</summary>
                <form method="post" action="{{ route('quote.public.decline', $quote->token) }}" class="stack-sm mt">
                    @csrf
                    <fieldset class="plain"><legend>{{ __('quotes.public.why') }}</legend>
                        @foreach (\App\Models\Sales\Quote::DECLINE_REASONS as $r)
                            <label class="check"><input type="radio" name="reason" value="{{ $r }}" required> {{ __('quotes.decline_reasons.'.$r) }}</label>
                        @endforeach
                    </fieldset>
                    <input name="note" maxlength="1000" placeholder="{{ __('quotes.public.note') }}" aria-label="{{ __('quotes.public.note') }}">
                    <button class="btn" type="submit">{{ __('quotes.public.decline_go') }}</button>
                </form>
            </details>
        </section>
    @endif

    <p class="muted small">{{ __('quotes.public.questions') }}@if ($quote->owner) {{ $quote->owner->name }} · <a href="mailto:{{ $quote->owner->email }}">{{ $quote->owner->email }}</a>@endif · {{ trim($me->legal_name.' '.$me->legal_form) }}, {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}</p>
</main>
</body>
</html>
