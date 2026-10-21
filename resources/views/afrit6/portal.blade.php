@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
<x-layouts.portal :title="__('afrit6.portal.title')" tab="overview">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper($member->companyName()) }}</div>
            <h1>{{ __('afrit6.portal.welcome', ['name' => $member->first_name]) }}</h1>
            <p class="muted">{{ __('afrit6.portal.intro') }}</p>
        </div>
    </div>

    @if ($waiting->isNotEmpty())
        <a class="flash flash-warn p-link" href="{{ route('afrit6.portal.services') }}">{{ __('workspace.portal.question_waiting') }} →</a>
    @endif

    <div class="p-grid">
        <section class="card">
            <h2>{{ __('workspace.portal.door_code') }}</h2>
            @if ($member->door_code)
                <details class="door">
                    <summary><span class="door-dots" aria-hidden="true">• • • •</span><span class="btn">{{ __('workspace.portal.show') }}</span></summary>
                    <div class="door-code">{{ trim(chunk_split($member->door_code, 1, ' ')) }}</div>
                </details>
            @else
                <p class="muted">{{ __('workspace.portal.no_code') }}</p>
            @endif
        </section>

        <section class="card">
            <h2>{{ __('workspace.portal.your_desks') }}</h2>
            @forelse ($desks as $r)
                <div class="p-line"><b>{{ $r->space->code }}</b><span class="muted">{{ __('workspace.desks.zones.'.$r->space->zone) }} · {{ $r->ends_on ? __('workspace.desks.until', ['date' => $r->ends_on->format('d/m/Y')]) : __('workspace.desks.open_ended') }}</span></div>
            @empty
                <p class="muted">{{ __('workspace.portal.no_desks') }}</p>
            @endforelse
            <p class="mt"><a href="{{ route('afrit6.portal.desks') }}">{{ __('workspace.nav.desks') }} →</a></p>
        </section>

        <section class="card">
            <h2>{{ __('workspace.nav.room') }}</h2>
            <p class="big-num">{{ max(0, $allowance - $used) }} <small>/ {{ $allowance }}</small></p>
            <p class="muted">{{ __('workspace.portal.free_slots', ['left' => max(0, $allowance - $used), 'total' => $allowance]) }}</p>
            <h3 class="h3">{{ __('workspace.portal.next_bookings') }}</h3>
            @forelse ($bookings as $b)
                <div class="p-line"><b>{{ $b->date->locale(app()->getLocale())->translatedFormat('D j M') }}</b><span>{{ $b->timeRange() }} · {{ __('workspace.room.setups.'.$b->setup) }}</span></div>
            @empty
                <p class="muted">{{ __('workspace.portal.nothing') }}</p>
            @endforelse
            <p class="mt"><a href="{{ route('afrit6.portal.room') }}">{{ __('workspace.room.book') }} →</a></p>
        </section>

        <section class="card">
            <h2>{{ __('workspace.portal.open_requests') }}</h2>
            @forelse ($open as $r)
                <div class="p-line"><b>{{ __('workspace.kinds.'.$r->kind) }}</b><span @class(['pill', 'pill-warn' => in_array($r->status, ['pending', 'waiting']), 'pill-ok' => $r->status === 'confirmed'])>{{ __('workspace.services.status.'.$r->status) }}</span></div>
            @empty
                <p class="muted">{{ __('workspace.portal.nothing') }}</p>
            @endforelse
            <p class="mt"><a href="{{ route('afrit6.portal.services') }}">{{ __('workspace.services.new') }} →</a></p>
        </section>

        <section class="card">
            <h2>{{ __('workspace.portal.this_month') }}</h2>
            <p class="big-num">{{ $eur($bill['total_cents']) }}</p>
            <p class="muted">{{ __('workspace.bill.period', ['from' => $bill['start']->format('d/m'), 'to' => $bill['end']->format('d/m/Y')]) }} · {{ __('workspace.bill.total') }}</p>
            <p class="mt"><a href="{{ route('afrit6.portal.bill') }}">{{ __('workspace.portal.see_all') }} →</a></p>
        </section>
    </div>
    <p class="muted small">{{ __('afrit6.portal.account', ['email' => $member->email]) }}</p>
</x-layouts.portal>
