{{-- Incoming › a website request › what the same visitor did before sending it (read-only). --}}
<section class="card" id="before-request">
    <h2>{{ __('webstats.before.title') }}</h2>
    @if (! $before['summary'])
        <p class="muted">{{ __('webstats.before.none') }}</p>
    @else
        @php($s = $before['summary'])
        <p class="muted small">{{ trans_choice('webstats.activity.visits_n', $s['visits'], ['n' => $s['visits']]) }} · {{ __('webstats.activity.since', ['date' => $s['first']->format('d/m/Y')]) }} · {{ __('webstats.activity.source') }}: {{ implode(', ', array_keys($s['sources'])) }}@if ($s['devices']) · {{ implode(', ', array_map(fn ($d) => __('webstats.devices.'.$d), array_keys($s['devices']))) }}@endif</p>
        <ol class="web-timeline">
            @php($prev = null)
            @foreach ($before['timeline'] as $e)
                @if (! $prev || $prev->diffInMinutes($e['at']) > 30)<li class="wt-visit">{{ $e['at']->format('d/m/Y H:i') }}</li>@endif
                @php($prev = $e['at'])
                <li class="wt-{{ $e['type'] }}"><span class="muted">{{ $e['at']->format('H:i') }}</span> <b>{{ __('webstats.types.'.$e['type']) }}</b>
                    @if ($e['text'])· {{ \Illuminate\Support\Str::limit($e['text'], 80) }}@endif
                    @if ($e['type'] === 'search' && isset($e['meta']['results'])) <small class="muted">({{ trans_choice('webstats.before.results', (int) $e['meta']['results'], ['n' => (int) $e['meta']['results']]) }})</small>@endif
                    @if ($e['type'] === 'add' && isset($e['meta']['qty'])) <small class="muted">×{{ (int) $e['meta']['qty'] }}</small>@endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
