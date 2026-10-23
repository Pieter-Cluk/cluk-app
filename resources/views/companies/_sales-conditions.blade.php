{{-- Customer card › Standard sales conditions (sales products): default %, % per brand or category, net prices per product. --}}
@if (auth()->user()->canUseModule('sales', 1) || auth()->user()->hasRight('sales.approve_low_margin'))
    @php($rows = \App\Models\ProductSales\CustomerDiscount::with(['brand', 'category', 'item.suppliers', 'setBy'])->where('company_id', $company->id)->get())
    @php($canDisc = auth()->user()->hasRight('sales.approve_low_margin'))
    @php($seeMargin = auth()->user()->hasRight('finance.see_margins'))
    @php($pct = fn ($bp) => rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',').'%')
    @php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
    {{-- Lowest margin among the products a line applies to (for colleagues who see margins). --}}
    @php($lowest = function ($r) use ($seeMargin) {
        if (! $seeMargin) return null;
        if ($r->item) {
            $cost = $r->item->suppliers->isNotEmpty() ? $r->item->costCents() : null;
            return $cost !== null && $r->net_cents ? ($r->net_cents - $cost) / $r->net_cents : null;
        }
        $q = \App\Models\ProductSales\SalesItem::with('suppliers')->where('active', true)->where('list_price_cents', '>', 0)
            ->when($r->brand_id, fn ($w) => $w->where('brand_id', $r->brand_id))->when($r->category_id, fn ($w) => $w->where('category_id', $r->category_id));
        return $q->get()->filter(fn ($i) => $i->suppliers->isNotEmpty())->map(fn ($i) => (($p = $i->list_price_cents * (1 - $r->discount_bp / 10000)) - $i->costCents()) / max(1, $p))->min();
    })
    @php($groups = ['default' => $rows->filter(fn ($r) => $r->scope() === 'default'), 'brand' => $rows->filter(fn ($r) => $r->scope() === 'brand')->sortBy(fn ($r) => $r->brand?->name),
        'category' => $rows->filter(fn ($r) => $r->scope() === 'category')->sortBy(fn ($r) => $r->category?->sort), 'product' => $rows->filter(fn ($r) => $r->scope() === 'product')->sortBy(fn ($r) => $r->item?->number)])
    <section class="card" id="sales-conditions">
        <div class="card-head"><h2>{{ __('psales.discounts.title') }}</h2></div>
        <p class="muted small">{{ __('psales.discounts.intro') }}</p>
        @if ($rows->isEmpty())<p class="muted">{{ __('psales.discounts.none') }}</p>@endif
        @foreach ($groups as $g => $list)
            @if ($list->isNotEmpty())
                <h3 class="sc-h">{{ __('psales.discounts.groups.'.$g) }}</h3>
                @foreach ($list as $r)
                    @php($low = $lowest($r))
                    <div class="contact"><div>
                        <b>{{ match ($g) { 'default' => __('psales.discounts.default'), 'brand' => $r->brand?->name, 'category' => $r->category?->label(), 'product' => $r->item?->number.' · '.$r->item?->name() } }}</b>
                        · <b>{{ $g === 'product' ? $eur($r->net_cents) : $pct($r->discount_bp) }}</b>@if ($g === 'product' && $r->item?->list_price_cents) <small class="muted">({{ __('psales.discounts.list', ['price' => $eur($r->item->list_price_cents)]) }})</small>@endif
                        @if ($low !== null && $low < 0.10)<span class="pill pill-warn">{{ __('psales.discounts.low_margin', ['pct' => number_format($low * 100, 1, ',', '')]) }}</span>@endif
                        <br><small class="muted">@if ($r->note){{ $r->note }} · @endif{{ $r->setBy?->first_name }} · {{ $r->updated_at->format('d/m/Y') }}</small>
                    </div>
                    @if ($canDisc)<form method="post" action="{{ route('companies.discounts.destroy', [$company, $r]) }}">@csrf @method('DELETE')<button class="linkbtn" type="submit">{{ __('psales.discounts.remove') }}</button></form>@endif
                    </div>
                @endforeach
            @endif
        @endforeach
        @if ($canDisc)
            <details class="mt"><summary>{{ __('psales.discounts.add') }}</summary>
                <form method="post" action="{{ route('companies.discounts.store', $company) }}" class="stack-sm mt">@csrf
                    <div class="inline-form">
                        <label class="field"><span>{{ __('psales.discounts.applies_to') }}</span><select name="target" required>
                            <option value="default">{{ __('psales.discounts.default') }}</option>
                            <optgroup label="{{ __('psales.discounts.groups.brand') }}">@foreach (\App\Models\Product\Brand::where('active', true)->orderBy('name')->get(['id', 'name']) as $b)<option value="b:{{ $b->id }}">{{ $b->name }}</option>@endforeach</optgroup>
                            <optgroup label="{{ __('psales.discounts.groups.category') }}">@foreach (\App\Models\Product\Category::of('sales')->where('active', true)->orderBy('sort')->get() as $c)<option value="c:{{ $c->id }}">{{ $c->label() }}</option>@endforeach</optgroup>
                        </select></label>
                        <label class="field"><span>{{ __('psales.discounts.discount') }} %</span><input type="number" name="discount" step="0.1" min="0" max="90" required class="w-sm"></label>
                    </div>
                    <label class="field"><span>{{ __('psales.discounts.note') }}</span><input name="note" maxlength="200"></label>
                    <div><button class="btn" type="submit">{{ __('psales.save') }}</button></div>
                </form>
            </details>
            <details class="mt"><summary>{{ __('psales.discounts.add_net') }}</summary>
                <form method="post" action="{{ route('companies.discounts.net', $company) }}" class="stack-sm mt">@csrf
                    <div class="inline-form">
                        <label class="field"><span>{{ __('psales.discounts.product') }}</span><input name="item" required list="sc-items" placeholder="S-100001" autocomplete="off"></label>
                        <label class="field"><span>{{ __('psales.discounts.net_price') }} (€)</span><input type="number" name="net_price" step="0.01" min="0" required class="w-sm"></label>
                    </div>
                    <datalist id="sc-items">@foreach (\App\Models\ProductSales\SalesItem::where('active', true)->orderBy('number')->get(['id', 'number', 'brand', 'type']) as $i)<option value="{{ $i->number }} · {{ $i->name() }}"></option>@endforeach</datalist>
                    <label class="field"><span>{{ __('psales.discounts.note') }}</span><input name="note" maxlength="200"></label>
                    <div><button class="btn" type="submit">{{ __('psales.save') }}</button></div>
                </form>
            </details>
            <p class="muted small mt">{{ __('psales.discounts.hint') }}</p>
        @endif
    </section>
@endif
