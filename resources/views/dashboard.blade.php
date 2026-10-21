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

    @php($myTasks = $user->canUseModule('afrit6') ? \App\Models\Afrit6\ServiceRequest::with('member')->open()->where('assignee_id', $user->id)->orderBy('date')->limit(8)->get() : collect())
    @php($toConfirm = $user->canUseModule('afrit6', 2) ? \App\Models\Afrit6\ServiceRequest::where('status', 'pending')->whereNull('assignee_id')->count() : 0)
    <section class="card">
        <h2>{{ __('nav.your_tasks') }}</h2>
        @if ($myTasks->isEmpty() && ! $toConfirm)
            <p class="muted">{{ __('nav.no_tasks') }}</p>
        @else
            @if ($toConfirm)<p><a href="{{ route('afrit6.staff.tasks', ['status' => 'pending']) }}" class="pill pill-warn">Afrit 6 · {{ __('workspace.tasks.to_confirm_count', ['count' => $toConfirm]) }}</a></p>@endif
            @foreach ($myTasks as $t)
                <div class="p-line"><a href="{{ route('afrit6.staff.tasks.show', $t) }}"><b>{{ __('workspace.kinds.'.$t->kind) }}</b> · {{ $t->member->companyName() }}</a><span class="muted">{{ $t->date->locale(app()->getLocale())->translatedFormat('D j M') }} · {{ __('workspace.tasks.status.'.$t->taskStatus()) }}</span></div>
            @endforeach
        @endif
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
