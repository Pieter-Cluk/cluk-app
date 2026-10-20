@props(['items'])
@if ($items->hasPages())
    <nav class="pager" aria-label="pages">
        @if ($items->onFirstPage())<span class="btn is-off">{{ __('users.previous') }}</span>@else<a class="btn" href="{{ $items->previousPageUrl() }}" rel="prev">{{ __('users.previous') }}</a>@endif
        <span class="muted">{{ $items->currentPage() }} / {{ $items->lastPage() }}</span>
        @if ($items->hasMorePages())<a class="btn" href="{{ $items->nextPageUrl() }}" rel="next">{{ __('users.next') }}</a>@else<span class="btn is-off">{{ __('users.next') }}</span>@endif
    </nav>
@endif
