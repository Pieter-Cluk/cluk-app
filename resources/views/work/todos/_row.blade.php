{{-- One task: tick it off, see where it came from, give it to someone else or another date. --}}
@php($can = $t->canBeChangedBy(auth()->user()))
<div @class(['td-row', 'td-done' => $t->status === 'done', 'td-late' => $t->isOverdue()])>
    <form method="post" action="{{ route('todos.done', $t) }}">@csrf
        <button type="submit" class="td-box" @disabled(! $can) title="{{ $t->status === 'done' ? __('todos.reopen') : __('todos.mark_done') }}" aria-label="{{ $t->status === 'done' ? __('todos.reopen') : __('todos.mark_done') }}: {{ $t->title }}">{{ $t->status === 'done' ? '✓' : '' }}</button>
    </form>
    <div class="td-main">
        <b>{{ $t->title }}</b>
        @if ($t->details)<div class="small">{{ $t->details }}</div>@endif
        <small class="muted">
            {{ $t->assignee?->name }}
            @if ($t->due_on) · <span @class(['txt-warn' => $t->isOverdue()])>{{ $t->isOverdue() ? __('todos.late_since', ['date' => $t->due_on->format('d/m')]) : __('todos.due', ['date' => $t->due_on->format('d/m/Y')]) }}</span>@endif
            @if (($showMeeting ?? true) && $t->meeting) · <a href="{{ route('meetings.show', $t->meeting) }}">{{ $t->meeting->title }}</a>@endif
            @if ($t->company && ($showMeeting ?? true)) · <a href="{{ route('companies.show', $t->company) }}">{{ $t->company->name }}</a>@endif
            @if ($t->created_by_id && $t->created_by_id !== $t->assignee_id) · {{ __('todos.from', ['name' => $t->createdBy?->first_name]) }}@endif
            @if ($t->status === 'done' && $t->done_at) · {{ __('todos.done_on', ['date' => $t->done_at->timezone('Europe/Brussels')->format('d/m H:i')]) }}@endif
        </small>
    </div>
    @if ($can && $t->status === 'open')
        <details class="td-edit">
            <summary class="linkbtn">{{ __('todos.change') }}</summary>
            <form method="post" action="{{ route('todos.update', $t) }}" class="inline-form">@csrf @method('PUT')
                <select name="assignee_id" aria-label="{{ __('todos.fields.assignee') }}">@foreach ($staff ?? \App\Models\User::where('status', \App\Models\User::STATUS_ACTIVE)->orderBy('first_name')->get() as $u)<option value="{{ $u->id }}" @selected($u->id === $t->assignee_id)>{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select>
                <input type="date" name="due_on" value="{{ $t->due_on?->toDateString() }}" aria-label="{{ __('todos.fields.due') }}">
                <button class="btn btn-sm" type="submit">{{ __('todos.save') }}</button>
            </form>
        </details>
    @endif
</div>
