<x-layouts.app :title="$a->t('title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('help.index') }}">{{ mb_strtoupper(__('nav.items.help')) }}</a> / <a href="{{ route('help.index', ['topic' => $a->topic]) }}">{{ mb_strtoupper(__('help.topics.'.$a->topic)) }}</a></div>
            <h1>{{ $a->t('title') }}</h1>
            @if ($a->t('intro'))<p class="muted">{{ $a->t('intro') }}</p>@endif
        </div>
        @if ($canEdit)<div class="inline-form"><a class="btn" href="{{ route('help.edit', $a) }}">{{ __('help.edit') }}</a></div>@endif
    </div>
    @include('partials.flash')
    @if (! $a->active)<div class="flash flash-err">{{ __('help.hidden') }}</div>@endif

    <div class="help-layout">
        <article class="card help-article">
            @forelse ($a->blocks() as $b)
                @if ($b['kind'] === 'p')<p>{{ $b['text'] }}</p>
                @elseif ($b['kind'] === 'ol')<ol>@foreach ($b['items'] as $i)<li>{{ $i }}</li>@endforeach</ol>
                @else<ul>@foreach ($b['items'] as $i)<li>{{ $i }}</li>@endforeach</ul>@endif
            @empty
                <p class="muted">{{ __('help.no_text') }}</p>
            @endforelse
            @if ($a->link_path)<p class="mt"><a class="primary" href="{{ url($a->link_path) }}">{{ __('help.go_there') }} →</a></p>@endif
            <p class="muted mt"><small>{{ __('help.updated', ['date' => $a->updated_at->format('d/m/Y')]) }}@if ($a->updatedBy) · {{ $a->updatedBy->name }}@endif</small></p>
        </article>
        <aside class="help-side">
            @if ($related->isNotEmpty())
                <section class="card">
                    <h2>{{ __('help.related') }}</h2>
                    <ul class="plain-list">@foreach ($related as $r)<li><a href="{{ route('help.show', $r) }}">{{ $r->t('title') }}</a></li>@endforeach</ul>
                </section>
            @endif
            <section class="card">
                <h2>{{ __('help.stuck') }}</h2>
                <p class="muted">{{ __('help.stuck_hint') }}</p>
                <form method="post" action="{{ route('help.ask') }}">@csrf
                    <input type="hidden" name="page" value="{{ route('help.show', $a) }}">
                    <label class="field"><span class="sr-only">{{ __('help.question') }}</span><textarea name="question" rows="4" required minlength="5" maxlength="2000" placeholder="{{ __('help.ask_placeholder') }}"></textarea></label>
                    <button class="primary mt" type="submit">{{ __('help.ask') }}</button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.app>
