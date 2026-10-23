{{-- Shared bits for the account forms. --}}
@if (! empty($notice))<p class="acc-ok" role="status">{{ $notice }}</p>@endif
@if ($errors->any())<div class="acc-err" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
