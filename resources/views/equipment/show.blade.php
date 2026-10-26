@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($fmt = fn ($d) => $d?->timezone(config('app.timezone'))->format('d/m/Y H:i'))
@php($isContainer = in_array($item->kind, ['package', 'case']))
<x-layouts.app :title="$item->number">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('equipment.index') }}">{{ mb_strtoupper(__('nav.items.dryhire')) }}</a> · {{ $item->productCategory?->label() ?? $item->category }}</div>
            <h1>{{ $item->name }}</h1>
            <p class="muted">{{ $item->number }} · <span class="pill">{{ __('equipment.kinds.'.$item->kind) }}</span>@unless ($item->active) <span class="pill pill-off">{{ __('equipment.inactive') }}</span>@endunless</p>
        </div>
        <div class="inline-form">
            @if ($canEdit && $item->serial && $item->assets->where('status', '!=', 'retired')->isNotEmpty())<a class="btn" href="{{ route('equipment.labels', ['item' => $item->id]) }}" target="_blank" rel="noopener">{{ __('equipment.print_all') }}</a>@endif
            @if ($canEdit && ! $item->serial && $item->kind === 'item')
                <form method="get" action="{{ route('equipment.labels') }}" target="_blank" class="inline-form"><input type="hidden" name="bulk" value="{{ $item->id }}">
                    <input type="number" name="qty" min="1" max="500" value="{{ max(1, min(500, (int) $item->stock_qty)) }}" style="width:6em" aria-label="{{ __('warehouse.labels.how_many') }}">
                    <button class="btn" type="submit">{{ __('warehouse.labels.print_n') }}</button></form>
            @endif
            @if ($canEdit)<a class="primary" href="{{ route('equipment.edit', $item) }}">{{ __('equipment.edit') }}</a>@endif
        </div>
    </div>
    @include('partials.flash')
    @if ($canEdit && ! $item->serial && $item->kind === 'item')
        @php($inCases = \App\Models\Equipment\CaseItem::with('container')->where('item_id', $item->id)->where('qty', '>', 0)->get())
        <section class="card">
            <h2>{{ __('equipment.kept.title') }}</h2>
            <form method="post" action="{{ route('equipment.counted.kept', $item) }}" class="inline-form">@csrf
                <input name="position" list="rack-codes" value="{{ old('position', $item->position_code) }}" placeholder="GA1B1" maxlength="30" aria-label="{{ __('equipment.kept.title') }}" style="width:9em">
                <button class="btn" type="submit">{{ __('equipment.kept.go') }}</button>
            </form>
            @error('position')<p class="txt-danger small">{{ $message }}</p>@enderror
            <p class="muted small">{{ __('equipment.kept.hint') }}</p>
            @include('equipment._place-codes', ['id' => 'rack-codes', 'cases' => false])
        </section>
        <section class="card">
            <h2>{{ __('equipment.place.counted_title') }}</h2>
            @if ($inCases->isNotEmpty())<p>{{ $inCases->map(fn ($c) => $c->qty.' × '.__('warehouse.carriers.in_it', ['carrier' => $c->container->code]))->implode(' · ') }} <span class="muted">· {{ trans_choice('warehouse.labels.in_stock', $item->stock_qty, ['n' => $item->stock_qty]) }}</span></p>@endif
            <form method="post" action="{{ route('equipment.counted.place', $item) }}" class="inline-form">@csrf
                <input type="number" name="qty" value="1" min="-10000" max="10000" style="width:6em" aria-label="{{ __('warehouse.labels.how_many') }}">
                <input name="place" list="case-codes" placeholder="BOX-01" required aria-label="{{ __('equipment.place.title') }}" style="width:9em">
                <button class="btn" type="submit">{{ __('equipment.place.counted_go') }}</button>
            </form>
            @error('place')<p class="txt-danger small">{{ $message }}</p>@enderror
            <p class="muted small">{{ __('equipment.place.counted_hint') }}</p>
        </section>
    @endif
    @include('equipment._place-codes', ['cases' => $item->kind !== 'case'])
    @if (! $item->serial)@include('equipment._place-codes', ['id' => 'case-codes', 'racks' => false])@endif
    @if ($codes = session('print'))
        <div class="flash flash-ok"><a href="{{ route('equipment.labels', ['codes' => implode(',', $codes)]) }}" target="_blank" rel="noopener">{{ trans_choice('equipment.print_new', count($codes), ['count' => count($codes)]) }} →</a></div>
    @endif

    <div class="split">
        <div class="stack">
            <section class="card">
                <h2>{{ __('equipment.details') }}</h2>
                <dl class="facts">
                    <dt>{{ __('equipment.fields.category') }}</dt><dd>@if ($item->productSubcategory){{ $item->productCategory?->label() }} · {{ $item->productSubcategory->label() }}@else{{ $item->category ?? '—' }}@if ($item->subcategory) · {{ $item->subcategory }}@endif @endif
                        @foreach ($item->optionLabels() as $l)<span class="chip-attr">{{ $l }}</span>@endforeach @foreach ($item->tags as $t)<span class="pill">{{ $t->label() }}</span>@endforeach</dd>
                    <dt>{{ __('equipment.fields.brand') }} / {{ __('equipment.fields.type') }}</dt><dd>{{ trim($item->brand.' '.$item->type) ?: '—' }}</dd>
                    @if ($item->kind === 'service')
                        <dt>{{ __('equipment.cols.price') }}</dt><dd>{{ $eur($item->day_price_cents) }} {{ __('equipment.units.'.$item->price_unit) }} · {{ __('equipment.excl_vat') }}</dd>
                    @else
                        <dt>{{ __('equipment.fields.day_price') }}</dt><dd>{{ $eur($item->day_price_cents) }} {{ __('equipment.per_day') }} · {{ __('equipment.factor_note') }}</dd>
                        <dt>{{ __('equipment.fields.catalog_price') }}</dt><dd>{{ $item->catalog_price_cents !== null ? $eur($item->catalog_price_cents) : '—' }}</dd>
                        <dt>{{ __('equipment.fields.tracking') }}</dt><dd>{{ $item->serial ? __('equipment.serial_yes') : __('equipment.serial_no') }}</dd>
                        <dt>{{ __('equipment.cols.stock') }}</dt><dd>{{ $item->availableQty() }} {{ __('equipment.available') }}@if ($item->kind !== 'package') · {{ $item->totalQty() }} {{ __('equipment.owned') }}@endif</dd>
                        <dt>{{ __('equipment.fields.weight_kg') }}</dt><dd>{{ $item->weight_kg !== null ? rtrim(rtrim(number_format($item->weight_kg, 2, ',', ''), '0'), ',').' kg' : '—' }}@if ($isContainer && $item->totalWeight()) · {{ __('equipment.total_weight', ['kg' => rtrim(rtrim(number_format($item->totalWeight(), 2, ',', ''), '0'), ',')]) }}@endif</dd>
                        <dt>{{ __('equipment.dimensions') }}</dt><dd>@if ($item->volume()){{ $item->width_m }} × {{ $item->length_m }} × {{ $item->height_m }} m · {{ number_format($item->volume(), 3, ',', '') }} m³@else — @endif</dd>
                        <dt>{{ __('equipment.fields.origin') }} · HS</dt><dd>{{ $item->origin ?? '—' }} · {{ $item->hs_code ?? '—' }}</dd>
                    @endif
                    <dt>{{ __('equipment.fields.unit') }}</dt><dd>{{ $item->unit }}</dd>
                    <dt>{{ __('equipment.fields.in_search') }}</dt><dd>{{ $item->in_search ? __('companies.yes') : __('companies.no') }}</dd>
                    <dt>{{ __('equipment.fields.on_website') }}</dt><dd>{{ $item->on_website ? __('companies.yes') : __('companies.no') }}</dd>
                </dl>
            </section>

            <section class="card">
                <h2>{{ __('equipment.descriptions') }}</h2>
                @foreach (config('cluk.locales') as $code => $lang)
                    <div class="p-line"><b>{{ strtoupper($code) }}</b><span @class(['muted' => empty($item->descriptions[$code])])>{{ $item->descriptions[$code] ?? __('equipment.no_text', ['lang' => $lang]) }}</span></div>
                @endforeach
            </section>

            @if ($item->kind !== 'service')
                <section class="card">
                    <h2>{{ $isContainer ? __('equipment.contents') : __('equipment.kit') }}</h2>
                    <p class="muted small">{{ $isContainer ? __('equipment.contents_hint_'.$item->kind) : __('equipment.kit_hint') }}</p>
                    @forelse ($isContainer ? $item->contents : $item->kit as $c)
                        <div class="p-line">
                            <span><b>{{ $c->pivot->qty }} ×</b> <a href="{{ route('equipment.show', $c) }}">{{ $c->number }}</a> · {{ $c->name }}</span>
                            @if ($canEdit)
                                <form method="post" action="{{ route('equipment.components.destroy', [$item, $c]) }}">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('equipment.remove') }}</button></form>
                            @endif
                        </div>
                    @empty
                        <p class="muted">{{ __('equipment.none') }}</p>
                    @endforelse
                    @if ($canEdit)
                        <form method="post" action="{{ route('equipment.components.store', $item) }}" class="inline-form mt">
                            @csrf
                            <input type="number" name="qty" value="1" min="1" max="999" style="width:80px" aria-label="{{ __('equipment.qty') }}">
                            <select name="child_id" required aria-label="{{ __('equipment.fields.name') }}">
                                <option value="">{{ __('equipment.choose_item') }}</option>
                                @foreach ($options as $o)<option value="{{ $o->id }}">{{ $o->number }} · {{ $o->name }}</option>@endforeach
                            </select>
                            <button class="btn" type="submit">{{ __('equipment.add') }}</button>
                        </form>
                    @endif
                </section>
            @endif

            @if ($item->serial)
                <section class="card card-flush">
                    <form method="post" action="{{ route('equipment.assets.place', $item) }}" class="lbl-pick">@csrf
                    <div class="bill-head lbl-bar"><b>{{ __('equipment.assets') }}</b> <span class="muted">· {{ $item->assets->count() }}</span>
                        @if ($canEdit && $item->assets->isNotEmpty())
                            <label class="check"><input type="checkbox" data-all><span>{{ __('warehouse.labels.select_all') }}</span></label>
                            <span class="muted" data-count>0</span>
                            <button class="btn" type="submit" data-print disabled formaction="{{ route('equipment.labels') }}" formmethod="get" formtarget="_blank"
                                onclick="var t = this.form.querySelector('[name=_token]'); t.disabled = true; setTimeout(function () { t.disabled = false; }, 500);">{{ __('warehouse.labels.print') }}</button>
                            <span class="inline-form"><input name="place" list="place-codes" placeholder="{{ __('equipment.place.placeholder') }}" aria-label="{{ __('equipment.place.title') }}" style="width:11em">
                                <button class="btn" type="submit" data-print disabled>{{ __('equipment.place.go') }}</button></span>
                        @endif
                    </div>
                    @error('place')<p class="flash flash-err">{{ $message }}</p>@enderror
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr>@if ($canEdit)<th class="w-check"></th>@endif<th>{{ __('equipment.fields.code') }}</th><th>{{ __('equipment.fields.serial_number') }}</th><th>{{ __('equipment.fields.status') }}</th><th>{{ __('equipment.fields.location') }}</th><th>{{ __('equipment.label') }}</th></tr></thead>
                            <tbody>
                                @forelse ($item->assets as $a)
                                    <tr @class(['is-off' => $a->status === 'retired'])>
                                        @if ($canEdit)<td>@if ($a->status !== 'retired')<input type="checkbox" name="assets[]" value="{{ $a->id }}" aria-label="{{ $a->code }}">@endif</td>@endif
                                        <td><a href="{{ route('equipment.assets.show', $a) }}"><b>{{ $a->code }}</b></a></td>
                                        <td>{{ $a->serial_number ?? '—' }}</td>
                                        <td><span @class(['pill', 'pill-ok' => $a->status === 'available', 'pill-info' => $a->status === 'out', 'pill-warn' => $a->status === 'repair', 'pill-off' => in_array($a->status, ['retired', 'label'], true)])>{{ __('equipment.statuses.'.$a->status) }}</span></td>
                                        <td>{{ $a->location ?? '—' }}@if ($a->case_id) <small class="muted">· {{ __('warehouse.carriers.in_it', ['carrier' => $a->container?->code]) }}</small>@endif</td>
                                        <td class="muted small">{{ $a->label_printed_at ? $a->label_printed_at->format('d/m/Y') : __('equipment.not_printed') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="muted">{{ __('equipment.no_assets') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    </form>
                    @if ($canEdit)<p class="muted small pad">{{ __('equipment.place.hint') }}</p>@endif
                    @if ($canEdit)
                        <form method="post" action="{{ route('equipment.assets.store', $item) }}" class="form-grid pad">
                            @csrf
                            <h3 class="h3 span-2">{{ __('equipment.add_assets') }}</h3>
                            <label class="field"><span>{{ __('equipment.count') }}</span><input type="number" name="count" min="1" max="200" value="{{ old('count', 1) }}"><small>{{ __('equipment.count_hint') }}</small></label>
                            <label class="field"><span>{{ __('equipment.fields.serial_number') }}</span><textarea name="serials" rows="3" placeholder="{{ __('equipment.serials_hint') }}">{{ old('serials') }}</textarea></label>
                            <label class="field"><span>{{ __('equipment.fields.purchase_date') }}</span><input type="date" name="purchase_date" value="{{ old('purchase_date') }}"></label>
                            <label class="field"><span>{{ __('equipment.fields.location') }}</span><input name="location" maxlength="60" value="{{ old('location') }}"></label>
                            @if ($canMoney)
                                <label class="field"><span>{{ __('equipment.fields.purchase_price') }}</span><input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price') }}"></label>
                                <label class="field"><span>{{ __('equipment.fields.supplier') }}</span><input name="supplier" maxlength="160" value="{{ old('supplier') }}"></label>
                            @endif
                            <div class="span-2"><button class="btn" type="submit">{{ __('equipment.add_assets_btn') }}</button></div>
                        </form>
                        <form method="post" action="{{ route('equipment.assets.reserve', $item) }}" class="form-grid pad">
                            @csrf
                            <h3 class="h3 span-2">{{ __('equipment.reserve.title') }}</h3>
                            <p class="muted small span-2">{{ __('equipment.reserve.hint') }}</p>
                            <label class="field"><span>{{ __('equipment.reserve.how_many') }}</span><input type="number" name="labels" min="1" max="200" value="20"></label>
                            <div class="span-2"><button class="btn" type="submit">{{ __('equipment.reserve.go') }}</button></div>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        <aside class="stack">
            <section class="card">
                <h2>{{ __('pictures.item_title') }}</h2>
                @php($photos = $item->photos)
                @if ($photos->isNotEmpty())
                    <img class="photo" src="{{ route('media.item', $photos->first()) }}" alt="{{ $item->name }}">
                    @if ($photos->count() > 1)
                        <div class="thumbs">
                            @foreach ($photos->skip(1) as $p)
                                <figure class="thumb-fig">
                                    <img src="{{ route('media.item', $p) }}" alt="" loading="lazy">
                                    @if ($canEdit)
                                        <figcaption>
                                            <form method="post" action="{{ route('equipment.photos.main', [$item, $p]) }}">@csrf<button class="linkbtn" type="submit">{{ __('pictures.make_main') }}</button></form>
                                            <form method="post" action="{{ route('equipment.photos.destroy', [$item, $p]) }}">@csrf @method('DELETE')<button class="linkbtn danger" type="submit" aria-label="{{ __('pictures.remove') }}">✕</button></form>
                                        </figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    @endif
                    @if ($canEdit)
                        <form method="post" action="{{ route('equipment.photos.destroy', [$item, $photos->first()]) }}" class="mt">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('pictures.remove_main') }}</button></form>
                    @endif
                @else
                    <p class="muted">{{ __('equipment.no_photo') }}</p>
                @endif
                @if ($canEdit)
                    <form method="post" action="{{ route('equipment.photos.store', $item) }}" enctype="multipart/form-data" class="stack-sm mt">
                        @csrf
                        <input type="file" name="photos[]" accept="image/*" multiple required aria-label="{{ __('pictures.item_title') }}">
                        <button class="btn" type="submit">{{ __('pictures.upload') }}</button>
                        <small class="muted">{{ __('pictures.item_hint') }}</small>
                    </form>
                @endif
            </section>
            @if ($usedIn->isNotEmpty())
                <section class="card">
                    <h2>{{ __('equipment.used_in') }}</h2>
                    @foreach ($usedIn as $u)<div class="p-line"><a href="{{ route('equipment.show', $u) }}">{{ $u->number }}</a><span class="muted">{{ $u->name }}</span></div>@endforeach
                </section>
            @endif
            <section class="card">
                <h2>{{ __('users.edit.activity') }}</h2>
                @forelse ($activity as $log)
                    <div class="log-line"><small class="muted">{{ $fmt($log->created_at) }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small><div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div></div>
                @empty
                    <p class="muted">{{ __('users.edit.no_activity') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
    <script>
        document.querySelectorAll('.lbl-pick').forEach(function (f) {
            var boxes = f.querySelectorAll('tbody input[type=checkbox]'), all = f.querySelector('[data-all]'), count = f.querySelector('[data-count]'), btns = f.querySelectorAll('[data-print]');
            if (!all) return;
            function update() {
                var n = f.querySelectorAll('tbody input:checked').length;
                count.textContent = @js(__('warehouse.labels.selected')).replace(':n', n);
                btns.forEach(function (b) { b.disabled = n === 0; });
                all.checked = n > 0 && n === boxes.length;
            }
            all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
            boxes.forEach(function (b) { b.addEventListener('change', update); });
            update();
        });
    </script>
</x-layouts.app>
