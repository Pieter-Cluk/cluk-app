@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($fmt = fn ($d) => $d?->timezone(config('app.timezone'))->format('d/m/Y H:i'))
@php($isContainer = in_array($item->kind, ['package', 'case']))
<x-layouts.app :title="$item->number">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('equipment.index') }}">{{ mb_strtoupper(__('nav.items.dryhire')) }}</a> · {{ $item->category }}</div>
            <h1>{{ $item->name }}</h1>
            <p class="muted">{{ $item->number }} · <span class="pill">{{ __('equipment.kinds.'.$item->kind) }}</span>@unless ($item->active) <span class="pill pill-off">{{ __('equipment.inactive') }}</span>@endunless</p>
        </div>
        <div class="inline-form">
            @if ($canEdit && $item->serial && $item->assets->where('status', '!=', 'retired')->isNotEmpty())<a class="btn" href="{{ route('equipment.labels', ['item' => $item->id]) }}" target="_blank" rel="noopener">{{ __('equipment.print_all') }}</a>@endif
            @if ($canEdit)<a class="primary" href="{{ route('equipment.edit', $item) }}">{{ __('equipment.edit') }}</a>@endif
        </div>
    </div>
    @include('partials.flash')
    @if ($codes = session('print'))
        <div class="flash flash-ok"><a href="{{ route('equipment.labels', ['codes' => implode(',', $codes)]) }}" target="_blank" rel="noopener">{{ trans_choice('equipment.print_new', count($codes), ['count' => count($codes)]) }} →</a></div>
    @endif

    <div class="split">
        <div class="stack">
            <section class="card">
                <h2>{{ __('equipment.details') }}</h2>
                <dl class="facts">
                    <dt>{{ __('equipment.fields.category') }}</dt><dd>{{ $item->category ?? '—' }}@if ($item->subcategory) · {{ $item->subcategory }}@endif</dd>
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
                    <div class="bill-head"><b>{{ __('equipment.assets') }}</b> <span class="muted">· {{ $item->assets->count() }}</span></div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>{{ __('equipment.fields.code') }}</th><th>{{ __('equipment.fields.serial_number') }}</th><th>{{ __('equipment.fields.status') }}</th><th>{{ __('equipment.fields.location') }}</th><th>{{ __('equipment.label') }}</th></tr></thead>
                            <tbody>
                                @forelse ($item->assets as $a)
                                    <tr @class(['is-off' => $a->status === 'retired'])>
                                        <td><a href="{{ route('equipment.assets.show', $a) }}"><b>{{ $a->code }}</b></a></td>
                                        <td>{{ $a->serial_number ?? '—' }}</td>
                                        <td><span @class(['pill', 'pill-ok' => $a->status === 'available', 'pill-info' => $a->status === 'out', 'pill-warn' => $a->status === 'repair', 'pill-off' => $a->status === 'retired'])>{{ __('equipment.statuses.'.$a->status) }}</span></td>
                                        <td>{{ $a->location ?? '—' }}</td>
                                        <td class="muted small">{{ $a->label_printed_at ? $a->label_printed_at->format('d/m/Y') : __('equipment.not_printed') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="muted">{{ __('equipment.no_assets') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
</x-layouts.app>
