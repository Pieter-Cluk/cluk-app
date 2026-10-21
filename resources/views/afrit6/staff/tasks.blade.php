<x-layouts.app :title="__('workspace.tasks.title')">
    @include('afrit6.staff._head', ['section' => 'tasks'])
    <p class="muted">{{ __('workspace.tasks.intro') }}</p>

    <div class="filters">
        <nav class="chips">
            @foreach (\App\Http\Controllers\Afrit6\TaskController::FILTERS as $f)
                <a href="{{ route('afrit6.staff.tasks', array_filter(['status' => $f === 'open' ? null : $f, 'who' => $who === 'mine' ? 'mine' : null])) }}" @class(['chip', 'chip-on' => $filter === $f])>{{ __('workspace.tasks.filters.'.$f) }} <span class="count">{{ $counts[$f] }}</span></a>
            @endforeach
        </nav>
        <nav class="chips">
            @foreach (['all', 'mine'] as $w)
                <a href="{{ route('afrit6.staff.tasks', array_filter(['status' => $filter === 'open' ? null : $filter, 'who' => $w === 'mine' ? 'mine' : null])) }}" @class(['chip', 'chip-on' => $who === $w])>{{ __('workspace.tasks.who.'.$w) }}</a>
            @endforeach
        </nav>
    </div>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('workspace.tasks.cols.task') }}</th><th>{{ __('workspace.tasks.cols.member') }}</th><th>{{ __('workspace.tasks.cols.when') }}</th><th>{{ __('workspace.tasks.cols.status') }}</th><th>{{ __('workspace.tasks.cols.assignee') }}</th></tr></thead>
                <tbody>
                    @forelse ($tasks as $t)
                        @php($st = $t->taskStatus())
                        <tr>
                            <td><a href="{{ route('afrit6.staff.tasks.show', $t) }}"><b>{{ __('workspace.kinds.'.$t->kind) }}</b></a><br><small class="muted">@include('afrit6.staff._task-title', ['t' => $t])</small></td>
                            <td>{{ $t->member->name }}<br><small class="muted">{{ $t->member->companyName() }}</small></td>
                            <td class="nowrap">{{ $t->date->locale(app()->getLocale())->translatedFormat('D j M') }}</td>
                            <td><span @class(['pill', 'pill-warn' => in_array($st, ['pending', 'waiting']), 'pill-danger' => $st === 'overdue', 'pill-info' => $st === 'confirmed', 'pill-ok' => $st === 'done', 'pill-off' => $st === 'declined'])>{{ __('workspace.tasks.status.'.$st) }}</span></td>
                            <td>{{ $t->assignee?->name ?? __('workspace.tasks.unassigned') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">{{ __('workspace.tasks.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$tasks"/>
</x-layouts.app>
