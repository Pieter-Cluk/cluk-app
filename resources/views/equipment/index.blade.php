@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('nav.items.dryhire')">
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
            <a href="{{ route('equipment.index', array_filter(['kind' => $k === 'all' ? null : $k, 'cat' => $cat ?: null, 'q' => $q ?: null, 'inactive' => $showInactive ? 1 : null, 'web' => $onlyWeb ? 1 : null])) }}" @class(['chip', 'chip-on' => $kind === $k])>{{ __('equipment.filters.'.$k) }} <span class="count">{{ $counts[$k] }}</span></a>
        @endforeach
    </nav>
    <form method="get" class="filters">
        @if ($kind !== 'all')<input type="hidden" name="kind" value="{{ $kind }}">@endif
        <label class="field-search search"><x-icon name="search"/><input type="search" name="q" value="{{ $q }}" placeholder="{{ __('equipment.search') }}" aria-label="{{ __('equipment.search') }}"></label>
        <select name="cat" aria-label="{{ __('equipment.fields.category') }}" onchange="this.form.submit()">
            <option value="">{{ __('equipment.all_categories') }}</option>
            @foreach ($categories as $c)<option value="{{ $c }}" @selected($cat === $c)>{{ $c }}</option>@endforeach
        </select>
        <label class="check"><input type="checkbox" name="inactive" value="1" @checked($showInactive) onchange="this.form.submit()"><span>{{ __('equipment.show_inactive') }}</span></label>
        <label class="check"><input type="checkbox" name="web" value="1" @checked($onlyWeb) onchange="this.form.submit()"><span>{{ __('equipment.only_web') }}</span></label>
        <button class="btn" type="submit">{{ __('equipment.find') }}</button>
    </form>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th></th><th>{{ __('equipment.fields.number') }}</th><th>{{ __('equipment.fields.name') }}</th><th>{{ __('equipment.fields.category') }}</th><th>{{ __('equipment.cols.kind') }}</th><th class="num">{{ __('equipment.cols.stock') }}<small>{{ __('equipment.cols.stock_hint') }}</small></th><th class="num">{{ __('equipment.cols.price') }}</th></tr></thead>
                <tbody>
                    @forelse ($items as $it)
                        <tr @class(['is-off' => ! $it->active])>
                            <td class="thumb">@if ($it->photo_path)<img src="{{ route('equipment.photo', $it) }}" alt="" width="40" height="40" loading="lazy">@else<span class="thumb-empty" aria-hidden="true"><x-icon name="box"/></span>@endif</td>
                            <td class="nowrap"><a href="{{ route('equipment.show', $it) }}"><b>{{ $it->number }}</b></a></td>
                            <td>{{ $it->name }}@if (! $it->active) <span class="pill pill-off">{{ __('equipment.inactive') }}</span>@endif @if ($it->on_website) <span class="pill">{{ __('equipment.web_pill') }}</span>@endif<br><small class="muted">{{ trim($it->brand.' '.$it->type) }}</small></td>
                            <td>{{ $it->category ?? '—' }}<br><small class="muted">{{ $it->subcategory }}</small></td>
                            <td><span @class(['pill', 'pill-info' => $it->kind === 'package', 'pill-warn' => $it->kind === 'case', 'pill-off' => $it->kind === 'service', 'pill-ok' => $it->kind === 'item'])>{{ __('equipment.kinds.'.$it->kind) }}</span>@if ($it->kind === 'item' && ! $it->serial) <small class="muted">{{ __('equipment.counted') }}</small>@endif</td>
                            <td class="num nowrap">{{ $it->kind === 'service' ? '—' : ($it->kind === 'package' ? $it->availableQty() : $it->availableQty().' / '.$it->totalQty()) }}</td>
                            <td class="num nowrap">{{ $eur($it->day_price_cents) }}<br><small class="muted">{{ $it->kind === 'service' ? __('equipment.units.'.$it->price_unit) : __('equipment.per_day') }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">{{ __('equipment.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$items"/>
</x-layouts.app>
