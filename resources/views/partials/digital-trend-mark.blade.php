{{-- One post marker: shape per platform (circle Facebook, square Instagram, diamond LinkedIn), with a surface ring. --}}
@php($x = round($x, 1)) @php($y = round($y, 1)) @php($r = round($r, 1))
@if ($p['shape'] === 'square')
    <rect class="dt-mark" x="{{ $x - $r * 0.9 }}" y="{{ $y - $r * 0.9 }}" width="{{ $r * 1.8 }}" height="{{ $r * 1.8 }}" rx="2" style="fill: {{ $p['color'] }}" />
@elseif ($p['shape'] === 'diamond')
    <path class="dt-mark" d="M{{ $x }} {{ $y - $r * 1.15 }}L{{ $x + $r * 1.15 }} {{ $y }}L{{ $x }} {{ $y + $r * 1.15 }}L{{ $x - $r * 1.15 }} {{ $y }}Z" style="fill: {{ $p['color'] }}" />
@else
    <circle class="dt-mark" cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}" style="fill: {{ $p['color'] }}" />
@endif
