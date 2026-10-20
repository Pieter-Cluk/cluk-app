@if (session('status'))<div class="member-ok" role="status">{{ session('status') }}</div>@endif
@if ($errors->any())
    <div class="login-error" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
