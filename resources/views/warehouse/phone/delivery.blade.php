<x-layouts.phone :title="__('warehouse.delivery.title')" :back="route('w.home')">
    <p class="muted">{{ __('warehouse.delivery.intro') }}</p>
    <form method="post" action="{{ route('w.delivery.store') }}" enctype="multipart/form-data">
        @csrf
        <h2 class="ph-h">1 · {{ __('warehouse.delivery.photo') }}</h2>
        <label class="ph-photo-add ph-photo-wide">＋<span>{{ __('warehouse.delivery.photo_btn') }}</span><input type="file" name="photos[]" accept="image/*" capture="environment" multiple required></label>
        <div class="ph-row mt"><input name="carrier" maxlength="80" value="{{ old('carrier') }}" placeholder="{{ __('warehouse.delivery.carrier') }}" aria-label="{{ __('warehouse.delivery.carrier') }}"></div>
        <div class="ph-row"><input name="label_text" maxlength="300" value="{{ old('label_text') }}" placeholder="{{ __('warehouse.delivery.label_text') }}" aria-label="{{ __('warehouse.delivery.label_text') }}"></div>

        <h2 class="ph-h">2 · {{ __('warehouse.delivery.what') }}</h2>
        <label class="ph-acc"><span><b>{{ __('warehouse.delivery.colli') }}</b></span><input type="number" name="colli" min="0" max="999" value="{{ old('colli', 1) }}" class="ph-qty"></label>
        <label class="ph-acc"><span><b>{{ __('warehouse.delivery.pallets') }}</b></span><input type="number" name="pallets" min="0" max="99" value="{{ old('pallets', 0) }}" class="ph-qty"></label>
        <label class="ph-acc"><span><b>{{ __('warehouse.delivery.damaged') }}</b><small>{{ __('warehouse.delivery.damaged_hint') }}</small></span><input type="checkbox" name="damaged" value="1" @checked(old('damaged'))></label>

        <h2 class="ph-h">3 · {{ __('warehouse.delivery.who') }}</h2>
        <label class="ph-opt-row"><input type="radio" name="match" value="member" @checked(old('match', 'member') === 'member')> <span><b>{{ __('warehouse.delivery.m_member') }}</b><small>{{ __('warehouse.delivery.m_member_s') }}</small></span></label>
        <select name="member_id" aria-label="{{ __('warehouse.delivery.m_member') }}" class="ph-select"><option value="">—</option>@foreach ($members as $m)<option value="{{ $m->id }}" @selected((int) old('member_id') === $m->id)>{{ $m->companyName() }} · {{ $m->name }}</option>@endforeach</select>
        <label class="ph-opt-row"><input type="radio" name="match" value="return" @checked(old('match') === 'return')> <span><b>{{ __('warehouse.delivery.m_return') }}</b><small>{{ __('warehouse.delivery.m_return_s') }}</small></span></label>
        <select name="order_id" aria-label="{{ __('warehouse.delivery.m_return') }}" class="ph-select"><option value="">—</option>@foreach ($orders as $o)<option value="{{ $o->id }}" @selected((int) old('order_id') === $o->id)>{{ $o->number }} · {{ $o->customerName() }}</option>@endforeach</select>
        <label class="ph-opt-row"><input type="radio" name="match" value="unknown" @checked(old('match') === 'unknown')> <span><b>{{ __('warehouse.delivery.m_unknown') }}</b><small>{{ __('warehouse.delivery.m_unknown_s') }}</small></span></label>
        <button class="primary ph-big" type="submit">{{ __('warehouse.delivery.register') }}</button>
    </form>
</x-layouts.phone>
