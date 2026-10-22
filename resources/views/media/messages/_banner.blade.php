{{-- The promotion / seasonal banner on quotes, in the quote's language. --}}
<section class="doc-banner">
    @if ($b->image_path)<img src="{{ URL::signedRoute('messages.public.image', $b) }}" alt="">@endif
    @if ($t = $b->text('subject', $loc))<b>{{ $t }}</b>@endif
    @if ($t = $b->text('body', $loc))<p>{!! nl2br(e($t)) !!}</p>@endif
</section>
