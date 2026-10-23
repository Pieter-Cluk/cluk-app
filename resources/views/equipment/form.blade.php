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
                <label class="field"><span>{{ __('equipment.fields.number') }}</span><input name="number" required maxlength="40" value="{{ $v('number', $item->number) }}" placeholder="AUDSPEQSCKLA181" data-codes="1"><small>{{ __('equipment.number_hint') }}</small>@unless ($item->exists)<button type="button" class="linkbtn js-suggest">{{ __('equipment.suggest_number') }}</button>@endunless</label>
                <label class="field"><span>{{ __('equipment.cols.kind') }}</span>
                    <select name="kind">@foreach (\App\Models\Equipment\Item::KINDS as $k)<option value="{{ $k }}" @selected($v('kind', $item->kind) === $k)>{{ __('equipment.kinds.'.$k) }}</option>@endforeach</select>
                </label>
                <label class="field span-2"><span>{{ __('equipment.fields.name') }}</span><input name="name" required maxlength="255" value="{{ $v('name', $item->name) }}"></label>
                @php($catId = (int) $v('category_id', $item->category_id))
                @php($subId = (int) $v('subcategory_id', $item->subcategory_id))
                <label class="field"><span>{{ __('equipment.fields.category') }}</span>
                    <select name="category_id" class="js-cat"><option value="">—</option>@foreach ($categories as $c)@if ($c->active || $c->id === $catId)<option value="{{ $c->id }}" data-code="{{ $c->code }}" @selected($catId === $c->id)>{{ $c->label() }}</option>@endif @endforeach</select>
                    @error('category_id')<small class="error">{{ $message }}</small>@enderror
                </label>
                <label class="field"><span>{{ __('equipment.fields.subcategory') }}</span>
                    <select name="subcategory_id" class="js-sub"><option value="">—</option>@foreach ($categories as $c)@foreach ($c->subcategories as $sc)@if ($sc->active || $sc->id === $subId)<option value="{{ $sc->id }}" data-cat="{{ $c->id }}" data-code="{{ $sc->code }}" @selected($subId === $sc->id)>{{ $sc->label() }}</option>@endif @endforeach @endforeach</select>
                    @error('subcategory_id')<small class="error">{{ $message }}</small>@enderror
                    @if ($item->exists && $item->legacy_subcategory)<small class="muted">{{ __('products.was', ['old' => trim($item->legacy_category.' › '.$item->legacy_subcategory, ' ›')]) }}</small>@endif
                </label>
                @php($chosen = collect(old('attr', null) ?? $item->options->groupBy('attribute_id')->map(fn ($g) => $g->pluck('id')->all())->mapWithKeys(fn ($ids, $aid) => [$attrList->firstWhere('id', $aid)?->key => $ids])->all()))
                @foreach ($attrList as $a)
                    @php($mine = collect((array) ($chosen[$a->key] ?? []))->map(fn ($x) => (int) $x)->all())
                    <div class="field js-attr" data-subs="{{ $a->subcategories->pluck('id')->join(',') }}" hidden>
                        <span>{{ $a->label() }}@if ($a->unit) ({{ $a->unit }})@endif</span>
                        @if ($a->multiple)
                            <div class="checks-inline">@foreach ($a->options as $o)<label class="check"><input type="checkbox" name="attr[{{ $a->key }}][]" value="{{ $o->id }}" @checked(in_array($o->id, $mine, true))><span>{{ $o->display() }}</span></label>@endforeach</div>
                        @else
                            <select name="attr[{{ $a->key }}]" aria-label="{{ $a->label() }}"><option value="">—</option>@foreach ($a->options as $o)<option value="{{ $o->id }}" @selected(in_array($o->id, $mine, true))>{{ $o->display() }}</option>@endforeach</select>
                        @endif
                        @error('attr.'.$a->key)<small class="error">{{ $message }}</small>@enderror
                    </div>
                @endforeach
                <label class="field"><span>{{ __('equipment.fields.brand') }}</span><input name="brand" maxlength="80" value="{{ $v('brand', $item->brand) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.type') }}</span><input name="type" maxlength="255" value="{{ $v('type', $item->type) }}"></label>
                <label class="field"><span>{{ __('equipment.fields.unit') }}</span><input name="unit" required maxlength="12" value="{{ $v('unit', $item->unit ?? 'pcs') }}"></label>
                <label class="check"><input type="checkbox" name="serial" value="1" @checked($v('serial', $item->serial))><span>{{ __('equipment.fields.serial') }}<br><small>{{ __('equipment.serial_hint') }}</small></span></label>
                <label class="field"><span>{{ __('equipment.fields.stock_qty') }}</span><input type="number" name="stock_qty" min="0" value="{{ $v('stock_qty', $item->stock_qty) }}"><small>{{ __('equipment.stock_hint') }}</small></label>
            </div>
            @if ($tags->isNotEmpty())
                <div class="field mt"><span>{{ __('products.tags') }}</span>
                    @php($myTags = collect(old('tags', $item->exists ? $item->tags->pluck('id')->all() : []))->map(fn ($x) => (int) $x)->all())
                    <div class="checks-inline">@foreach ($tags as $t)<label class="check"><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked(in_array($t->id, $myTags, true))><span>{{ $t->label() }}</span></label>@endforeach</div>
                </div>
            @endif
            <label class="field mt"><span>{{ __('products.search_words') }}</span><textarea name="search_words" rows="2" maxlength="1000" placeholder="{{ __('products.search_words_placeholder') }}">{{ $v('search_words', $item->search_words) }}</textarea><small>{{ __('products.search_words_hint') }}</small></label>
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
            <label class="check"><input type="checkbox" name="on_website" value="1" @checked($v('on_website', $item->on_website)) @disabled(! auth()->user()->hasRight('equipment.website'))><span>{{ __('equipment.fields.on_website') }}<br><small>{{ __('equipment.on_website_hint') }}</small></span></label>
            <label class="check"><input type="checkbox" name="active" value="1" @checked($v('active', $item->active))><span>{{ __('equipment.fields.active') }}<br><small>{{ __('equipment.active_hint') }}</small></span></label>
            <div class="form-actions">
                <a class="btn" href="{{ $item->exists ? route('equipment.show', $item) : route('equipment.index') }}">{{ __('equipment.cancel') }}</a>
                <button class="primary" type="submit">{{ __('equipment.save') }}</button>
            </div>
        </section>
    </form>
    <script>
    (() => {
        // Subcategories follow the category; attributes show only for the subcategories they apply to.
        const cat = document.querySelector('.js-cat'), sub = document.querySelector('.js-sub');
        const attrs = document.querySelectorAll('.js-attr');
        const filterSubs = () => {
            sub.querySelectorAll('option[data-cat]').forEach(o => { o.hidden = cat.value !== '' && o.dataset.cat !== cat.value; });
            if (sub.selectedOptions[0]?.hidden) sub.value = '';
        };
        const showAttrs = () => attrs.forEach(el => {
            const on = sub.value !== '' && el.dataset.subs.split(',').includes(sub.value);
            el.hidden = !on;
            el.querySelectorAll('select, input').forEach(i => { i.disabled = !on; });
        });
        cat.addEventListener('change', () => { filterSubs(); showAttrs(); });
        sub.addEventListener('change', () => { const o = sub.selectedOptions[0]; if (o?.dataset.cat) cat.value = o.dataset.cat; filterSubs(); showAttrs(); });
        filterSubs(); showAttrs();

        // New items: suggest the number from the codes of the lists + brand + type. An existing number never changes.
        const f = document.querySelector('[data-codes]');
        if (!f || @js($item->exists)) return;
        const val = n => (f.form.querySelector('[name=' + n + ']')?.value || '').trim();
        const clean = s => s.toUpperCase().replace(/[^A-Z0-9&]/g, '');
        const suggest = () => (cat.selectedOptions[0]?.dataset.code || '') + (sub.selectedOptions[0]?.dataset.code || '') + clean(val('brand')).slice(0, 3) + clean(val('type')).slice(0, 6);
        document.querySelector('.js-suggest')?.addEventListener('click', () => { f.value = suggest(); });
        [cat, sub, f.form.querySelector('[name=brand]'), f.form.querySelector('[name=type]')].forEach(el => el?.addEventListener('change', () => { if (!f.dataset.touched) f.value = suggest(); }));
        f.addEventListener('input', () => { f.dataset.touched = '1'; });
    })();
    </script>
</x-layouts.app>
