<x-layouts.app :title="__('search.title')">
    <div class="pagehead">
        <div>
            <h1>{{ __('search.title') }}</h1>
            @if ($q !== '')<p class="muted">{{ __('search.for', ['q' => $q]) }}</p>@endif
        </div>
    </div>

    <form method="get" action="{{ route('search') }}" class="filters" role="search">
        <label class="field-search"><x-icon name="search"/><input type="search" name="q" value="{{ $q }}" placeholder="{{ __('nav.search') }}" aria-label="{{ __('search.title') }}" autofocus></label>
        <button class="btn" type="submit">{{ __('search.go') }}</button>
    </form>

    @if ($q === '')
        <p class="muted">{{ __('search.hint') }}</p>
    @elseif (! $groups)
        <section class="card"><p class="muted">{{ __('search.none') }}</p></section>
    @else
        <div class="stack">
            @foreach ($groups as $g)
                <section class="card">
                    <h2>{{ $g['label'] }} <span class="muted">({{ count($g['items']) }})</span></h2>
                    <ul class="search-list">
                        @foreach ($g['items'] as $i)
                            <li><a href="{{ $i['url'] }}"><b>{{ $i['title'] }}</b></a>@if ($i['sub'])<br><small class="muted">{{ $i['sub'] }}</small>@endif</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
