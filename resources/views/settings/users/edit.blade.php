@php($me = auth()->user())
@php($levels = config('cluk.levels'))
<x-layouts.app :title="$user->name">
    @include('settings.users._head', ['heading' => $user->name, 'intro' => $user->title, 'tab' => 'people'])

    <div class="split">
        <div class="stack">
            <form class="card" method="post" action="{{ route('settings.users.update', $user) }}">
                @csrf @method('put')
                <h2>{{ __('users.edit.details') }}</h2>
                @include('settings.users._details')
                <div class="form-actions">
                    <a class="btn" href="{{ route('settings.users.index') }}">{{ __('users.edit.back') }}</a>
                    <button class="primary" type="submit">{{ __('users.edit.save') }}</button>
                </div>
            </form>

            <form class="card" method="post" action="{{ route('settings.users.access', $user) }}">
                @csrf @method('put')
                <h2>{{ __('users.access.title') }}</h2>
                <p class="muted">{{ __('users.access.intro') }}</p>
                <div class="table-wrap">
                    <table class="table table-tight">
                        <thead>
                            <tr>
                                <th>{{ __('users.access.module') }}</th>
                                <th>{{ __('users.access.from_role') }}</th>
                                <th>{{ __('users.access.this_person') }}</th>
                                <th>{{ __('users.access.result') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (config('cluk.modules') as $module)
                                @php($override = $user->moduleOverrides->firstWhere('module', $module))
                                @php($roleLevel = $user->role?->levelFor($module) ?? 0)
                                @php($result = $override ? (int) $override->level : $roleLevel)
                                <tr>
                                    <td>{{ __('cluk.modules.'.$module) }}</td>
                                    <td class="muted">{{ __('cluk.levels.'.$levels[$roleLevel]) }}</td>
                                    <td>
                                        <select name="overrides[{{ $module }}]" aria-label="{{ __('cluk.modules.'.$module) }}">
                                            <option value="">{{ __('users.access.same_as_role') }}</option>
                                            @foreach ($levels as $n => $level)
                                                <option value="{{ $n }}" @selected($override && (int) $override->level === $n)>{{ __('cluk.levels.'.$level) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><span @class(['level', 'level-'.$result])>{{ __('cluk.levels.'.$levels[$result]) }}</span>@if ($override) <span class="pill pill-warn">{{ __('users.access.changed') }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3>{{ __('users.access.rights') }}</h3>
                <p class="muted">{{ __('users.access.rights_intro') }}</p>
                <div class="checks">
                    @foreach (config('cluk.rights') as $right)
                        @php($viaRole = (bool) $user->role?->hasRight($right))
                        <label @class(['check', 'is-off' => $viaRole])>
                            <input type="checkbox" name="rights[]" value="{{ $right }}" @checked($viaRole || $user->extraRights->contains('right', $right)) @disabled($viaRole)>
                            <span>{{ __('cluk.rights.'.$right) }}@if ($viaRole) <small>· {{ __('users.access.via_role') }}</small>@endif</span>
                        </label>
                    @endforeach
                </div>
                <div class="form-actions">
                    <button class="primary" type="submit">{{ __('users.access.save') }}</button>
                </div>
            </form>
        </div>

        <aside class="stack">
            <section class="card">
                <div class="person person-lg">
                    <span class="av av-lg">{{ $user->initials }}</span>
                    <span><b>{{ $user->name }}</b><small>{{ $user->email }}</small></span>
                </div>
                <p><span @class(['pill', 'pill-ok' => $user->isActive(), 'pill-off' => ! $user->isActive()])>{{ __('users.status.'.$user->status) }}</span>
                    <span class="muted">· {{ $user->microsoft_id ? __('users.edit.microsoft_linked') : __('users.edit.microsoft_not_linked') }}</span></p>
                @if ($user->is($me))
                    <p class="muted">{{ __('users.edit.you') }}</p>
                @elseif ($user->isActive())
                    <form method="post" action="{{ route('settings.users.disable', $user) }}" onsubmit="return confirm(@js(__('users.edit.disable_confirm', ['name' => $user->name])))">
                        @csrf
                        <button class="btn btn-danger" type="submit">{{ __('users.edit.disable') }}</button>
                    </form>
                @else
                    <p class="muted">{{ __('users.edit.disabled_note') }}</p>
                    <form method="post" action="{{ route('settings.users.enable', $user) }}">
                        @csrf
                        <button class="btn" type="submit">{{ __('users.edit.enable') }}</button>
                    </form>
                @endif
            </section>

            @if ($user->directReports->isNotEmpty())
                <section class="card">
                    <h2>{{ __('users.edit.direct_reports') }}</h2>
                    <ul class="list">
                        @foreach ($user->directReports->sortBy('first_name') as $r)
                            <li><a href="{{ route('settings.users.edit', $r) }}">{{ $r->name }}</a> <span class="muted">{{ $r->title }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="card">
                <h2>{{ __('users.edit.activity') }}</h2>
                @forelse ($activity as $log)
                    <div class="log-line">
                        <small class="muted">{{ $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small>
                        <div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div>
                    </div>
                @empty
                    <p class="muted">{{ __('users.edit.no_activity') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.app>
