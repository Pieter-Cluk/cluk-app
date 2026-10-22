<x-layouts.app :title="__('equipment.photos_found.title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('equipment.index') }}">{{ mb_strtoupper(__('nav.items.dryhire')) }}</a></div>
            <h1>{{ __('equipment.photos_found.title') }}</h1>
            <p class="muted">{{ __('equipment.photos_found.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    <nav class="tabs">
        @foreach (\App\Models\Equipment\PhotoSuggestion::STATUSES as $s)<a @class(['tab', 'tab-on' => $status === $s]) href="{{ route('equipment.photos.found', ['s' => $s]) }}">{{ __('equipment.photos_found.statuses.'.$s) }} <small class="muted">{{ $counts[$s] }}</small></a>@endforeach
    </nav>
    @if ($counts['new'])<p class="muted mt">{{ trans_choice('equipment.photos_found.still_fetching', $counts['new'], ['n' => $counts['new']]) }}</p>@endif

    @if ($status === 'pending' && $rows->isNotEmpty())
        <form method="post" action="{{ route('equipment.photos.found.bulk') }}" class="mt" onsubmit="return confirm(@js(__('equipment.photos_found.bulk_confirm')))">@csrf
            @foreach ($rows as $r)<input type="hidden" name="ids[]" value="{{ $r->id }}">@endforeach
            <button class="btn" type="submit">{{ __('equipment.photos_found.bulk') }}</button>
            <small class="muted">{{ __('equipment.photos_found.bulk_hint') }}</small>
        </form>
    @endif

    <div class="ps-grid mt">
        @forelse ($rows as $r)
            <section class="card ps-card">
                <div><b>{{ $r->title }}</b><br><small class="muted">{{ trans_choice('equipment.photos_found.items', count($r->item_numbers), ['n' => count($r->item_numbers)]) }} · {{ \Illuminate\Support\Str::limit(implode(', ', $r->item_numbers), 70) }}</small>
                    @if ($r->note)<br><small class="muted">{{ $r->note }}</small>@endif
                    @if ($r->page)<br><small><a href="{{ $r->page }}" target="_blank" rel="noopener noreferrer">{{ parse_url($r->page, PHP_URL_HOST) }}</a> · {{ __('equipment.photos_found.sources.'.($r->source ?: 'none')) }}</small>@endif
                </div>
                @if ($status === 'pending')
                    <div class="ps-opts">
                        @foreach ($r->usable() as $i => $f)
                            <form method="post" action="{{ route('equipment.photos.found.decide', $r) }}">@csrf<input type="hidden" name="use" value="{{ $i }}">
                                <button type="submit" class="ps-opt" title="{{ __('equipment.photos_found.use') }}"><img src="{{ route('equipment.photos.found.image', [$r, $i]) }}" alt="" loading="lazy"><span>✓ {{ __('equipment.photos_found.use') }}</span></button>
                            </form>
                        @endforeach
                    </div>
                    <form method="post" action="{{ route('equipment.photos.found.decide', $r) }}">@csrf<button class="linkbtn" type="submit">✕ {{ __('equipment.photos_found.none') }}</button></form>
                @else
                    <small class="muted">{{ __('equipment.photos_found.statuses.'.$r->status) }}@if ($r->decidedBy) · {{ $r->decidedBy->name }} · {{ $r->decided_at?->format('d/m H:i') }}@endif</small>
                    @if ($r->status === 'approved')
                        @php($it = \App\Models\Equipment\Item::where('number', $r->item_numbers[0] ?? '')->first())
                        @if ($it?->photo_path)<img class="ps-done" src="{{ route('equipment.photo', $it) }}" alt="" loading="lazy">@endif
                    @endif
                @endif
            </section>
        @empty
            <p class="muted">{{ __('equipment.photos_found.empty') }}</p>
        @endforelse
    </div>
    <x-pager :items="$rows"/>
</x-layouts.app>
