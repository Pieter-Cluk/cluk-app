{{-- Website activity (read-only), for a contact or company card. $act = WebActivity::summary(...) or null. --}}
<section class="card" id="web-activity">
    <div class="card-head"><h2>{{ __('webstats.activity.title') }}</h2>@if ($act)<span class="muted small">{{ __('webstats.activity.kept') }}</span>@endif</div>
    @if (! $act)
        <p class="muted">{{ __('webstats.activity.none') }}</p>
    @else
        <dl class="facts">
            <dt>{{ __('webstats.activity.last') }}</dt><dd>{{ $act['last']->format('d/m/Y H:i') }} <small class="muted">({{ $act['last']->diffForHumans() }})</small></dd>
            <dt>{{ __('webstats.activity.visits') }}</dt><dd>{{ trans_choice('webstats.activity.visits_n', $act['visits'], ['n' => $act['visits']]) }} · {{ __('webstats.activity.since', ['date' => $act['first']->format('d/m/Y')]) }}@if ($act['requests']) · {{ trans_choice('webstats.activity.requests_n', $act['requests'], ['n' => $act['requests']]) }}@endif</dd>
            <dt>{{ __('webstats.activity.source') }}</dt><dd>{{ implode(', ', array_keys($act['sources'])) }}</dd>
            @if ($act['searches'])<dt>{{ __('webstats.activity.searched') }}</dt><dd>@foreach ($act['searches'] as $w => $n)<span class="chip-attr">{{ $w }}@if ($n > 1) ×{{ $n }}@endif</span> @endforeach</dd>@endif
            @if ($act['categories'])<dt>{{ __('webstats.activity.categories') }}</dt><dd>{{ collect($act['categories'])->map(fn ($n, $c) => $c.' ('.$n.')')->implode(', ') }}</dd>@endif
        </dl>
        @if ($act['products'])
            <h3 class="sc-h">{{ __('webstats.activity.products') }}</h3>
            <ul class="plain-list">@foreach ($act['products'] as $p)<li>{{ $p['name'] }} <small class="muted">· {{ __('webstats.sections.'.$p['section']) }} · ×{{ $p['n'] }}</small></li>@endforeach</ul>
        @endif
    @endif
</section>
