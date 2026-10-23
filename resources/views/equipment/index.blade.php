@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('nav.items.dryhire')">
    @php($canWeb = auth()->user()->hasRight('equipment.website'))
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }}</div>
            <h1>{{ __('nav.items.dryhire') }}</h1>
            <p class="muted">{{ __('equipment.intro') }}</p>
        </div>
        @if ($canEdit)
            <div class="inline-form">
                @if (auth()->user()->canUseModule('settings', 2))<a class="btn" href="{{ url('/settings/import') }}">{{ __('equipment.import') }}</a>@endif
                <a class="btn" href="{{ route('equipment.photos.found') }}">{{ __('equipment.photos_found.link') }}</a>
                <a class="primary" href="{{ route('equipment.create') }}">{{ __('equipment.new') }}</a>
            </div>
        @endif
    </div>
    @include('partials.flash')

    <nav class="chips">
        @foreach (\App\Http\Controllers\Equipment\ItemController::FILTERS as $k)
            <a href="{{ route('equipment.index', array_filter(['kind' => $k === 'all' ? null : $k, 'cat' => $cat ?: null, 'sub' => $sub ?: null, 'tag' => $tag ?: null, 'opt' => $opts ?: null, 'q' => $q ?: null, 'inactive' => $showInactive ? 1 : null, 'web' => $onlyWeb ? 1 : null])) }}" @class(['chip', 'chip-on' => $kind === $k])>{{ __('equipment.filters.'.$k) }} <span class="count">{{ $counts[$k] }}</span></a>
        @endforeach
    </nav>
    <form method="get" class="filters">
        @if ($kind !== 'all')<input type="hidden" name="kind" value="{{ $kind }}">@endif
        <label class="field-search search"><x-icon name="search"/><input type="search" name="q" value="{{ $q }}" placeholder="{{ __('equipment.search') }}" aria-label="{{ __('equipment.search') }}"></label>
        <select name="cat" aria-label="{{ __('equipment.fields.category') }}" onchange="this.form.sub && (this.form.sub.value = ''); this.form.submit()">
            <option value="">{{ __('equipment.all_categories') }}</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($cat === $c->id)>{{ $c->label() }}</option>@endforeach
        </select>
        @if ($cat)
            <select name="sub" aria-label="{{ __('equipment.fields.subcategory') }}" onchange="this.form.submit()">
                <option value="">{{ __('products.all_subcategories') }}</option>
                @foreach ($categories->firstWhere('id', $cat)?->subcategories ?? [] as $sc)@if ($sc->active)<option value="{{ $sc->id }}" @selected($sub === $sc->id)>{{ $sc->label() }}</option>@endif @endforeach
            </select>
        @endif
        @if ($subcategory)
            @foreach ($subcategory->attrs as $a)
                <select name="opt[]" aria-label="{{ $a->label() }}" onchange="this.form.submit()">
                    <option value="">{{ $a->label() }}: {{ __('products.any') }}</option>
                    @foreach ($a->options as $o)@if ($o->active)<option value="{{ $o->id }}" @selected(in_array($o->id, $opts, true))>{{ $a->label() }}: {{ $o->display() }}</option>@endif @endforeach
                </select>
            @endforeach
        @endif
        @if ($tags->isNotEmpty())
            <select name="tag" aria-label="{{ __('products.tags') }}" onchange="this.form.submit()">
                <option value="">{{ __('products.all_tags') }}</option>
                @foreach ($tags as $t)<option value="{{ $t->id }}" @selected($tag === $t->id)>{{ $t->label() }}</option>@endforeach
            </select>
        @endif
        <label class="check"><input type="checkbox" name="inactive" value="1" @checked($showInactive) onchange="this.form.submit()"><span>{{ __('equipment.show_inactive') }}</span></label>
        <label class="check"><input type="checkbox" name="web" value="1" @checked($onlyWeb) onchange="this.form.submit()"><span>{{ __('equipment.only_web') }}</span></label>
        <button class="btn" type="submit">{{ __('equipment.find') }}</button>
    </form>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th></th><th>{{ __('equipment.fields.number') }}</th><th>{{ __('equipment.fields.name') }}</th><th>{{ __('equipment.fields.category') }}</th><th>{{ __('equipment.cols.kind') }}</th><th class="num">{{ __('equipment.cols.stock') }}<small>{{ __('equipment.cols.stock_hint') }}</small></th><th class="num">{{ __('equipment.cols.price') }}</th><th class="center" title="{{ __('equipment.fields.on_website') }}">{{ __('equipment.web_pill') }}</th></tr></thead>
                <tbody>
                    @forelse ($items as $it)
                        <tr @class(['is-off' => ! $it->active])>
                            <td class="thumb">@if ($it->photo_path)<img src="{{ route('equipment.photo', $it) }}" alt="" width="40" height="40" loading="lazy">@else<span class="thumb-empty" aria-hidden="true"><x-icon name="box"/></span>@endif</td>
                            <td class="nowrap"><a href="{{ route('equipment.show', $it) }}"><b>{{ $it->number }}</b></a></td>
                            <td>{{ $it->name }}@if (! $it->active) <span class="pill pill-off">{{ __('equipment.inactive') }}</span>@endif<br><small class="muted">{{ trim($it->brand.' '.$it->type) }}</small></td>
                            <td>{{ $it->productCategory?->label() ?? $it->category ?? '—' }}<br><small class="muted">{{ $it->productSubcategory?->label() ?? $it->subcategory }}</small>@foreach ($it->optionLabels() as $l)<span class="chip-attr">{{ $l }}</span>@endforeach @foreach ($it->tags as $t)<span class="pill">{{ $t->label() }}</span>@endforeach</td>
                            <td><span @class(['pill', 'pill-info' => $it->kind === 'package', 'pill-warn' => $it->kind === 'case', 'pill-off' => $it->kind === 'service', 'pill-ok' => $it->kind === 'item'])>{{ __('equipment.kinds.'.$it->kind) }}</span>@if ($it->kind === 'item' && ! $it->serial) <small class="muted">{{ __('equipment.counted') }}</small>@endif</td>
                            <td class="num nowrap">{{ $it->kind === 'service' ? '—' : ($it->kind === 'package' ? $it->availableQty() : $it->availableQty().' / '.$it->totalQty()) }}</td>
                            <td class="num nowrap">{{ $eur($it->day_price_cents) }}<br><small class="muted">{{ $it->kind === 'service' ? __('equipment.units.'.$it->price_unit) : __('equipment.per_day') }}</small></td>
                            <td class="center">@if ($canWeb)<input type="checkbox" class="web-tick" data-url="{{ route('equipment.website', $it) }}" @checked($it->on_website) aria-label="{{ __('equipment.fields.on_website') }} · {{ $it->name }}">@else<span @class(['muted' => ! $it->on_website])>{{ $it->on_website ? '✓' : '—' }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">{{ __('equipment.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$items"/>
    @if ($canWeb)
        <script>
            document.querySelectorAll('.web-tick').forEach(function (box) {
                box.addEventListener('change', function () {
                    box.disabled = true;
                    fetch(box.dataset.url, {method: 'POST', headers: {'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({on: box.checked})})
                        .then(function (r) { if (!r.ok) throw r; return r.json(); })
                        .then(function (d) { box.checked = d.on; })
                        .catch(function () { box.checked = !box.checked; alert(@js(__('equipment.web_failed'))); })
                        .finally(function () { box.disabled = false; });
                });
            });
        </script>
    @endif
</x-layouts.app>
