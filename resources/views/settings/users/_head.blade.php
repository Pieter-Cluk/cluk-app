{{-- Page title and the three tabs of Settings › Users. --}}
<div class="pagehead">
    <div>
        <div class="kicker">{{ mb_strtoupper(__('users.kicker')) }}</div>
        <h1>{{ $heading }}</h1>
        @isset($intro)<p class="muted">{{ $intro }}</p>@endisset
    </div>
    @isset($action){!! $action !!}@endisset
</div>
<nav class="tabs" aria-label="{{ __('users.index.title') }}">
    <a href="{{ route('settings.users.index') }}" @class(['tab', 'tab-on' => $tab === 'people']) @if ($tab === 'people') aria-current="page" @endif>{{ __('users.tabs.people') }}</a>
    <a href="{{ route('settings.roles.index') }}" @class(['tab', 'tab-on' => $tab === 'roles']) @if ($tab === 'roles') aria-current="page" @endif>{{ __('users.tabs.roles') }}</a>
    <a href="{{ route('settings.audit.index') }}" @class(['tab', 'tab-on' => $tab === 'activity']) @if ($tab === 'activity') aria-current="page" @endif>{{ __('users.tabs.activity') }}</a>
</nav>
@if (session('status'))
    <div class="flash flash-ok" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="flash flash-err" role="alert">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
