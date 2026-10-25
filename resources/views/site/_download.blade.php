{{-- One document on the Downloads page. --}}
<li>
  <span class="dl-type">{{ $d->type() }}</span>
  <span class="dl-main"><b>{{ $d->label(null, 'title') }}</b>
    @if ($d->label(null, 'description'))<span class="dl-desc">{{ $d->label(null, 'description') }}</span>@endif
    <small>{{ $d->locale ? __('site.downloads.langs.'.$d->locale) : __('site.downloads.any_language') }} · {{ $d->sizeLabel() }}@if ($d->access === 'login') · {{ __('site.downloads.for_customers') }}@endif · {{ $d->updated_at?->locale(app()->getLocale())->translatedFormat('j M Y') }}</small></span>
  <a class="dl-btn" href="{{ route('site.downloads.file', $d->id) }}" @if ($d->type() === 'PDF' && $d->access === 'open') target="_blank" rel="noopener" @endif>{{ __('site.downloads.download') }}</a>
</li>
