{{-- Clukie for the team (same mascot and chat window as on the website, with its own answers and tools). --}}
@if (auth()->check() && config('clukie.enabled') && \App\Services\Assistant\StaffClukie::available())
@php
    $ckClips = collect(config('clukie.clips'))->filter(fn ($p) => is_file(public_path($p)))->map(fn ($p) => asset($p).'?v='.filemtime(public_path($p)))->all();
    $ckCfg = [
        'locale' => app()->getLocale(), 'store' => 'clukie-staff-'.auth()->id(), 'max' => 2000, 'base' => rtrim(url('/'), '/'),
        'available' => true, 'team_open' => true, 'next' => '', 'customer' => true, 'team' => false,
        'img' => asset('images/clukie/clukie.jpg'), 'head' => asset('images/clukie/clukie-head.jpg'), 'clips' => (object) $ckClips,
        'url' => ['message' => route('assistant.message'), 'poll' => route('assistant.poll'), 'team' => route('assistant.message')],
        't' => array_merge((array) __('site.clukie'), (array) __('assistant.t')),
    ];
@endphp
<script type="application/json" id="clukie-cfg">{!! json_encode($ckCfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<link rel="stylesheet" href="{{ asset('css/clukie.css') }}?v={{ @filemtime(public_path('css/clukie.css')) }}">
<script src="{{ asset('js/clukie.js') }}?v={{ @filemtime(public_path('js/clukie.js')) }}" defer></script>
@endif
