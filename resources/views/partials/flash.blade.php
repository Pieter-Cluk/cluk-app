@if (session('status'))
    <div class="flash flash-ok" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="flash flash-err" role="alert">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
