<x-layouts.app :title="__('users.index.title')">
    @include('settings.users._head', [
        'heading' => __('users.index.title'),
        'intro' => __('users.index.intro'),
        'tab' => 'people',
        'action' => '<a class="primary" href="'.e(route('settings.users.create')).'">'.e(__('users.index.add')).'</a>',
    ])

    <form class="filters" method="get" action="{{ route('settings.users.index') }}">
        <label class="search field-search">
            <x-icon name="search"/>
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('users.index.search') }}" aria-label="{{ __('users.index.search') }}">
        </label>
        <select name="role" aria-label="{{ __('users.columns.role') }}">
            <option value="">{{ __('users.index.all_roles') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="{{ __('users.columns.status') }}">
            <option value="active" @selected($status === 'active')>{{ __('users.index.status_active', ['count' => $counts['active']]) }}</option>
            <option value="disabled" @selected($status === 'disabled')>{{ __('users.index.status_disabled', ['count' => $counts['disabled']]) }}</option>
            <option value="all" @selected($status === 'all')>{{ __('users.index.status_all') }}</option>
        </select>
        <button class="btn" type="submit">{{ __('users.index.filter') }}</button>
    </form>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('users.columns.name') }}</th>
                        <th>{{ __('users.columns.title') }}</th>
                        <th>{{ __('users.columns.role') }}</th>
                        <th>{{ __('users.columns.reports_to') }}</th>
                        <th>{{ __('users.columns.status') }}</th>
                        <th>{{ __('users.columns.last_seen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td>
                                <a class="person" href="{{ route('settings.users.edit', $u) }}">
                                    <x-avatar :who="$u"/>
                                    <span><b>{{ $u->name }}</b><small>{{ $u->email }}</small></span>
                                </a>
                            </td>
                            <td>{{ $u->title ?? '—' }}</td>
                            <td>{{ $u->role?->name ?? '—' }}</td>
                            <td>{{ $u->reportsTo?->name ?? '—' }}</td>
                            <td><span @class(['pill', 'pill-ok' => $u->isActive(), 'pill-off' => ! $u->isActive()])>{{ __('users.status.'.$u->status) }}</span></td>
                            <td class="muted">{{ $u->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? __('users.index.never') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ __('users.index.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$users"/>
</x-layouts.app>
