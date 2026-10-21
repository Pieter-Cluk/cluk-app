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
    @php($incoming = $user->canUseModule('sales', 2) ? \App\Models\Sales\IncomingMessage::where('status', 'new')->count() : 0)
    @php($ordersOpen = $user->canUseModule('dryhire', 2) ? \App\Models\Rental\Order::where('status', 'allocation')->where(fn ($q) => $q->whereNull('assignee_id')->orWhere('assignee_id', $user->id))->count() : 0)
    @php($venueRequests = $user->canUseModule('factory', 2) ? \App\Models\Factory\Booking::where('status', 'request')->count() : 0)
    @php($myDeals = $user->canUseModule('sales', 2) ? \App\Models\Sales\Deal::with('company')->open()->where('owner_id', $user->id)->orderByRaw('event_start is null, event_start')->limit(6)->get() : collect())
    <section class="card">
        <h2>{{ __('nav.your_tasks') }}</h2>
        @if ($incoming)<p><a href="{{ route('incoming.index') }}" class="pill pill-warn">{{ __('deals.dashboard.incoming') }} · {{ $incoming }}</a></p>@endif
        @if ($venueRequests)<p><a href="{{ route('factory.index') }}" class="pill pill-warn">{{ __('factory.dashboard') }} · {{ $venueRequests }}</a></p>@endif
        @if ($ordersOpen)<p><a href="{{ route('orders.index') }}" class="pill pill-warn">{{ __('orders.dashboard.orders') }} · {{ $ordersOpen }}</a></p>@endif
        @if ($myTasks->isEmpty() && ! $toConfirm && ! $incoming && $myDeals->isEmpty() && ! $ordersOpen && ! $venueRequests)
            <p class="muted">{{ __('nav.no_tasks') }}</p>
        @else
            @if ($toConfirm)<p><a href="{{ route('afrit6.staff.tasks', ['status' => 'pending']) }}" class="pill pill-warn">Afrit 6 · {{ __('workspace.tasks.to_confirm_count', ['count' => $toConfirm]) }}</a></p>@endif
            @foreach ($myTasks as $t)
                <div class="p-line"><a href="{{ route('afrit6.staff.tasks.show', $t) }}"><b>{{ __('workspace.kinds.'.$t->kind) }}</b> · {{ $t->member->companyName() }}</a><span class="muted">{{ $t->date->locale(app()->getLocale())->translatedFormat('D j M') }} · {{ __('workspace.tasks.status.'.$t->taskStatus()) }}</span></div>
            @endforeach
        @endif
        @if ($myDeals->isNotEmpty())
            <h3>{{ __('deals.dashboard.my_deals') }}</h3>
            @foreach ($myDeals as $d)
                <div class="p-line"><a href="{{ route('deals.show', $d) }}"><b>{{ $d->title }}</b> · {{ $d->customerName() }}</a><span class="muted">{{ __('deals.stages.'.$d->stage) }}@if ($d->event_start) · {{ $d->event_start->locale(app()->getLocale())->translatedFormat('D j M') }}@endif</span></div>
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
