{{-- The agenda next to the slideshow on the Afrit 6 entrance TV (polled every minute). --}}
@if ($guests->isNotEmpty())
    <section class="en-block">
        <h2>{{ __('wall.entrance.welcome') }}</h2>
        <ul class="en-guests">
            @foreach ($guests as $g)
                <li @class(['en-past' => $g['past']])>
                    @if ($g['time'])<time>{{ $g['time'] }}</time>@endif
                    <div>
                        @if ($g['name'])<b>{{ $g['name'] }}</b>@if ($g['company'])<span>{{ $g['company'] }}</span>@endif
                        @else<b>{{ __('wall.entrance.guest_anon') }}</b>@endif
                        <small>{{ __('wall.entrance.guest_of', ['host' => $g['host']]) }}</small>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@else
    <section class="en-block en-hello">
        <h2>{{ __('wall.entrance.welcome_to') }}</h2>
        <p>{{ __('wall.entrance.hello') }}</p>
    </section>
@endif

@if ($notices->isNotEmpty())
    <section class="en-block">
        <h3>{{ __('wall.entrance.today') }}</h3>
        @foreach ($notices as $n)
            <div @class(['en-room', 'en-notice', 'en-past' => $n['past']])>@if ($n['time'])<time>{{ $n['time'] }}</time>@endif<span>{{ $n['text'] }}</span></div>
        @endforeach
    </section>
@endif

<section class="en-block">
    <h3>{{ __('wall.entrance.room') }}</h3>
    @forelse ($rooms->reject(fn ($r) => $r['done']) as $r)
        <div @class(['en-room', 'en-now' => $r['now']])>
            <time>{{ $r['time'] }}</time>
            <span>{{ $r['who'] }}</span>
            @if ($r['now'])<em>{{ __('wall.entrance.now') }}</em>@endif
        </div>
    @empty
        <p class="en-muted">{{ $rooms->isEmpty() ? __('wall.entrance.room_free') : __('wall.entrance.room_done') }}</p>
    @endforelse
</section>
