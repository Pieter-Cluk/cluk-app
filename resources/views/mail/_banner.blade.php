@php($bTitle = $b->text('subject', $loc))
@php($bText = $b->text('body', $loc))
@php($bLink = $b->link_url)
<table class="mail-banner" width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td>@if ($b->image_path)@if ($bLink)<a href="{{ $bLink }}" target="_blank" rel="noopener">@endif<img src="{{ URL::signedRoute('messages.public.image', $b) }}" alt="{{ $bTitle ?: $b->title }}" width="570" style="display:block;width:100%;max-width:570px;height:auto;border:0;border-radius:6px;">@if ($bLink)</a>@endif @endif @if ($bTitle)<p class="mail-banner-title">@if ($bLink)<a href="{{ $bLink }}" target="_blank" rel="noopener">{{ $bTitle }}</a>@else{{ $bTitle }}@endif</p>@endif @if ($bText)<p class="mail-banner-text">{!! nl2br(e($bText)) !!}</p>@endif</td></tr></table>
