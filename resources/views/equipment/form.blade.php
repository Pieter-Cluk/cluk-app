@php($v = fn ($k, $d = null) => old($k, $d))
<x-layouts.app :title="$item->exists ? $item->number : __('equipment.new')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('equipment.index') }}">{{ mb_strtoupper(__('nav.items.dryhire')) }}</a></div>
            <h1>{{ $item->exists ? __('equipment.edit_title', ['number' => $item->number]) : __('equipment.new') }}</h1>
        </div>
    </div>
    @include('partials.flash')
    <form method="post" action="{{ $item->exists ? route('equipment.update', $item) : route('equipment.store') }}" class="stack">
        @csrf @if ($item->exists) @method('PUT') @endif
        <section class="card">
            <h2>{{ __('equipment.details') }}</h2>
            <div class="form-grid">
                <label class="field"><span>{{ __('equipment.fields.number') }}</span><input name="number" required maxlength="40" value="{{ $v('number', $item->number) }}" placeholder="LT-SPIIDER"><small>{{ __('equipment.number_hint') }}</small></label>
                <label class="field"><span>{{ __('equipment.cols.kind') }}</span>
                    <select name="kind">@foreach (\App\Models\Equipment\Item::KINDS as $k)<option value="{{ $k }}" @selected($v('kind', $item->kind) === $k)>{{ __('equipment.kinds.'.$k) }}</option>@endforeach</select>
                </label>
                <label class="field span-2"><span>{{ __('equipment.fields.name') }}</span><input name="name" required maxlength="160" value="{{ $v('name', $item->name) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.category') }}</span><input name="category" maxlength="60" value="{{ $v('category', $item->category) }}" list="cats"></label>
                <label class="field"><span>{{ __('equipment.fields.subcategory') }}</span><input name="subcategory" maxlength="80" value="{{ $v('subcategory', $item->subcategory) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.brand') }}</span><input name="brand" maxlength="80" value="{{ $v('brand', $item->brand) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.type') }}</span><input name="type" maxlength="120" value="{{ $v('type', $item->type) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.unit') }}</span><input name="unit" required maxlength="12" value="{{ $v('unit', $item->unit ?? 'pcs') }}"></label>
                <label class="check"><input type="checkbox" name="serial" value="1" @checked($v('serial', $item->serial))><span>{{ __('equipment.fields.serial') }}<br><small>{{ __('equipment.serial_hint') }}</small></span></label>
                <label class="field"><span>{{ __('equipment.fields.stock_qty') }}</span><input type="number" name="stock_qty" min="0" value="{{ $v('stock_qty', $item->stock_qty) }}"><small>{{ __('equipment.stock_hint') }}</small></label>
            </div>
            <datalist id="cats">@foreach (\App\Models\Equipment\Item::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category') as $c)<option value="{{ $c }}">@endforeach</datalist>
        </section>
        <section class="card">
            <h2>{{ __('equipment.prices') }}</h2>
            <div class="form-grid">
                <label class="field"><span>{{ __('equipment.fields.day_price') }}</span><input type="number" step="0.01" min="0" name="day_price" value="{{ $v('day_price', $item->day_price_cents !== null ? number_format($item->day_price_cents / 100, 2, '.', '') : '') }}"><small>{{ __('equipment.day_price_hint') }}</small></label>
                <label class="field"><span>{{ __('equipment.fields.price_unit') }}</span>
                    <select name="price_unit"><option value="">—</option>@foreach (\App\Models\Equipment\Item::PRICE_UNITS as $u)<option value="{{ $u }}" @selected($v('price_unit', $item->price_unit) === $u)>{{ __('equipment.units.'.$u) }}</option>@endforeach</select>
                    <small>{{ __('equipment.price_unit_hint') }}</small>
                </label>
                <label class="field"><span>{{ __('equipment.fields.catalog_price') }}</span><input type="number" step="0.01" min="0" name="catalog_price" value="{{ $v('catalog_price', $item->catalog_price_cents !== null ? number_format($item->catalog_price_cents / 100, 2, '.', '') : '') }}"><small>{{ __('equipment.catalog_hint') }}</small></label>
            </div>
        </section>
        <section class="card">
            <h2>{{ __('equipment.transport') }}</h2>
            <div class="form-grid form-grid-3">
                <label class="field"><span>{{ __('equipment.fields.weight_kg') }}</span><input type="number" step="0.01" min="0" name="weight_kg" value="{{ $v('weight_kg', $item->weight_kg) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.origin') }}</span><input name="origin" maxlength="2" value="{{ $v('origin', $item->origin) }}" placeholder="CZ"></label>
                <label class="field"><span>{{ __('equipment.fields.hs_code') }}</span><input name="hs_code" maxlength="20" value="{{ $v('hs_code', $item->hs_code) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.width_m') }}</span><input type="number" step="0.001" min="0" name="width_m" value="{{ $v('width_m', $item->width_m) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.length_m') }}</span><input type="number" step="0.001" min="0" name="length_m" value="{{ $v('length_m', $item->length_m) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.height_m') }}</span><input type="number" step="0.001" min="0" name="height_m" value="{{ $v('height_m', $item->height_m) }}"></label>
            </div>
        </section>
        <section class="card">
            <h2>{{ __('equipment.descriptions') }}</h2>
            <p class="muted small">{{ __('equipment.desc_hint') }}</p>
            <div class="form-grid">
                @foreach (config('cluk.locales') as $code => $lang)
                    <label class="field"><span>{{ $lang }}</span><textarea name="desc[{{ $code }}]" rows="3" maxlength="2000">{{ old('desc.'.$code, $item->descriptions[$code] ?? '') }}</textarea></label>
                @endforeach
            </div>
        </section>
        <section class="card">
            <label class="check"><input type="checkbox" name="in_search" value="1" @checked($v('in_search', $item->in_search))><span>{{ __('equipment.fields.in_search') }}</span></label>
            <label class="check"><input type="checkbox" name="active" value="1" @checked($v('active', $item->active))><span>{{ __('equipment.fields.active') }}<br><small>{{ __('equipment.active_hint') }}</small></span></label>
            <div class="form-actions">
                <a class="btn" href="{{ $item->exists ? route('equipment.show', $item) : route('equipment.index') }}">{{ __('equipment.cancel') }}</a>
                <button class="primary" type="submit">{{ __('equipment.save') }}</button>
            </div>
        </section>
    </form>
</x-layouts.app>
