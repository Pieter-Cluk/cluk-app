@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.phone :title="__('warehouse.check.title', ['code' => $asset->code])" :back="route('w.home')">
    <section class="ph-card">
        <b>{{ $asset->item->name }} · {{ $asset->code }}</b>
        <small>@if ($asset->serial_number)S/N {{ $asset->serial_number }} · @endif{{ $check->order ? __('warehouse.check.from', ['order' => $check->order->number, 'customer' => $check->order->customerName()]) : __('warehouse.check.no_order') }}</small>
    </section>
    <form method="post" action="{{ route('w.check.save', $asset) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="photo_only" value="0" data-photo-only>
        <h2 class="ph-h">1 · {{ __('warehouse.check.cleaning') }}</h2>
        <p class="ph-done">{{ $check->cleaned_at ? '✓ '.__('warehouse.check.cleaned', ['stage' => $check->dirt, 'stage_name' => __('warehouse.clean.stages.'.$check->dirt)]) : __('warehouse.check.not_cleaned') }}</p>

        <h2 class="ph-h">2 · {{ __('warehouse.check.accessories') }}</h2>
        @forelse ($asset->item->kit as $k)
            <label class="ph-acc"><span><b>{{ $k->name }}</b><small>{{ $k->number }} · {{ __('warehouse.check.per_piece', ['n' => $k->pivot->qty]) }}@if ($k->catalog_price_cents) · {{ $eur($k->catalog_price_cents) }}@endif</small></span>
                <select name="missing[{{ $k->id }}]" aria-label="{{ __('warehouse.check.missing_of', ['name' => $k->name]) }}">
                    <option value="0">{{ __('warehouse.check.complete') }}</option>
                    @for ($i = 1; $i <= max(1, $k->pivot->qty); $i++)<option value="{{ $i }}" @selected((int) old('missing.'.$k->id) === $i)>{{ __('warehouse.check.missing_n', ['n' => $i]) }}</option>@endfor
                </select></label>
        @empty
            <p class="muted small">{{ __('warehouse.check.no_kit') }}</p>
        @endforelse
        <details class="ph-more" @if (old('extra_item')) open @endif>
            <summary>{{ __('warehouse.check.other_missing') }}</summary>
            <div class="ph-row">
                <input name="extra_item" list="stock-items" value="{{ old('extra_item') }}" placeholder="{{ __('warehouse.check.search_stock') }}" aria-label="{{ __('warehouse.check.search_stock') }}">
                <input name="extra_qty" type="number" min="1" max="999" value="{{ old('extra_qty', 1) }}" aria-label="{{ __('warehouse.check.qty') }}" class="ph-qty">
            </div>
            <datalist id="stock-items">@foreach ($stockItems as $s)<option value="{{ $s->number }} · {{ $s->name }}">@endforeach</datalist>
        </details>

        <h2 class="ph-h">3 · {{ __('warehouse.check.tests') }}</h2>
        @foreach ($tests as $t)
            <div class="ph-test"><span>{{ __('warehouse.tests.'.$t) }}</span>
                <label class="tb tb-okc"><input type="radio" name="tests[{{ $t }}]" value="ok" @checked(old('tests.'.$t) === 'ok')> OK</label>
                <label class="tb tb-failc"><input type="radio" name="tests[{{ $t }}]" value="fail" @checked(old('tests.'.$t) === 'fail')> {{ __('warehouse.check.fail') }}</label>
            </div>
        @endforeach
        <button type="button" class="btn btn-sm" onclick="this.form.querySelectorAll('input[value=ok][name^=tests]').forEach(r => r.checked = true)">{{ __('warehouse.check.all_ok') }}</button>

        <h2 class="ph-h">4 · {{ __('warehouse.check.damage') }}</h2>
        @for ($i = 0; $i < 2; $i++)
            <div class="ph-row">
                <select name="damage_type[{{ $i }}]" aria-label="{{ __('warehouse.check.damage') }}"><option value="">{{ __('warehouse.check.no_damage') }}</option>@foreach (config('warehouse.damage_types') as $d)<option value="{{ $d }}" @selected(old('damage_type.'.$i) === $d)>{{ __('warehouse.damage.'.$d) }}</option>@endforeach</select>
                <input name="damage_note[{{ $i }}]" value="{{ old('damage_note.'.$i) }}" maxlength="200" placeholder="{{ __('warehouse.check.damage_note') }}" aria-label="{{ __('warehouse.check.damage_note') }}">
            </div>
        @endfor

        <h2 class="ph-h">5 · {{ __('warehouse.check.photos') }}</h2>
        @include('warehouse.phone._photos', ['stage' => null])
        <p class="muted small">{{ __('warehouse.check.photo_rule') }}</p>

        <h2 class="ph-h">6 · {{ __('warehouse.check.reset') }}</h2>
        <label class="ph-acc"><span>{{ __('warehouse.check.reset_text') }}</span><input type="checkbox" name="reset" value="1" @checked(old('reset'))></label>

        <button class="primary ph-big" type="submit">{{ __('warehouse.check.finish') }}</button>
        <p class="muted small">{{ __('warehouse.check.office_rule') }}</p>
    </form>
</x-layouts.phone>
