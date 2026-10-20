@php($user = auth()->user())
@php($hour = (int) now()->format('G'))
<x-layouts.app :title="__('nav.items.dashboard')">
    <div class="pagehead">
        <div>
            <div class="kicker">CLUK / {{ mb_strtoupper(__('nav.overview')) }}</div>
            <h1>{{ __($hour < 12 ? 'nav.greeting_morning' : ($hour < 18 ? 'nav.greeting_afternoon' : 'nav.greeting_evening'), ['name' => $user->first_name]) }}</h1>
            <p class="muted">{{ __('nav.today') }}</p>
        </div>
        <button type="button" class="primary">{{ __('nav.new_request') }}</button>
    </div>

    <section class="card">
        <h2>{{ __('nav.your_tasks') }}</h2>
        <p class="muted">{{ __('nav.no_tasks') }}</p>
    </section>

    <section class="card">
        <h2>{{ __('nav.your_modules') }}</h2>
        <div class="chips">
            @foreach ($user->allowedModules() as $module)
                <span class="chip">{{ __('cluk.modules.'.$module) }} · {{ __('cluk.levels.'.config('cluk.levels')[$user->moduleLevel($module)]) }}</span>
            @endforeach
        </div>
    </section>
</x-layouts.app>
