@php($levels = config('cluk.levels'))
<x-layouts.app :title="__('users.roles.title')">
    @include('settings.users._head', ['heading' => __('users.roles.title'), 'intro' => __('users.roles.intro'), 'tab' => 'roles'])

    <form class="card card-flush" method="post" action="{{ route('settings.roles.update') }}">
        @csrf @method('put')
        @foreach ($roles as $role)<input type="hidden" name="shown[]" value="{{ $role->id }}">@endforeach
        <div class="table-wrap">
            <table class="table matrix">
                <thead>
                    <tr>
                        <th class="sticky-col">{{ __('users.roles.levels') }}<small class="muted">{{ __('users.roles.legend') }}</small></th>
                        @foreach ($roles as $role)
                            <th>
                                <b>{{ $role->name }}</b>
                                <small class="muted">{{ trans_choice('users.roles.people', $role->users_count, ['count' => $role->users_count]) }}</small>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (config('cluk.modules') as $module)
                        <tr>
                            <td class="sticky-col">{{ __('cluk.modules.'.$module) }}</td>
                            @foreach ($roles as $role)
                                @php($l = $role->levelFor($module))
                                <td>
                                    <select name="levels[{{ $role->id }}][{{ $module }}]" @class(['level-select', 'level-'.$l]) aria-label="{{ $role->name }} · {{ __('cluk.modules.'.$module) }}">
                                        @foreach ($levels as $n => $level)
                                            <option value="{{ $n }}" @selected($l === $n)>{{ $n }} · {{ __('cluk.levels.'.$level) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="matrix-sep"><th class="sticky-col" colspan="{{ $roles->count() + 1 }}">{{ __('users.roles.rights') }}</th></tr>
                    @foreach (config('cluk.rights') as $right)
                        <tr>
                            <td class="sticky-col">{{ __('cluk.rights.'.$right) }}</td>
                            @foreach ($roles as $role)
                                <td class="center">
                                    <input type="checkbox" name="rights[{{ $role->id }}][]" value="{{ $right }}" @checked($role->hasRight($right)) aria-label="{{ $role->name }} · {{ __('cluk.rights.'.$right) }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <td class="sticky-col"></td>
                        @foreach ($roles as $role)
                            <td>
                                @if ($role->users_count === 0)
                                    <button class="linkbtn danger" type="submit" form="delete-role-{{ $role->id }}">{{ __('users.roles.delete') }}</button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="form-actions pad">
            <button class="primary" type="submit">{{ __('users.roles.save') }}</button>
        </div>
    </form>

    @foreach ($roles->where('users_count', 0) as $role)
        <form id="delete-role-{{ $role->id }}" method="post" action="{{ route('settings.roles.destroy', $role) }}" onsubmit="return confirm(@js(__('users.roles.delete_confirm', ['name' => $role->name])))">@csrf @method('delete')</form>
    @endforeach

    <form class="card" method="post" action="{{ route('settings.roles.store') }}">
        @csrf
        <h2>{{ __('users.roles.new') }}</h2>
        <div class="form-grid">
            <label class="field"><span>{{ __('users.roles.name') }}</span><input name="name" required maxlength="60" value="{{ old('name') }}"></label>
            <label class="field"><span>{{ __('users.roles.copy_from') }}</span>
                <select name="copy_from">
                    <option value="">{{ __('users.roles.start_empty') }}</option>
                    @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                </select></label>
        </div>
        <div class="form-actions"><button class="btn" type="submit">{{ __('users.roles.create') }}</button></div>
    </form>
</x-layouts.app>
