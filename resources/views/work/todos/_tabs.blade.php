{{-- My tasks: own tasks, given to others, everyone's, done, and the commercial tasks the app makes. --}}
@php($me = auth()->user())
@php($canAll = $me->hasRight('meetings.see_all'))
@php($n = [
    'mine' => \App\Models\Work\Todo::open()->where('assignee_id', $me->id)->count(),
    'given' => \App\Models\Work\Todo::open()->where('created_by_id', $me->id)->where('assignee_id', '!=', $me->id)->count(),
    'all' => $canAll ? \App\Models\Work\Todo::open()->count() : null,
    'commercial' => \App\Http\Controllers\Sales\SalesTaskController::allowed($me) ? \App\Models\Sales\SalesTask::open()->where('owner_id', $me->id)->count() : null,
])
<nav class="tabs">
    @foreach (\App\Http\Controllers\Work\TodoController::VIEWS as $v)
        @continue($v === 'all' && ! $canAll)
        <a @class(['tab', 'tab-on' => ($current ?? null) === $v]) href="{{ route('todos.index', ['v' => $v]) }}">{{ __('todos.views.'.$v) }}@isset($n[$v]) <small class="muted">{{ $n[$v] }}</small>@endisset</a>
    @endforeach
    @if ($n['commercial'] !== null)
        <a @class(['tab', 'tab-on' => ($current ?? null) === 'commercial']) href="{{ route('salestasks.index') }}">{{ __('todos.views.commercial') }} <small class="muted">{{ $n['commercial'] }}</small></a>
    @endif
</nav>
