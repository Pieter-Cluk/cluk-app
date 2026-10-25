<x-layouts.app :title="$m->title">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('meetings.index') }}">{{ mb_strtoupper(__('nav.items.meetings')) }}</a> · {{ $m->held_on->format('d/m/Y') }}</div>
            <h1>{{ $m->title }}</h1>
            <p class="muted">{{ $m->kind === 'customer' ? __('meetings.with_name', ['name' => $m->company?->name ?? '—']) : __('meetings.kinds.internal') }}@if ($m->company) · <a href="{{ route('companies.show', $m->company) }}">{{ __('meetings.open_company') }}</a>@endif · {{ $m->attendeeUsers()->map(fn ($u) => $u->first_name)->implode(', ') ?: '—' }}</p>
        </div>
    </div>
    @include('partials.flash')

    @if (is_array($m->proposals) && $canEdit)
        <section class="card mt-review" id="review">
            <div class="card-head">
                <h2>{{ __('meetings.review.title') }}</h2>
                <span class="muted small">{{ $m->analysed_by === 'claude' ? __('meetings.review.by_claude') : __('meetings.review.by_rules') }}</span>
            </div>
            @if ($m->proposals === [])
                <p class="muted">{{ $claude ? __('meetings.review.none') : __('meetings.review.none_rules') }}</p>
            @else
                <p class="muted small">{{ __('meetings.review.intro') }}</p>
            @endif
            <form method="post" action="{{ route('meetings.tasks', $m) }}" class="stack-sm">@csrf
                <div class="mt-props">
                    @foreach ($m->proposals as $i => $p)
                        <div @class(['mt-prop', 'mt-prop-customer' => $p['for_customer'] ?? false])>
                            <input type="hidden" name="tasks[{{ $i }}][take]" value="0">
                            <label class="mt-take"><input type="checkbox" name="tasks[{{ $i }}][take]" value="1" checked aria-label="{{ __('meetings.review.take') }}"></label>
                            <div class="mt-prop-main">
                                <input name="tasks[{{ $i }}][title]" value="{{ $p['title'] }}" maxlength="250" aria-label="{{ __('todos.fields.title') }}">
                                <input name="tasks[{{ $i }}][details]" value="{{ $p['details'] }}" maxlength="1000" placeholder="{{ __('todos.fields.details') }}" aria-label="{{ __('todos.fields.details') }}" class="mt-details">
                                <small class="muted">@if ($p['for_customer'] ?? false)<b>{{ __('meetings.review.customer') }}</b> · @endif @if ($p['who']){{ __('meetings.review.said', ['who' => $p['who']]) }}@endif @if ($p['guessed'] ?? false) <span class="txt-warn">{{ __('meetings.review.guessed') }}</span>@endif</small>
                            </div>
                            <label class="field"><span>{{ __('todos.fields.assignee') }}</span><select name="tasks[{{ $i }}][user_id]">@foreach ($staff as $u)<option value="{{ $u->id }}" @selected((int) $p['user_id'] === $u->id)>{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select></label>
                            <label class="field"><span>{{ __('todos.fields.due') }}</span><input type="date" name="tasks[{{ $i }}][due]" value="{{ $p['due'] }}"></label>
                        </div>
                    @endforeach
                    @php($n = count($m->proposals))
                    <details class="mt-prop mt-prop-new" @if ($n === 0) open @endif>
                        <summary>+ {{ __('meetings.review.add') }}</summary>
                        <div class="mt-prop-grid">
                            <input type="hidden" name="tasks[{{ $n }}][take]" value="0">
                            <label class="mt-take"><input type="checkbox" name="tasks[{{ $n }}][take]" value="1" aria-label="{{ __('meetings.review.take') }}"></label>
                            <div class="mt-prop-main"><input name="tasks[{{ $n }}][title]" maxlength="250" placeholder="{{ __('todos.fields.title') }}" aria-label="{{ __('todos.fields.title') }}"></div>
                            <label class="field"><span>{{ __('todos.fields.assignee') }}</span><select name="tasks[{{ $n }}][user_id]">@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select></label>
                            <label class="field"><span>{{ __('todos.fields.due') }}</span><input type="date" name="tasks[{{ $n }}][due]"></label>
                        </div>
                    </details>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit" form="dismiss">{{ __('meetings.review.dismiss') }}</button>
                    <button class="primary" type="submit">{{ __('meetings.review.make') }}</button>
                </div>
            </form>
            <form method="post" action="{{ route('meetings.dismiss', $m) }}" id="dismiss">@csrf</form>
        </section>
    @endif

    <div class="split">
        <div class="stack">
            @if ($m->summary || $m->decisions)
                <section class="card">
                    <h2>{{ __('meetings.summary') }}</h2>
                    @if ($m->summary)<p class="prewrap">{{ $m->summary }}</p>@endif
                    @if ($m->decisions)
                        <h3 class="mt">{{ __('meetings.decisions') }}</h3>
                        <ul class="mt-list">@foreach ($m->decisions as $d)<li>{{ $d }}</li>@endforeach</ul>
                    @endif
                </section>
            @endif
            <section class="card">
                <h2>{{ __('meetings.tasks') }}</h2>
                @forelse ($m->todos as $t)
                    @include('work.todos._row', ['t' => $t, 'showMeeting' => false])
                @empty
                    <p class="muted">{{ __('meetings.no_tasks') }}</p>
                @endforelse
            </section>
            <details class="card" @if ($errors->any()) open @endif>
                <summary><b>{{ $canEdit ? __('meetings.edit') : __('meetings.fields.notes') }}</b></summary>
                @if ($canEdit)
                    <form method="post" action="{{ route('meetings.update', $m) }}" class="mt">@csrf @method('PUT')
                        @include('work.meetings._fields')
                        <div class="form-actions">
                            <label class="check"><input type="checkbox" name="again" value="1"> {{ __('meetings.read_again') }}</label>
                            <button class="primary" type="submit">{{ __('meetings.save') }}</button>
                        </div>
                    </form>
                @else
                    <p class="prewrap mt">{{ $m->notes }}</p>
                @endif
            </details>
        </div>
        <aside class="stack">
            <section class="card">
                <dl class="facts">
                    <dt>{{ __('meetings.fields.held_on') }}</dt><dd>{{ $m->held_on->format('d/m/Y') }}</dd>
                    <dt>{{ __('meetings.fields.kind') }}</dt><dd>{{ __('meetings.kinds.'.$m->kind) }}</dd>
                    @if ($m->company)<dt>{{ __('meetings.fields.company') }}</dt><dd><a href="{{ route('companies.show', $m->company) }}">{{ $m->company->name }}</a></dd>@endif
                    <dt>{{ __('meetings.written_by') }}</dt><dd>{{ $m->createdBy?->name ?? '—' }}</dd>
                    <dt>{{ __('meetings.fields.attendees') }}</dt><dd>{{ $m->attendeeUsers()->map->name->implode(', ') ?: '—' }}</dd>
                </dl>
                @if ($m->created_by_id === auth()->id() || auth()->user()->hasRight('meetings.see_all'))
                    <form method="post" action="{{ route('meetings.destroy', $m) }}" onsubmit="return confirm(@js(__('meetings.delete_confirm')))">@csrf @method('DELETE')<button class="linkbtn" type="submit">{{ __('meetings.delete') }}</button></form>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.app>
