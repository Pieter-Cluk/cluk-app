@props(['who', 'size' => null])
@php
    $path = $who?->photo_path ?? null;
    $url = match (true) {
        ! $path => null,
        $who instanceof \App\Models\User => route('media.user', [$who, 'v' => substr(md5($path), 0, 8)]),
        $who instanceof \App\Models\Contact => route('media.contact', [$who, 'v' => substr(md5($path), 0, 8)]),
        default => null,
    };
@endphp
@if ($url)
    <img {{ $attributes->class(['av', 'av-img', 'av-'.$size => $size]) }} src="{{ $url }}" alt="" loading="lazy">
@else
    <span {{ $attributes->class(['av', 'av-'.$size => $size]) }}>{{ $who?->initials ?? '?' }}</span>
@endif
