{{-- Quotes & sales › Quotes: rental quotes (R-QUO) or sales quotes (S-QUO). --}}
<nav class="chips mt" aria-label="{{ __('quotes.title') }}">
    <a href="{{ route('quotes.index') }}" @class(['chip', 'chip-on' => $kind === 'rental']) @if ($kind === 'rental') aria-current="page" @endif>{{ __('quotes.kinds.rental') }}</a>
    <a href="{{ route('psales.index') }}" @class(['chip', 'chip-on' => $kind === 'sales']) @if ($kind === 'sales') aria-current="page" @endif>{{ __('quotes.kinds.sales') }}</a>
</nav>
