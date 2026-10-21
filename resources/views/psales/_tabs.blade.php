<nav class="tabs">
    <a @class(['tab', 'tab-on' => request()->routeIs('psales.index', 'psales.show', 'psales.create')]) href="{{ route('psales.index') }}">{{ __('psales.tabs.quotes') }}</a>
    <a @class(['tab', 'tab-on' => request()->routeIs('psales.items')]) href="{{ route('psales.items') }}">{{ __('psales.tabs.items') }}</a>
    @if (auth()->user()->canUseModule('purchasing'))<a @class(['tab', 'tab-on' => request()->routeIs('purchasing.*')]) href="{{ route('purchasing.index') }}">{{ __('nav.items.purchasing') }}</a>@endif
</nav>
