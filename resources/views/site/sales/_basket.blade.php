{{-- Basket drawer and "added" pop-up (filled by site-sales.js), plus the page settings. --}}
<div class="scrim" id="scrim"></div>
<div class="drawer" id="bk" role="dialog" aria-modal="true" aria-labelledby="bkTitle"></div>
<div class="added" id="added" role="dialog" aria-modal="true" aria-labelledby="addedTitle" hidden></div>
<script type="application/json" id="s-cfg">@json($cfg)</script>
@push('scripts')
<script src="{{ asset('js/site-sales.js') }}?v={{ @filemtime(public_path('js/site-sales.js')) }}" defer></script>
@endpush
