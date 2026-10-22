@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
<section class="card">
    <h2>{{ __('workspace.staff.desks_card') }}</h2>
    @forelse ($rentals as $r)
        <div class="p-line">
            <span><b>{{ $r->space->code }}</b> <small class="muted">{{ $r->starts_on->format('d/m/Y') }} → {{ $r->ends_on?->format('d/m/Y') ?? __('workspace.desks.open_ended') }} · {{ __('workspace.desks.per_month', ['amount' => $eur($r->monthly_price_cents)]) }}</small></span>
            @if ($canDesks && (! $r->ends_on || $r->ends_on->gte(today())))
                <form method="post" action="{{ route('afrit6.staff.desks.end', [$member, $r]) }}" class="inline-form">
                    @csrf
                    <input type="date" name="ends_on" min="{{ $r->starts_on->toDateString() }}" value="{{ ($r->ends_on ?? today())->toDateString() }}" aria-label="{{ __('workspace.staff.end') }}">
                    <button class="btn" type="submit">{{ __('workspace.staff.end') }}</button>
                </form>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('workspace.staff.no_rentals') }}</p>
    @endforelse
    @if ($canDesks && $member->isActive())
        <h3 class="h3">{{ __('workspace.staff.add_desk') }}</h3>
        <form method="post" action="{{ route('afrit6.staff.desks.store', $member) }}" class="form-grid">
            @csrf
            <label class="field"><span>{{ __('workspace.staff.tabs.desks') }}</span>
                <select name="space_id" required>@foreach ($desks as $d)<option value="{{ $d->id }}" @selected(old('space_id') == $d->id)>{{ $d->code }} · {{ __('workspace.desks.zones.'.$d->zone) }}</option>@endforeach</select>
            </label>
            <label class="field"><span>{{ __('workspace.staff.price_month') }}</span><input type="number" step="0.01" min="0" name="price" value="{{ old('price', number_format(config('afrit6.prices.desk_month') / 100, 2, '.', '')) }}" required></label>
            <label class="field"><span>{{ __('workspace.staff.starts_on') }}</span><input type="date" name="starts_on" value="{{ old('starts_on', today()->toDateString()) }}" required></label>
            <label class="field"><span>{{ __('workspace.staff.ends_on') }}</span><input type="date" name="ends_on" value="{{ old('ends_on') }}"><small>{{ __('workspace.staff.ends_hint') }}</small></label>
            <div class="span-2"><button class="btn" type="submit">{{ __('workspace.staff.add') }}</button></div>
        </form>
    @endif
</section>

<section class="card">
    <h2>{{ __('workspace.staff.door') }}</h2>
    @if ($member->door_requested_at)<p class="flash flash-err">{{ __('workspace.staff.door_requested', ['date' => $member->door_requested_at->format('d/m/Y H:i')]) }}@if ($member->door_request_note) · “{{ $member->door_request_note }}”@endif</p>@endif
    @if ($canDoor)
        <form method="post" action="{{ route('afrit6.staff.door', $member) }}" class="inline-form">
            @csrf @method('PUT')
            <input name="door_code" inputmode="numeric" pattern="[0-9 ]{4,12}" value="{{ $member->door_code }}" aria-label="{{ __('workspace.staff.door') }}">
            <button class="btn" type="submit">{{ __('workspace.tasks.save') }}</button>
        </form>
        <p class="muted small">{{ __('workspace.staff.door_hint') }}</p>
    @else
        <p>{{ $member->door_code ? __('workspace.staff.door_set') : __('workspace.staff.door_none') }}</p>
        <p class="muted small">{{ __('workspace.staff.door_no_right') }}</p>
    @endif
</section>

@if ($openTasks->isNotEmpty())
    <section class="card">
        <h2>{{ __('workspace.staff.open_tasks') }}</h2>
        @foreach ($openTasks as $t)
            <div class="p-line"><a href="{{ route('afrit6.staff.tasks.show', $t) }}"><b>{{ __('workspace.kinds.'.$t->kind) }}</b></a><span class="muted">{{ $t->date->format('d/m') }} · {{ __('workspace.tasks.status.'.$t->taskStatus()) }}</span></div>
        @endforeach
    </section>
@endif

@if ($bill)
    @foreach ([['workspace.staff.bill_title', $bill], ['workspace.staff.last_bill', $lastBill]] as [$label, $b])
        <h2 class="h2">{{ __($label) }}</h2>
        @include('afrit6._bill', ['bill' => $b])
        @if ($canStatement && $b['lines'])
            <form method="post" action="{{ route('afrit6.staff.statements.store', $member) }}">
                @csrf <input type="hidden" name="day" value="{{ $b['start']->toDateString() }}">
                <button class="btn" type="submit">{{ __('workspace.staff.save_statement') }}</button>
            </form>
        @endif
    @endforeach
@endif

@if ($statements->isNotEmpty())
    <section class="card">
        <h2>{{ __('workspace.staff.statements') }}</h2>
        @foreach ($statements as $s)
            <div class="p-line"><span>{{ __('workspace.bill.period', ['from' => $s->period_start->format('d/m/Y'), 'to' => $s->period_end->format('d/m/Y')]) }} <small class="muted">{{ __('workspace.staff.by', ['name' => $s->createdBy?->name ?? '—']) }}</small></span><b>{{ $eur($s->total_cents) }}</b></div>
        @endforeach
    </section>
@endif
