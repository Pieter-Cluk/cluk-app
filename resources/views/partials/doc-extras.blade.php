{{-- The commercial note of this document type and the seasonal message, in the document's language. --}}
@php($loc = app()->getLocale())
@php($dnote = \App\Models\DocumentSetting::note($type, $loc))
@php($dseason = \App\Models\DocumentSetting::season($loc))
@if ($dnote)
    <section class="doc-cnote"><b>{{ __('documents.note_title') }}</b><p>{!! nl2br(e($dnote)) !!}</p></section>
@endif
@if ($dseason)
    <p class="doc-season">{{ $dseason }}</p>
@endif
@if (in_array($type, ['R-QUO', 'S-QUO'], true) && ($dbanner = \App\Models\Media\Message::inUse('banner')))
    @include('media.messages._banner', ['b' => $dbanner, 'loc' => $loc])
@endif
