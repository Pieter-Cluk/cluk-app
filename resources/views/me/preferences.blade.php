<x-layouts.app :title="__('me.prefs.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('me.kicker')) }}</div>
            <h1>{{ __('me.prefs.title') }}</h1>
            <p class="muted">{{ __('me.prefs.intro') }} <a href="{{ route('me.profile') }}">{{ __('me.prefs.your_profile') }}</a>.</p>
        </div>
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('me.preferences.save') }}" class="me-grid">@csrf @method('put')
        <section class="card">
            <h2>{{ __('me.prefs.appearance') }}</h2>
            <div class="me-row">
                <div><b>{{ __('me.prefs.theme') }}</b><p class="muted">{{ __('me.prefs.theme_hint') }}</p></div>
                <div class="seg" role="radiogroup" aria-label="{{ __('me.prefs.theme') }}">@foreach (\App\Http\Controllers\Me\MeController::THEMES as $t)<label><input type="radio" name="theme" value="{{ $t }}" @checked(old('theme', $u->theme ?? 'auto') === $t)>{{ __('me.prefs.themes.'.$t) }}</label>@endforeach</div>
            </div>
            <div class="me-row">
                <div><b>{{ __('me.prefs.density') }}</b><p class="muted">{{ __('me.prefs.density_hint') }}</p></div>
                <div class="seg" role="radiogroup" aria-label="{{ __('me.prefs.density') }}">@foreach (\App\Http\Controllers\Me\MeController::DENSITIES as $t)<label><input type="radio" name="density" value="{{ $t }}" @checked(old('density', $u->density ?? 'comfortable') === $t)>{{ __('me.prefs.densities.'.$t) }}</label>@endforeach</div>
            </div>
            <div class="me-row">
                <div><b>{{ __('me.prefs.start') }}</b><p class="muted">{{ __('me.prefs.start_hint') }}</p></div>
                <label class="field"><span class="sr-only">{{ __('me.prefs.start') }}</span><select name="start_page">@foreach ($starts as $k)<option value="{{ $k }}" @selected(old('start_page', $u->start_page ?? 'dashboard') === $k)>{{ __('nav.items.'.$k) }}</option>@endforeach</select></label>
            </div>
        </section>

        <section class="card">
            <h2>{{ __('me.prefs.language') }}</h2>
            <div class="me-row">
                <div><b>{{ __('me.prefs.app_language') }}</b><p class="muted">{{ __('me.prefs.app_language_hint') }}</p></div>
                <label class="field"><span class="sr-only">{{ __('me.prefs.app_language') }}</span><select name="locale">@foreach (config('cluk.locales') as $code => $name)<option value="{{ $code }}" @selected(old('locale', $u->locale ?: app()->getLocale()) === $code)>{{ $name }}</option>@endforeach</select></label>
            </div>
        </section>

        <section class="card" id="notifications">
            <h2>{{ __('me.notify.title') }}</h2>
            <p class="muted">{{ __('me.notify.intro') }}</p>
            <div class="table-wrap">
                <table class="table table-tight">
                    <thead><tr><th>{{ __('me.notify.when') }}</th><th class="center">{{ __('me.notify.in_app') }}</th><th class="center">{{ __('me.notify.email') }}</th></tr></thead>
                    <tbody>
                        @foreach (\App\Models\User::MAIL_EVENTS as $e)
                            <tr><td>{{ __('me.notify.events.'.$e) }}</td><td class="center" title="{{ __('me.notify.always') }}">✓</td><td class="center"><input type="checkbox" name="mail[{{ $e }}]" value="1" @checked(old('mail.'.$e, $u->wantsMail($e))) aria-label="{{ __('me.notify.email') }} · {{ __('me.notify.events.'.$e) }}"></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="me-row">
                <div><b>{{ __('me.notify.daily') }}</b><p class="muted">{{ __('me.notify.daily_hint') }}</p></div>
                <div class="seg" role="radiogroup" aria-label="{{ __('me.notify.daily') }}"><label><input type="radio" name="daily_at" value="" @checked(! old('daily_at', $u->daily_at))>{{ __('me.off') }}</label>@foreach (\App\Services\Me\DailySummary::TIMES as $t)<label><input type="radio" name="daily_at" value="{{ $t }}" @checked(old('daily_at', $u->daily_at) === $t)>{{ $t }}</label>@endforeach</div>
            </div>
        </section>

        <section class="card">
            <h2>{{ __('me.away.title') }}</h2>
            <div class="me-row">
                <div><b>{{ __('me.away.delegate') }}</b><p class="muted">{{ __('me.away.delegate_hint') }}</p></div>
                <label class="field"><span class="sr-only">{{ __('me.away.delegate') }}</span><select name="away_delegate_id"><option value="">{{ __('me.away.nobody') }}</option>@foreach ($colleagues as $c)<option value="{{ $c->id }}" @selected((int) old('away_delegate_id', $u->away_delegate_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
            </div>
            <div class="me-row">
                <div><b>{{ __('me.away.dates') }}</b>@if ($u->awayNow())<p><span class="pill pill-warn">{{ __('me.away.badge', ['date' => $u->away_until->format('d/m')]) }}</span></p>@endif</div>
                <div class="inline-form">
                    <label class="field"><span>{{ __('me.away.from') }}</span><input type="date" name="away_from" value="{{ old('away_from', $u->away_from?->toDateString()) }}"></label>
                    <label class="field"><span>{{ __('me.away.until') }}</span><input type="date" name="away_until" value="{{ old('away_until', $u->away_until?->toDateString()) }}"></label>
                </div>
            </div>
        </section>

        <div class="me-save"><button class="primary" type="submit">{{ __('me.save') }}</button><span class="muted">{{ __('me.save_hint') }}</span></div>
    </form>

    <section class="card me-grid mt" style="max-width:880px">
        <h2>{{ __('me.signin.title') }}</h2>
        <div class="me-row">
            <div><b>{{ __('me.signin.method') }}</b></div>
            <div>{{ $u->email_login ? __('me.signin.code', ['email' => $u->code_email ?: $u->email]) : __('me.signin.microsoft', ['email' => $u->email]) }}</div>
        </div>
        @if ($devices)
            <div class="me-row">
                <div><b>{{ trans_choice('me.signin.devices', count($devices), ['n' => count($devices)]) }}</b><p class="muted">{{ __('me.signin.devices_hint') }}</p></div>
                <div>
                    <ul class="plain-list">@foreach ($devices as $dv)<li>{{ $dv['name'] }} · <span class="muted">{{ $dv['at']->timezone('Europe/Brussels')->format('d/m H:i') }}</span>@if ($dv['current']) <span class="pill pill-ok">{{ __('me.signin.this_one') }}</span>@endif</li>@endforeach</ul>
                    @if (count($devices) > 1)<form method="post" action="{{ route('me.logout_others') }}" class="mt">@csrf<button class="btn" type="submit">{{ __('me.signin.logout_others') }}</button></form>@endif
                </div>
            </div>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">{{ __('nav.logout') }}</button></form>
    </section>
</x-layouts.app>
