{{-- Crew tasks: who does what and when. --}}
@php($me = auth()->user())
@php($when = fn ($d) => $d->locale(app()->getLocale())->translatedFormat('D j M · H:i'))
<section class="card card-flush">
    <div class="card-head pad"><h2>{{ __('factory.task.title') }}</h2><span class="muted small">{{ __('factory.task.count', ['done' => $b->tasks->whereNotNull('done_at')->count(), 'all' => $b->tasks->count()]) }}</span></div>
    <ul class="ftasks">
        @forelse ($b->tasks as $t)
            <li @class(['ftask', 'ftask-done' => $t->isDone(), 'ftask-late' => $t->isOverdue()])>
                <span class="ftask-mark" aria-hidden="true">{{ $t->isDone() ? '✓' : '' }}</span>
                <span class="ftask-main">
                    <b>{{ $t->label() }}</b>
                    <small class="muted">{{ $when($t->due_at) }}@if ($t->isOverdue()) · <span class="txt-danger">{{ __('factory.task.late') }}</span>@endif
                        @if ($t->isDone()) · {{ __('factory.task.done_by', ['name' => $t->doneBy?->name ?? '—', 'when' => $t->done_at->locale(app()->getLocale())->translatedFormat('j M H:i')]) }}@endif
                        @if ($t->kind === 'gear' && $b->gearQuote?->order) · <a href="{{ route('orders.show', $b->gearQuote->order) }}">{{ $b->gearQuote->order->number }}</a>@endif
                    </small>
                    @if ($t->note)<small>{{ $t->note }}</small>@endif
                </span>
                <span class="ftask-who">
                    @if ($t->assignee)<x-avatar :who="$t->assignee" size="sm" /> {{ $t->assignee->name }}@else<span class="muted">{{ __('factory.task.nobody') }}</span>@endif
                </span>
                <span class="ftask-acts">
                    @if (! $t->isDone())
                        @if ($t->assignee_id === $me->id)
                            <form method="post" action="{{ route('factory.tasks.done', $t) }}">@csrf<button class="primary btn-sm" type="submit">{{ __('factory.task.mark_done') }}</button></form>
                        @elseif ($canWork && $t->canBeMovedBy($me))
                            <form method="post" action="{{ route('factory.tasks.take', $t) }}">@csrf<button class="btn btn-sm" type="submit">{{ __('workspace.tasks.take') }}</button></form>
                        @endif
                        @if ($t->canBeMovedBy($me) && $crew->where('id', '!=', $t->assignee_id)->isNotEmpty())
                            <details class="ftask-hand"><summary class="btn btn-sm">{{ __('factory.task.hand') }}</summary>
                                <form method="post" action="{{ route('factory.tasks.reassign', $t) }}" class="inline-form mt">@csrf
                                    <select name="assignee_id" aria-label="{{ __('factory.task.hand') }}">@foreach ($crew->where('id', '!=', $t->assignee_id) as $u)<option value="{{ $u->id }}">{{ $u->name }}@if ($u->title) · {{ $u->title }}@endif</option>@endforeach</select>
                                    <button class="btn btn-sm" type="submit">{{ __('factory.task.hand_go') }}</button>
                                </form>
                            </details>
                        @endif
                        @if ($canWork && $t->kind === null)
                            <form method="post" action="{{ route('factory.tasks.destroy', $t) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost" type="submit" aria-label="{{ __('factory.task.remove') }}" title="{{ __('factory.task.remove') }}">×</button></form>
                        @endif
                    @elseif ($t->canBeMovedBy($me))
                        <form method="post" action="{{ route('factory.tasks.reopen', $t) }}">@csrf<button class="btn btn-sm" type="submit">{{ __('factory.task.reopen') }}</button></form>
                    @endif
                </span>
            </li>
        @empty
            <li class="muted inbox-empty">{{ __('factory.task.none') }}</li>
        @endforelse
    </ul>
    @if ($canWork)
        <details class="ftask-foot">
            <summary>{{ __('factory.task.add') }}</summary>
            <form method="post" action="{{ route('factory.tasks.store', $b) }}" class="form-grid mt">@csrf
                <label class="field span-2"><span>{{ __('factory.task.what') }}</span><input name="title" required maxlength="200"></label>
                <label class="field"><span>{{ __('factory.task.date') }}</span><input type="date" name="due_date" required value="{{ $b->firstDay()->toDateString() }}"></label>
                <label class="field"><span>{{ __('factory.task.time') }}</span><input type="time" name="due_time" required value="09:00"></label>
                <label class="field span-2"><span>{{ __('factory.task.who') }}</span><select name="assignee_id"><option value="">{{ __('factory.task.nobody') }}</option>@foreach ($crew as $u)<option value="{{ $u->id }}">{{ $u->name }}@if ($u->title) · {{ $u->title }}@endif</option>@endforeach</select></label>
                <div class="span-2"><button class="primary" type="submit">{{ __('factory.task.add_go') }}</button></div>
            </form>
        </details>
    @endif
    <p class="muted small ftask-foot">{{ __('factory.task.rule') }}</p>
</section>
