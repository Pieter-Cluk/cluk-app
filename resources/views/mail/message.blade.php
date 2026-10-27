<x-mail::message>
@if ($imageUrl)
<img src="{{ $imageUrl }}" alt="" style="display:block;width:100%;max-width:570px;height:auto;border-radius:8px;margin:0 0 20px">
@endif

{!! nl2br(e($body)) !!}

@if ($button ?? null)
<x-mail::button :url="$button[1]">
{{ $button[0] }}
</x-mail::button>
@endif

@if ($pdfLink)
<x-mail::button :url="$pdfLink[1]">
{{ $pdfLink[0] }}
</x-mail::button>
@endif
<div class="signature"><p class="who">{{ __('mails.team') }}</p><p class="motto">Cluk it, why not!</p></div>
@if ($unsubscribeUrl)
<x-slot:subcopy>
{{ __('messages.unsubscribe_line') }} [{{ __('messages.unsubscribe') }}]({{ $unsubscribeUrl }})
</x-slot:subcopy>
@endif
</x-mail::message>
