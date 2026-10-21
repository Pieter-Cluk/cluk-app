@php($newCount = $incomingCount ?? \App\Models\Sales\IncomingMessage::whereIn('status', ['new'])->count())
<nav class="tabs" aria-label="{{ __('nav.items.sales') }}">
    <a href="{{ route('deals.index') }}" @class(['tab', 'tab-on' => $section === 'board']) @if ($section === 'board') aria-current="page" @endif>{{ __('deals.tabs.board') }}</a>
    <a href="{{ route('incoming.index') }}" @class(['tab', 'tab-on' => $section === 'incoming']) @if ($section === 'incoming') aria-current="page" @endif>{{ __('deals.tabs.incoming') }} @if ($newCount)<span class="pill pill-warn">{{ $newCount }}</span>@endif</a>
    <a href="{{ route('quotes.index') }}" @class(['tab', 'tab-on' => $section === 'quotes']) @if ($section === 'quotes') aria-current="page" @endif>{{ __('quotes.title') }}</a>
    <a href="{{ route('deals.index', ['view' => 'closed']) }}" @class(['tab', 'tab-on' => $section === 'closed']) @if ($section === 'closed') aria-current="page" @endif>{{ __('deals.tabs.closed') }}</a>
</nav>
