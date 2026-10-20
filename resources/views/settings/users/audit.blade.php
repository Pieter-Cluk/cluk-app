<x-layouts.app :title="__('users.audit.title')">
    @include('settings.users._head', ['heading' => __('users.audit.title'), 'intro' => __('users.audit.intro'), 'tab' => 'activity'])

    <div class="segmented" role="group">
        <a href="{{ route('settings.audit.index') }}" @class(['seg', 'seg-on' => $area === null])>{{ __('users.audit.all') }}</a>
        @foreach (\App\Http\Controllers\Settings\AuditLogController::AREAS as $a)
            <a href="{{ route('settings.audit.index', ['area' => $a]) }}" @class(['seg', 'seg-on' => $area === $a])>{{ __('users.audit.areas.'.$a) }}</a>
        @endforeach
    </div>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('users.audit.when') }}</th>
                        <th>{{ __('users.audit.who') }}</th>
                        <th>{{ __('users.audit.what') }}</th>
                        <th>{{ __('users.audit.about') }}</th>
                        <th>{{ __('users.audit.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="muted nowrap">{{ $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td>{{ $log->actor?->name ?? __('users.audit.system') }}</td>
                            <td>{{ \App\Support\AuditText::action($log) }}</td>
                            <td>
                                @if ($log->subject instanceof \App\Models\User)
                                    <a href="{{ route('settings.users.edit', $log->subject) }}">{{ $log->subject->name }}</a>
                                @else
                                    {{ \App\Support\AuditText::subject($log) }}
                                @endif
                            </td>
                            <td class="muted">{{ \App\Support\AuditText::details($log) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">{{ __('users.audit.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$logs"/>
</x-layouts.app>
