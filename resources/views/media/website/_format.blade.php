{{-- The ideal picture for a spot: size, safe zone, a drawing of what each screen shows, and a template to design on. --}}
@php($f = \App\Models\Website\Slide::FORMATS[$format])
@php([$sw, $sh] = \App\Models\Website\Slide::safe($format))
<details class="ws-format" @if ($open ?? false) open @endif>
    <summary><b>{{ __('website.format.title') }}:</b>&nbsp;{{ $f['w'] }} × {{ $f['h'] }} px ({{ \App\Models\Website\Slide::ratioText($format) }})</summary>
    <div class="ws-format-body">
        <div class="ws-format-pic" style="aspect-ratio:{{ $f['w'] }}/{{ $f['h'] }}">
            @if ($f['note'] === 'dark_left')<span class="ws-format-dark"></span>@endif
            @if ($f['note'] === 'text_left')<span class="ws-format-text">{{ __('website.format.text_zone') }}</span>@endif
            <span class="ws-format-safe" style="width:{{ round($sw / $f['w'] * 100, 1) }}%;height:{{ round($sh / $f['h'] * 100, 1) }}%"><small>{{ __('website.format.safe_label') }}</small></span>
            @foreach ($f['frames'] as $name => $r)
                @php([$cw, $ch] = \App\Models\Website\Slide::crop($format, $r))
                <span class="ws-format-frame ws-fr-{{ $loop->index }}" style="width:{{ round($cw * 100, 1) }}%;height:{{ round($ch * 100, 1) }}%"><small>{{ __('website.format.screens.'.$name) }}</small></span>
            @endforeach
        </div>
        <ul class="ws-format-list small">
            <li><b>{{ __('website.format.size') }}:</b> {{ $f['w'] }} × {{ $f['h'] }} px · {{ \App\Models\Website\Slide::ratioText($format) }} · {{ __('website.format.file') }}</li>
            <li><b>{{ __('website.format.safe') }}:</b> {{ __('website.format.safe_text', ['w' => $sw, 'h' => $sh]) }}</li>
            <li>{{ __('website.format.notes.'.$f['note']) }}</li>
            @if ($format !== 'tile')<li><b>{{ __('website.format.video') }}:</b> {{ __('website.format.video_text', ['w' => min(1920, $f['w']), 'h' => (int) round(min(1920, $f['w']) * $f['h'] / $f['w'] / 2) * 2]) }}</li>@endif
            <li><a href="{{ route('website.template', $format) }}">{{ __('website.format.template') }}</a> <span class="muted">{{ __('website.format.template_hint') }}</span></li>
        </ul>
    </div>
</details>
