{{-- Pictures and videos from Media › Website: one = a still picture, more = a slideshow. Each one goes to its own link. --}}
@php($secs = (int) \App\Services\Website\SiteSettings::get('general.slide_seconds', 3))
<div class="wsl {{ $class ?? '' }}" @if ($slides->count() > 1) data-wslides data-seconds="{{ $secs }}" @endif aria-roledescription="{{ $slides->count() > 1 ? 'carousel' : '' }}">
  @foreach ($slides as $k => $s)
    @php($url = \App\Services\Website\SiteSettings::link($s->link_type, $s->link_value))
    @php($src = \App\Services\Website\SiteSettings::mediaUrl($s->path))
    <{{ $url ? 'a' : 'div' }} class="wsl-s {{ $k === 0 ? 'on' : '' }}" @if ($url) href="{{ $url }}" @if (! str_starts_with($url, url('/')) && ! str_starts_with($url, '/')) target="_blank" rel="noopener" @endif @endif aria-label="{{ $s->alt ?: ($s->text('title') ?: __('site.sales.slide', ['n' => $k + 1])) }}">
      @if ($s->kind === 'video')
        <video src="{{ $src }}" @if ($s->poster_path) poster="{{ \App\Services\Website\SiteSettings::mediaUrl($s->poster_path) }}" @endif muted loop playsinline preload="metadata" @if ($k === 0) autoplay @endif></video>
      @else
        <img src="{{ $src }}" alt="{{ $s->alt }}" @if ($k > 0) loading="lazy" @endif>
      @endif
    </{{ $url ? 'a' : 'div' }}>
  @endforeach
  @if ($slides->count() > 1)
    <div class="wsl-ctrl"><div class="wsl-dots"></div><button type="button" class="wsl-pause" aria-label="{{ __('site.sales.pause') }}" data-play="{{ __('site.sales.play') }}" data-pause="{{ __('site.sales.pause') }}">❚❚</button></div>
  @endif
</div>
