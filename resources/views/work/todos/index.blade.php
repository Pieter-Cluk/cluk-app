<x-layouts.app :title="__('nav.items.todos')">
    <div class="pagehead">
        <div><h1>{{ __('nav.items.todos') }}</h1><p class="muted">{{ __('todos.intro') }}</p></div>
        <a class="btn" href="{{ route('meetings.create') }}">+ {{ __('meetings.new') }}</a>
    </div>
    @include('partials.flash')
    <nav class="tabs">
        @foreach (\App\Http\Controllers\Work\TodoController::VIEWS as $v)
            @continue($v === 'all' && ! $canAll)
            <a @class(['tab', 'tab-on' => $view === $v]) href="{{ route('todos.index', ['v' => $v]) }}">{{ __('todos.views.'.$v) }}@isset($counts[$v]) <small class="muted">{{ $counts[$v] }}</small>@endisset</a>
        @endforeach
    </nav>
    @if ($view === 'all')
        <form method="get" class="filters mt"><input type="hidden" name="v" value="all">
            <select name="user" onchange="this.form.submit()" aria-label="{{ __('todos.fields.assignee') }}"><option value="">{{ __('todos.everyone') }}</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($who === $u->id)>{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select>
        </form>
    @endif

    @if ($view !== 'done')
        <form method="post" action="{{ route('todos.store') }}" class="card td-add mt">@csrf
            <input name="title" required maxlength="250" placeholder="{{ __('todos.add_ph') }}" aria-label="{{ __('todos.fields.title') }}">
            <select name="assignee_id" aria-label="{{ __('todos.fields.assignee') }}">@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ $u->first_name }} {{ $u->last_name }}</option>@endforeach</select>
            <input type="date" name="due_on" aria-label="{{ __('todos.fields.due') }}">
            <button class="primary" type="submit">{{ __('todos.add') }}</button>
        </form>
    @endif

    <section class="card mt">
        @forelse ($todos as $t)
            @include('work.todos._row', ['t' => $t])
        @empty
            <p class="muted">{{ __('todos.empty.'.$view) }}</p>
        @endforelse
    </section>
    <x-pager :items="$todos"/>
</x-layouts.app>
