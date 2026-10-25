{{-- How this picture is cut on each kind of screen. --}}
@php($f = \App\Models\Website\Slide::FORMATS[$format])
<div class="ws-crops" aria-label="{{ __('website.format.preview') }}">
    @foreach ($f['frames'] as $name => $r)
        <figure><div class="ws-crop" style="aspect-ratio:{{ $r }}">@if ($video ?? false)<video src="{{ $src }}" @if ($poster ?? null) poster="{{ $poster }}" @endif muted playsinline preload="metadata"></video>@else<img src="{{ $src }}" alt="" loading="lazy">@endif</div><figcaption>{{ __('website.format.screens.'.$name) }}</figcaption></figure>
    @endforeach
</div>
