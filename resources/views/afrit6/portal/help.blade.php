{{-- Afrit 6 member help: a tile per topic; a topic opens its video with the steps written out. --}}
<x-layouts.portal tab="help" :title="__('a6help.title')">
    @if (! $topic)
        <div class="pagehead"><div><h1>{{ __('a6help.title') }}</h1><p class="muted">{{ __('a6help.intro') }}</p></div></div>
        @if (__('a6help.video_note'))<p class="muted small">{{ __('a6help.video_note') }}</p>@endif
        <div class="a6-help-grid">
            @foreach ($topics as $t)
                <a class="a6-help-card" href="{{ route('afrit6.portal.help.show', $t['key']) }}">
                    <span class="a6-help-thumb">@if ($t['poster'])<img src="{{ $t['poster'] }}" alt="" loading="lazy">@else<span class="a6-help-icon" aria-hidden="true">{{ $t['icon'] }}</span>@endif
                        @if ($t['video'])<span class="a6-help-play" aria-hidden="true">▶</span>@endif</span>
                    <strong>{{ $t['title'] }}</strong>
                    <small class="muted">{{ $t['intro'] }}</small>
                </a>
            @endforeach
        </div>
        <p class="muted mt">{{ __('a6help.ask') }}</p>
    @else
        @php($t = $topics[$topic])
        @php($keys = $topics->keys()->values())
        @php($i = $keys->search($topic))
        <div class="pagehead"><div><div class="kicker"><a href="{{ route('afrit6.portal.help') }}">← {{ __('a6help.back') }}</a></div><h1>{{ $t['title'] }}</h1><p class="muted">{{ $t['intro'] }}</p></div></div>
        <div class="a6-help-show">
            <div class="a6-help-video">
                @if ($t['video'])
                    <video controls playsinline preload="metadata" @if ($t['poster']) poster="{{ $t['poster'] }}" @endif src="{{ $t['video'] }}"></video>
                    @if (__('a6help.video_note'))<p class="muted small">{{ __('a6help.video_note') }}</p>@endif
                @else
                    <div class="a6-help-novideo"><span aria-hidden="true">{{ $t['icon'] }}</span></div>
                @endif
            </div>
            <section class="card">
                <h2>{{ __('a6help.steps_title') }}</h2>
                <ol class="a6-help-steps">@foreach ($t['steps'] as $s)<li>{{ $s }}</li>@endforeach</ol>
            </section>
        </div>
        <nav class="a6-help-nav mt">
            @if ($i > 0)<a class="btn" href="{{ route('afrit6.portal.help.show', $keys[$i - 1]) }}">← {{ $topics[$keys[$i - 1]]['title'] }}</a>@else<span></span>@endif
            @if ($i < $keys->count() - 1)<a class="btn" href="{{ route('afrit6.portal.help.show', $keys[$i + 1]) }}">{{ $topics[$keys[$i + 1]]['title'] }} →</a>@endif
        </nav>
        <p class="muted mt">{{ __('a6help.ask') }}</p>
    @endif
</x-layouts.portal>
