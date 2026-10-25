@php($W = $f['w'])
@php($H = $f['h'])
@php($fs = max(18, round($W / 70)))
@php($colors = ['#e53935', '#1e88e5', '#fb8c00', '#8e24aa'])
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{{ $W }}" height="{{ $H }}" viewBox="0 0 {{ $W }} {{ $H }}">
  <title>CLUK · {{ __('website.places.'.$format) }} · {{ $W }} × {{ $H }} px</title>
  <rect width="{{ $W }}" height="{{ $H }}" fill="#d9ddd6"/>
  @if ($f['note'] === 'dark_left')<defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#070807" stop-opacity=".95"/><stop offset=".35" stop-color="#070807" stop-opacity=".6"/><stop offset="1" stop-color="#070807" stop-opacity=".1"/></linearGradient></defs><rect width="{{ $W }}" height="{{ $H }}" fill="url(#g)"/>@endif
  @if ($f['note'] === 'text_left')<rect x="0" y="{{ round($H * .35) }}" width="{{ round($W * .5) }}" height="{{ round($H * .65) }}" fill="#0a0a0a" fill-opacity=".25"/><text x="{{ round($W * .03) }}" y="{{ round($H * .93) }}" font-family="Arial" font-size="{{ $fs }}" fill="#222">{{ __('website.format.text_zone') }}</text>@endif
  <rect x="{{ round(($W - $sw) / 2) }}" y="{{ round(($H - $sh) / 2) }}" width="{{ $sw }}" height="{{ $sh }}" fill="#52ae32" fill-opacity=".22" stroke="#52ae32" stroke-width="{{ max(3, round($W / 600)) }}"/>
  <text x="{{ round(($W - $sw) / 2) + 14 }}" y="{{ round(($H - $sh) / 2) + $fs + 10 }}" font-family="Arial" font-weight="bold" font-size="{{ $fs }}" fill="#2e6b1a">{{ __('website.format.safe_label') }} {{ $sw }} × {{ $sh }}</text>
  @foreach ($frames->values() as $i => $fr)
    @php($cw = round($W * $fr['crop'][0]))
    @php($ch = round($H * $fr['crop'][1]))
    <rect x="{{ round(($W - $cw) / 2) }}" y="{{ round(($H - $ch) / 2) }}" width="{{ $cw }}" height="{{ $ch }}" fill="none" stroke="{{ $colors[$i % 4] }}" stroke-width="{{ max(3, round($W / 800)) }}" stroke-dasharray="{{ $fs }} {{ round($fs / 2) }}"/>
    <text x="{{ round(($W - $cw) / 2) + 14 }}" y="{{ round(($H + $ch) / 2) - 14 - $i * ($fs + 6) }}" font-family="Arial" font-size="{{ $fs }}" fill="{{ $colors[$i % 4] }}">{{ $fr['name'] }}</text>
  @endforeach
</svg>
