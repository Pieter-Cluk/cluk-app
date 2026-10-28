{{-- Clukie, the chat helper: only on the sales pages. --}}
@if (config('clukie.enabled'))
@php($ckWho = \App\Services\Web\CustomerAuth::current())
@php($ckClips = collect(config('clukie.clips'))->filter(fn ($p) => is_file(public_path($p)))->map(fn ($p) => asset($p).'?v='.filemtime(public_path($p)))->all())
<script type="application/json" id="clukie-cfg">@json([
  'locale' => app()->getLocale(), 'store' => 'clukie-chat', 'max' => config('clukie.max_length'), 'base' => rtrim(route('site.home'), '/'),
  'available' => \App\Services\Website\Clukie::available(), 'team_open' => \App\Services\Website\Clukie::open(), 'next' => \App\Services\Website\Clukie::nextOpenText(app()->getLocale()),
  'customer' => (bool) $ckWho, 'img' => asset('images/clukie/clukie.jpg'), 'head' => asset('images/clukie/clukie-head.jpg'), 'clips' => (object) $ckClips,
  'url' => ['message' => route('site.clukie.message'), 'poll' => route('site.clukie.poll'), 'team' => route('site.clukie.team')],
  't' => __('site.clukie'),
])</script>
@push('head')<link rel="stylesheet" href="{{ asset('css/clukie.css') }}?v={{ @filemtime(public_path('css/clukie.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/clukie.js') }}?v={{ @filemtime(public_path('js/clukie.js')) }}" defer></script>@endpush
@endif
