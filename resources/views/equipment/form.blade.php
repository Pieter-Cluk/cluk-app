@php($v = fn ($k, $d = null) => old($k, $d))
<x-layouts.app :title="$item->exists ? $item->number : __('equipment.new')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('equipment.index') }}">{{ mb_strtoupper(__('nav.items.dryhire')) }}</a></div>
            <h1>{{ $item->exists ? __('equipment.edit_title', ['number' => $item->number]) : __('equipment.new') }}</h1>
        </div>
    </div>
    @include('partials.flash')
    <form method="post" action="{{ $item->exists ? route('equipment.update', $item) : route('equipment.store') }}" class="stack" enctype="multipart/form-data">
        @csrf @if ($item->exists) @method('PUT') @endif
        <section class="card">
            <h2>{{ __('equipment.details') }}</h2>
            <div class="form-grid">
                <label class="field"><span>{{ __('equipment.fields.number') }}</span><input name="number" required maxlength="40" value="{{ $v('number', $item->number) }}" placeholder="AUDSPEQSCKLA181" data-codes="{{ json_encode(config('equipment_codes')) }}"><small>{{ __('equipment.number_hint') }}</small>@unless ($item->exists)<button type="button" class="linkbtn js-suggest">{{ __('equipment.suggest_number') }}</button>@endunless</label>
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
        @unless ($item->exists)
            <section class="card">
                <h2>{{ __('pictures.item_title') }}</h2>
                <input type="file" name="photos[]" accept="image/*" multiple aria-label="{{ __('pictures.item_title') }}">
                <p class="muted small">{{ __('pictures.item_hint') }}</p>
            </section>
        @endunless
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
    @unless ($item->exists)
        <script>
        (() => {
            const f = document.querySelector('[data-codes]');
            if (!f) return;
            const codes = JSON.parse(f.dataset.codes);
            const val = n => (f.form.querySelector('[name=' + n + ']')?.value || '').trim();
            const clean = s => s.toUpperCase().replace(/[^A-Z0-9&]/g, '');
            const code = (map, s, len) => map[s] || clean(s).slice(0, len);
            const suggest = () => code(codes.categories, val('category'), 3) + code(codes.subcategories, val('subcategory'), 3) + clean(val('brand')).slice(0, 3) + clean(val('type')).slice(0, 6);
            document.querySelector('.js-suggest')?.addEventListener('click', () => { f.value = suggest(); });
            ['category', 'subcategory', 'brand', 'type'].forEach(n => f.form.querySelector('[name=' + n + ']')?.addEventListener('change', () => { if (!f.dataset.touched) f.value = suggest(); }));
            f.addEventListener('input', () => { f.dataset.touched = '1'; });
        })();
        </script>
    @endunless
</x-layouts.app>
