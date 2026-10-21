@props(['company', 'size' => null, 'rel' => null])
@if ($company->logo_path)
    <img {{ $attributes->class(['logo-img', 'logo-'.$size => $size]) }} src="{{ route('media.company', [$company, 'v' => substr(md5($company->logo_path), 0, 8)]) }}" alt="{{ $company->name }}" loading="lazy">
@else
    <span {{ $attributes->class(['logo-ini', 'logo-'.$size => $size, 'rel-'.$rel => $rel]) }}>{{ $company->initials() }}</span>
@endif
