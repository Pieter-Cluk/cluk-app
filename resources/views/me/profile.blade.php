<x-layouts.app :title="__('me.profile.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('me.kicker')) }}</div>
            <h1>{{ __('me.profile.title') }}</h1>
            <p class="muted">{{ __('me.profile.intro') }}</p>
        </div>
        <div class="inline-form"><a class="btn" href="{{ route('me.preferences') }}">{{ __('nav.items.preferences') }}</a></div>
    </div>
    @include('partials.flash')

    <div class="me-grid">
        <section class="card">
            <div class="um-head"><x-avatar :who="$u" size="lg"/><div><h2 style="margin:0">{{ $u->name }}</h2><small class="muted">{{ collect([$u->title, $u->department, $u->location])->filter()->implode(' · ') ?: '—' }}</small></div></div>
            <dl class="facts mt">
                <dt>{{ __('me.profile.email') }}</dt><dd>{{ $u->email }} <small class="muted">· {{ __('me.profile.from_m365') }}</small></dd>
                <dt>{{ __('nav.role') }}</dt><dd>{{ $u->role?->name ?? '—' }}</dd>
                <dt>{{ __('me.profile.reports_to') }}</dt><dd>{{ $u->reportsTo?->name ?? '—' }}</dd>
                <dt>{{ __('me.profile.commercial') }}</dt><dd>{{ $u->is_commercial ? __('companies.yes') : __('companies.no') }}</dd>
            </dl>
            <form method="post" action="{{ route('me.profile.save') }}" class="inline-form mt">@csrf @method('put')
                <label class="field"><span>{{ __('me.profile.phone') }}</span><input name="phone" maxlength="40" value="{{ old('phone', $u->phone) }}" inputmode="tel"></label>
                <label class="field"><span>{{ __('me.profile.mobile') }}</span><input name="mobile" maxlength="40" value="{{ old('mobile', $u->mobile) }}" inputmode="tel"></label>
                <div><button class="primary" type="submit">{{ __('me.save') }}</button></div>
            </form>
        </section>

        <section class="card">
            <h2>{{ __('users.door.title') }}</h2>
            @if ($u->door_code)<p class="door-code"><b>{{ $u->door_code }}</b></p><p class="muted small">{{ __('users.door.own_hint') }}</p>@else<p class="muted">{{ __('users.door.own_none') }}</p>@endif
        </section>

        <section class="card">
            <h2>{{ __('me.profile.access') }}</h2>
            <p class="muted">{{ __('me.profile.access_hint') }}</p>
            <div class="table-wrap">
                <table class="table table-tight">
                    <thead><tr><th>{{ __('users.access.module') }}</th><th>{{ __('users.access.result') }}</th></tr></thead>
                    <tbody>
                        @foreach (config('cluk.modules') as $module)
                            @php($override = $u->moduleOverrides->firstWhere('module', $module))
                            @php($lv = $override ? (int) $override->level : ($u->role?->levelFor($module) ?? 0))
                            <tr><td>{{ __('cluk.modules.'.$module) }}</td><td><span @class(['level', 'level-'.$lv])>{{ __('cluk.levels.'.$levels[$lv]) }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <h3>{{ __('users.access.rights') }}</h3>
            @php($mine = collect(config('cluk.rights'))->filter(fn ($r) => $u->hasRight($r)))
            @if ($mine->isEmpty())<p class="muted">{{ __('me.profile.no_rights') }}</p>@else<ul class="plain-list">@foreach ($mine as $r)<li>{{ __('cluk.rights.'.$r) }}</li>@endforeach</ul>@endif
        </section>
    </div>
</x-layouts.app>
