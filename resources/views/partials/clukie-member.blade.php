{{-- Clukie for Afrit 6 members (same mascot and chat window as on the website; Afrit 6 answers only, prepares forms, never books). --}}
@if (config('clukie.enabled') && \App\Services\Afrit6\MemberClukie::available())
@php
    $ckClips = collect(config('clukie.clips'))->filter(fn ($p) => is_file(public_path($p)))->map(fn ($p) => asset($p).'?v='.filemtime(public_path($p)))->all();
    $ckKey = session('clukie_member_key') ?: tap(strtolower(\Illuminate\Support\Str::random(10)), fn ($k) => session(['clukie_member_key' => $k]));
    $ckCfg = [
        'locale' => app()->getLocale(), 'store' => 'clukie-member-'.$member->id.'-'.$ckKey, 'prefix' => 'clukie-member-'.$member->id, 'max' => 1500, 'base' => rtrim(url('/'), '/'),
        'available' => true, 'team_open' => true, 'next' => '', 'customer' => true, 'team' => false, 'same_tab' => true,
        'img' => asset('images/clukie/clukie.jpg'), 'head' => asset('images/clukie/clukie-head.jpg'), 'clips' => (object) $ckClips,
        'url' => ['message' => route('afrit6.portal.clukie'), 'poll' => route('afrit6.portal.clukie.poll'), 'team' => route('afrit6.portal.clukie')],
        't' => array_merge((array) __('site.clukie'), (array) __('a6clukie.t')),
    ];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script type="application/json" id="clukie-cfg">{!! json_encode($ckCfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<link rel="stylesheet" href="{{ asset('css/clukie.css') }}?v={{ @filemtime(public_path('css/clukie.css')) }}">
<script src="{{ asset('js/clukie.js') }}?v={{ @filemtime(public_path('js/clukie.js')) }}" defer></script>
@endif
