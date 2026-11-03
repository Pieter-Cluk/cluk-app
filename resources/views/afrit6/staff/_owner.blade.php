{{-- The owner's tools on the member card (right afrit6.manage_members). --}}
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
<section class="card" id="owner">
    <h2>{{ __('afrit6.manage.title') }}</h2>
    <p class="muted small">{{ __('afrit6.manage.title_hint') }}</p>
    <details @if ($errors->hasAny(['first_name', 'last_name', 'email', 'company', 'status', 'locale'])) open @endif>
        <summary>{{ __('afrit6.manage.edit') }}</summary>
        <form method="post" action="{{ route('afrit6.staff.manage.update', $member) }}" class="mt">
            @csrf @method('PUT')
            @include('afrit6.staff._member-form')
            <div class="form-actions"><button class="primary" type="submit">{{ __('afrit6.manage.save') }}</button></div>
        </form>
    </details>

    <h3 class="h3">{{ __('afrit6.manage.bookings') }}</h3>
    @forelse ($bookings as $b)
        <div class="p-line">
            <span><b>{{ $b['date']->format('d/m/Y') }}</b> {{ $b['what'] }}@if ($b['amount'] !== null) <small class="muted">· {{ $eur($b['amount']) }}</small>@endif</span>
            <form method="post" action="{{ route('afrit6.staff.manage.booking_remove', [$member, $b['kind'], $b['id']]) }}" onsubmit="return confirm(@js(__('afrit6.manage.remove_confirm')))">@csrf @method('DELETE')<button class="linkbtn" type="submit">{{ __('afrit6.manage.remove') }}</button></form>
        </div>
    @empty
        <p class="muted">{{ __('afrit6.manage.no_bookings') }}</p>
    @endforelse

    <details class="mt" @if ($errors->hasAny(['space_id', 'part', 'price'])) open @endif>
        <summary>{{ __('afrit6.manage.add_desk_day') }}</summary>
        <form method="post" action="{{ route('afrit6.staff.manage.desk_day', $member) }}" class="form-grid mt">
            @csrf
            <label class="field"><span>{{ __('afrit6.manage.date') }}</span><input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required></label>
            <label class="field"><span>{{ __('afrit6.manage.desk') }}</span><select name="space_id" required>@foreach ($deskSpaces as $d)<option value="{{ $d->id }}">{{ $d->code }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('afrit6.manage.part') }}</span><select name="part">@foreach (\App\Models\Afrit6\DeskBooking::PARTS as $p)<option value="{{ $p }}" @selected($p === 'day')>{{ __('workspace.days.parts.'.$p) }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('afrit6.manage.price') }}</span><input type="number" step="0.01" min="0" name="price" placeholder="{{ __('afrit6.manage.price_default') }}"></label>
            <div class="span-2"><button class="btn" type="submit">{{ __('afrit6.manage.add_booking') }}</button></div>
        </form>
    </details>
    @if (\App\Models\Afrit6\Space::room())
        <details class="mt" @if ($errors->hasAny(['start', 'slots'])) open @endif>
            <summary>{{ __('afrit6.manage.add_room') }}</summary>
            <form method="post" action="{{ route('afrit6.staff.manage.room', $member) }}" class="form-grid mt">
                @csrf
                <label class="field"><span>{{ __('afrit6.manage.date') }}</span><input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" required></label>
                <label class="field"><span>{{ __('afrit6.manage.from_time') }}</span><select name="start">@for ($i = 0; $i < (int) config('afrit6.room.slots'); $i++)<option value="{{ $i }}">{{ \App\Models\Afrit6\RoomBooking::slotTime($i) }}</option>@endfor</select></label>
                <label class="field"><span>{{ __('afrit6.manage.hours') }}</span><select name="slots">@for ($i = 1; $i <= (int) config('afrit6.room.slots'); $i++)<option value="{{ $i }}" @selected($i === 2)>{{ rtrim(rtrim(number_format($i / 2, 1, ',', ''), '0'), ',') }} h</option>@endfor</select></label>
                <label class="field"><span>{{ __('afrit6.manage.setup') }}</span><select name="setup">@foreach (array_keys(config('afrit6.room.setups')) as $s)<option value="{{ $s }}">{{ __('workspace.room.setups.'.$s) }}</option>@endforeach</select></label>
                <div class="span-2"><button class="btn" type="submit">{{ __('afrit6.manage.add_booking') }}</button></div>
            </form>
        </details>
    @endif

    <h3 class="h3">{{ __('afrit6.manage.account') }}</h3>
    <form method="post" action="{{ route('afrit6.staff.manage.welcome', $member) }}">@csrf<button class="btn" type="submit">{{ __('afrit6.manage.send_welcome') }}</button></form>
    <details class="mt" @if ($errors->has('confirm')) open @endif>
        <summary class="txt-danger">{{ __('afrit6.manage.delete') }}</summary>
        <p class="muted small">{{ __('afrit6.manage.delete_hint') }}</p>
        <form method="post" action="{{ route('afrit6.staff.manage.destroy', $member) }}" class="inline-form">
            @csrf @method('DELETE')
            <input name="confirm" placeholder="{{ $member->email }}" aria-label="{{ __('afrit6.manage.delete_type_email') }}" autocomplete="off" required>
            <button class="btn btn-danger" type="submit">{{ __('afrit6.manage.delete_go') }}</button>
        </form>
    </details>
</section>
