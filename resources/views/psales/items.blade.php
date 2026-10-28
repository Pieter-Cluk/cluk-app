@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($pct = fn ($bp) => rtrim(rtrim(number_format($bp / 100, 1, '.', ''), '0'), '.').'%')
<x-layouts.app :title="__('nav.items.productsales')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }}</div>
            <h1>{{ __('nav.items.productsales') }}</h1>
            <p class="muted">{{ __('psales.items.intro') }}</p>
        </div>
        @if ($canEdit)<a class="primary" href="{{ route('psales.items', ['new' => 1]) }}">{{ __('psales.items.new') }}</a>@endif
    </div>
    @include('partials.flash')
    <form method="get" class="inline-form mt">@if ($cat)<input type="hidden" name="cat" value="{{ $cat }}">@endif
        <input name="q" value="{{ $q }}" placeholder="{{ __('psales.items.search') }}" aria-label="{{ __('psales.items.search') }}" class="ps-search">
        <select name="brand" aria-label="{{ __('psales.items.brand') }}" onchange="this.form.submit()"><option value="">{{ __('psales.items.all_brands') }}</option>@foreach ($brands as $b)<option value="{{ $b->id }}" @selected($brand === $b->id)>{{ $b->name }}@if ($b->kind !== 'other') · {{ __('products.brand_kinds.'.$b->kind) }}@endif</option>@endforeach</select>
        <button class="btn" type="submit">{{ __('psales.items.find') }}</button></form>
    <nav class="chips mt">
        <a @class(['chip', 'chip-on' => ! $cat]) href="{{ route('psales.items', array_filter(['q' => $q, 'brand' => $brand])) }}">{{ __('psales.items.all') }}</a>
        @foreach ($categories as $c)@if ($c->active)<a @class(['chip', 'chip-on' => $cat === $c->id]) href="{{ route('psales.items', array_filter(['cat' => $c->id, 'q' => $q, 'brand' => $brand])) }}">{{ $c->label() }}</a>@endif @endforeach
    </nav>

    <nav class="chips mt" aria-label="{{ __('psales.web.filter') }}">
        <a @class(['chip', 'chip-on' => ! $web]) href="{{ route('psales.items', array_filter(['cat' => $cat, 'q' => $q, 'brand' => $brand])) }}">{{ __('psales.web.all') }}</a>
        @foreach (['online', 'partial', 'off'] as $w)<a @class(['chip', 'chip-on' => $web === $w]) href="{{ route('psales.items', array_filter(['cat' => $cat, 'q' => $q, 'brand' => $brand, 'web' => $w])) }}"><i class="web-dot web-{{ $w }}"></i>{{ __('psales.web.'.$w) }}</a>@endforeach
    </nav>

    <div class="split mt">
        <section class="card card-flush">
            <ul class="inbox">
                @forelse ($items as $it)
                    <li><a @class(['inbox-item', 'is-on' => $sel?->is($it) && ! $new]) href="{{ route('psales.items', array_filter(['item' => $it->id, 'cat' => $cat, 'q' => $q, 'brand' => $brand])) }}">
                        @php($ws = \App\Services\Website\SalesCatalogue::status($it))
                        <span class="inbox-main"><b><i class="web-dot web-{{ $ws['level'] }}" role="img" aria-label="{{ __('psales.web.'.$ws['level']) }}" title="{{ __('psales.web.'.$ws['level']) }}@foreach ($ws['reasons'] as $r) · {{ __('psales.web.why.'.$r) }}@endforeach"></i>{{ $it->name() }}</b><small class="muted">{{ $it->number }}@if ($it->manufacturer_code) · {{ $it->manufacturer_code }}@endif · {{ $it->documents_count ? trans_choice('psales.items.pdfs', $it->documents_count, ['n' => $it->documents_count]) : __('psales.items.no_pdfs') }}</small></span>
                        <span class="inbox-side"><b>{{ $eur($it->list_price_cents) }}</b><small class="muted">{{ $it->stock_qty > 0 ? __('psales.items.in_stock', ['n' => $it->stock_qty]) : __('psales.items.order_it') }}</small></span>
                    </a></li>
                @empty
                    <li class="muted inbox-empty">{{ __('psales.items.none') }}</li>
                @endforelse
            </ul>
            <x-pager :items="$items" />
        </section>

        <aside class="stack">
            @if ($new && $canEdit)
                <section class="card">
                    <h2>{{ __('psales.items.new') }}</h2>
                    <form method="post" action="{{ route('psales.items.store') }}" class="stack-sm">@csrf
                        @include('psales._item-fields', ['it' => new \App\Models\ProductSales\SalesItem(['unit' => 'piece'])])
                        <div><button class="primary" type="submit">{{ __('psales.items.create') }}</button></div>
                    </form>
                </section>
            @elseif ($sel)
                <section class="card">
                    <div class="ps-head">
                        @if ($sel->photo_path)<img class="ps-photo" src="{{ route('psales.items.photo.show', [$sel, 'v' => substr(md5($sel->photo_path), 0, 8)]) }}" alt="">@else<div class="ps-photo ps-nophoto">{{ mb_substr($sel->brand ?: $sel->type, 0, 2) }}</div>@endif
                        <div><div class="kicker">{{ $sel->number }}</div><h2>{{ $sel->name() }}</h2><p class="muted">{{ $sel->description }}</p></div>
                    </div>
                    @php($ss = \App\Services\Website\SalesCatalogue::status($sel))
                    <p class="web-status web-status-{{ $ss['level'] }}"><i class="web-dot web-{{ $ss['level'] }}"></i><b>{{ __('psales.web.'.$ss['level']) }}</b>@foreach ($ss['reasons'] as $r) · {{ __('psales.web.why.'.$r) }}@endforeach
                        @if ($ss['level'] !== 'off') · <a href="{{ route('site.sales.product', \App\Services\Website\SalesCatalogue::slug($sel)) }}" target="_blank" rel="noopener">{{ __('psales.web.open') }} ↗</a>@endif</p>
                    <dl class="facts">
                        @if ($sel->brandRef)<dt>{{ __('psales.items.brand') }}</dt><dd>{{ $sel->brandRef->name }}@if ($sel->brandRef->kind !== 'other') <span class="pill">{{ __('products.brand_kinds.'.$sel->brandRef->kind) }}</span>@endif</dd>@endif
                        <dt>{{ __('psales.items.codes') }}</dt><dd>{{ $sel->number }}@if ($sel->manufacturer_code) · {{ __('psales.items.code_mfr', ['code' => $sel->manufacturer_code]) }}@endif @if ($sel->ean) · EAN {{ $sel->ean }}@endif</dd>
                        <dt>{{ __('psales.items.list_price') }}</dt><dd><b>{{ $eur($sel->list_price_cents) }}</b> {{ __('psales.excl') }}</dd>
                        <dt>{{ __('psales.items.stock') }}</dt><dd>{{ $sel->stock_qty }} {{ __('psales.units.'.$sel->unit) }}@if ($sel->reserved()) · {{ __('psales.items.reserved', ['n' => $sel->reserved()]) }}@endif @if ($sel->min_stock) · {{ __('psales.items.min', ['n' => $sel->min_stock]) }}@endif</dd>
                        <dt>{{ __('psales.items.category') }}</dt><dd>@if ($sel->productCategory){{ $sel->productCategory->label() }}@if ($sel->productSubcategory) · {{ $sel->productSubcategory->label() }}@endif @else{{ $sel->category ?? '—' }}@endif
                            @foreach ($sel->optionLabels() as $l)<span class="chip-attr">{{ $l }}</span>@endforeach @foreach ($sel->tags as $t)<span class="pill">{{ $t->label() }}</span>@endforeach</dd>
                    </dl>
                    @if ($canEdit)
                        <details class="mt"><summary>{{ __('psales.items.edit') }}</summary>
                            <form method="post" action="{{ route('psales.items.update', $sel) }}" class="stack-sm mt">@csrf @method('PUT')
                                @include('psales._item-fields', ['it' => $sel])
                                <div><button class="primary" type="submit">{{ __('psales.save') }}</button></div>
                            </form>
                            <form method="post" action="{{ route('psales.items.photo', $sel) }}" enctype="multipart/form-data" class="inline-form mt">@csrf<input type="file" name="photo" accept="image/*" required aria-label="{{ __('psales.items.photo') }}"><button class="btn" type="submit">{{ __('psales.items.photo') }}</button></form>
                        </details>
                    @else
                        <p class="muted small">{{ __('psales.items.no_right') }}</p>
                    @endif
                </section>

                <section class="card">
                    <h2>{{ __('psales.items.suppliers') }}</h2>
                    @if ($sel->suppliers->isEmpty())<p class="muted">{{ __('psales.items.no_suppliers') }}</p>@endif
                    @foreach ($sel->suppliers as $s)
                        <div class="p-line"><span><b>{{ $s->name }}</b>@if ($s->pivot->preferred) <span class="pill pill-ok">{{ __('psales.items.preferred') }}</span>@endif<br><small class="muted">{{ __('psales.items.discount_lead', ['disc' => $pct($s->pivot->discount_bp), 'days' => $s->pivot->lead_days]) }}@if ($s->pivot->supplier_ref) · {{ __('psales.items.code_supplier', ['code' => $s->pivot->supplier_ref]) }}@endif</small></span>
                            @if ($seeCost)<span>{{ $eur($sel->costCents($s)) }}</span>@endif</div>
                    @endforeach
                    @if ($canEdit)
                        <details class="mt"><summary>{{ __('psales.items.add_supplier') }}</summary>
                            <form method="post" action="{{ route('psales.items.supplier', $sel) }}" class="stack-sm mt">@csrf
                                <label class="field"><span>{{ __('psales.items.supplier') }}</span><select data-pick name="company_id" required>@foreach ($suppliers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
                                <div class="inline-form"><label class="field"><span>{{ __('psales.items.discount') }} %</span><input type="number" step="0.1" min="0" max="100" name="discount" value="0" class="w-sm"></label>
                                    <label class="field"><span>{{ __('psales.items.lead') }}</span><input type="number" min="0" max="365" name="lead_days" value="5" class="w-sm"></label>
                                    <label class="field"><span>{{ __('psales.items.supplier_ref') }}</span><input name="supplier_ref" maxlength="60"></label></div>
                                <small class="muted">{{ __('psales.items.supplier_ref_hint') }}</small>
                                <input type="hidden" name="preferred" value="0"><label class="check"><input type="checkbox" name="preferred" value="1"> {{ __('psales.items.preferred') }}</label>
                                <div><button class="btn" type="submit">{{ __('psales.save') }}</button></div>
                            </form>
                        </details>
                    @endif
                    <p class="muted small">{{ __('psales.items.suppliers_hint') }}</p>
                </section>

                @if ($seeCost && $sel->suppliers->isNotEmpty())
                    @php($cost = $sel->costCents())
                    <section class="card">
                        <h2>{{ __('psales.items.rules') }}</h2>
                        <dl class="facts">
                            <dt>{{ __('psales.items.cost') }}</dt><dd>{{ $eur($cost) }}</dd>
                            <dt>{{ __('psales.items.margin_list') }}</dt><dd>{{ $sel->list_price_cents ? $pct(($sel->list_price_cents - $cost) / $sel->list_price_cents * 10000) : '—' }}</dd>
                            <dt>{{ __('psales.items.floor') }}</dt><dd>{{ $eur((int) ceil($cost / 0.9)) }}</dd>
                            <dt>{{ __('psales.items.max_disc') }}</dt><dd>{{ $sel->list_price_cents ? $pct(max(0, (1 - ($cost / 0.9) / $sel->list_price_cents) * 10000)) : '—' }}</dd>
                        </dl>
                    </section>
                @endif

                <section class="card">
                    <h2>{{ __('psales.items.documents') }} <span class="muted small">{{ trans_choice('psales.items.pdfs', $sel->documents->count(), ['n' => $sel->documents->count()]) }}</span></h2>
                    <form method="post" action="{{ route('psales.items.send', $sel) }}" id="send-docs">@csrf
                        @forelse ($sel->documents as $d)
                            <div class="p-line"><label class="check"><input type="checkbox" name="docs[]" value="{{ $d->id }}"> <span><a href="{{ route('psales.items.doc', [$sel, $d]) }}" target="_blank" rel="noopener">{{ $d->name }}</a><br><small class="muted">{{ __('psales.doc_kinds.'.$d->kind) }} · {{ number_format($d->size / 1048576, 1) }} MB · {{ $d->user?->first_name }} · {{ $d->created_at->format('d/m/Y') }}</small></span></label></div>
                        @empty
                            <p class="muted">{{ __('psales.items.no_docs') }}</p>
                        @endforelse
                        @if ($sel->documents->isNotEmpty() && auth()->user()->canUseModule('sales', 2))
                            <details class="mt"><summary>{{ __('psales.items.send') }}</summary>
                                <div class="stack-sm mt">
                                    <label class="field"><span>{{ __('psales.items.to') }}</span><select name="contact_id" required>@foreach ($contacts as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->companies->first()?->name }}</option>@endforeach</select></label>
                                    <label class="field"><span>{{ __('psales.items.message') }}</span><textarea name="message" rows="3" maxlength="2000"></textarea></label>
                                    <div><button class="primary" type="submit">{{ __('psales.items.send_go') }}</button></div>
                                    <small class="muted">{{ __('psales.items.send_hint') }}</small>
                                </div>
                            </details>
                        @endif
                    </form>
                    @if ($canEdit)
                        <form method="post" action="{{ route('psales.items.upload', $sel) }}" enctype="multipart/form-data" class="inline-form mt">@csrf
                            <select name="kind" aria-label="{{ __('psales.items.kind') }}">@foreach (\App\Models\ProductSales\ItemDocument::KINDS as $k)<option value="{{ $k }}">{{ __('psales.doc_kinds.'.$k) }}</option>@endforeach</select>
                            <input type="file" name="files[]" accept="application/pdf" multiple required aria-label="PDF">
                            <button class="btn" type="submit">{{ __('psales.items.upload') }}</button>
                        </form>
                        <small class="muted">{{ __('psales.items.upload_hint') }}</small>
                    @endif
                </section>
            @endif
        </aside>
    </div>
    @include('products._place-js')
</x-layouts.app>
