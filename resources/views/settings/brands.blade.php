@php($locales = config('cluk.locales'))
@php($fields = function (?\App\Models\Product\Brand $b) use ($locales, $canTranslate) {
    return view('settings._brand-fields', ['b' => $b, 'locales' => $locales, 'canTranslate' => $canTranslate])->render();
})
<x-layouts.app :title="__('products.brands.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.settings')) }} / {{ mb_strtoupper(__('products.title')) }}</div>
            <h1>{{ __('products.brands.title') }}</h1>
            <p class="muted">{{ __('products.brands.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif

    <nav class="tabs">
        @foreach (\App\Models\Product\Category::CATALOGUES as $cat)<a class="tab" href="{{ route('settings.products.index', ['c' => $cat]) }}">{{ __('products.catalogues.'.$cat) }}</a>@endforeach
        <a class="tab tab-on" aria-current="page" href="{{ route('settings.products.brands') }}">{{ __('products.brands.tab') }}</a>
    </nav>

    <nav class="chips mt">
        <a @class(['chip', 'chip-on' => ! $kind]) href="{{ route('settings.products.brands') }}">{{ __('products.brands.all') }} · {{ $counts->sum() }}</a>
        @foreach (\App\Models\Product\Brand::KINDS as $k)<a @class(['chip', 'chip-on' => $kind === $k]) href="{{ route('settings.products.brands', ['k' => $k]) }}">{{ __('products.brand_kinds.'.$k) }} · {{ $counts[$k] ?? 0 }}</a>@endforeach
    </nav>

    <section class="card mt">
        <details @if ($errors->any() && ! old('_brand')) open @endif><summary><b>{{ __('products.brands.add') }}</b></summary>
            <form method="post" action="{{ route('settings.products.brand.store') }}" enctype="multipart/form-data" class="stack-sm mt">@csrf
                {!! $fields(null) !!}
                <div><button class="primary" type="submit">{{ __('products.brands.add') }}</button></div>
            </form>
        </details>
    </section>

    <section class="card card-flush mt">
        <table class="table">
            <thead><tr><th></th><th>{{ __('products.brands.name') }}</th><th>{{ __('products.brands.kind') }}</th><th>{{ __('products.slug') }}</th><th class="num">{{ __('products.brands.products') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($brands as $b)
                    <tr @class(['is-off' => ! $b->active])>
                        <td class="br-logo">@if ($b->logo_path)<img src="{{ route('brand.logo', [$b, 'v' => substr(md5($b->logo_path), 0, 8)]) }}" alt="" loading="lazy">@endif</td>
                        <td><b>{{ $b->name }}</b>@if (! $b->active) <span class="pill pill-off">{{ __('products.brands.off') }}</span>@endif @if ($b->website)<br><small class="muted">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($b->website, '/')) }}</small>@endif</td>
                        <td><span @class(['pill', 'pill-ok' => $b->kind === 'own', 'pill-warn' => $b->kind === 'distributed'])>{{ __('products.brand_kinds.'.$b->kind) }}</span></td>
                        <td><code>{{ $b->slug }}</code></td>
                        <td class="num"><a href="{{ route('psales.items', ['brand' => $b->id]) }}">{{ $b->sales_items_count }}</a></td>
                        <td class="num"><details class="pl-row"><summary>{{ __('products.edit') }}</summary>
                            <form method="post" action="{{ route('settings.products.brand.update', $b) }}" enctype="multipart/form-data" class="stack-sm mt">@csrf @method('PUT')
                                <input type="hidden" name="_brand" value="{{ $b->id }}">
                                {!! $fields($b) !!}
                                <input type="hidden" name="active" value="0"><label class="check"><input type="checkbox" name="active" value="1" @checked($b->active)> {{ __('products.active') }}</label>
                                <div><button class="primary" type="submit">{{ __('products.save') }}</button></div>
                            </form>
                            @if ($brands->count() > 1)
                                <form method="post" action="{{ route('settings.products.brand.merge', $b) }}" class="inline-form mt">@csrf
                                    <label class="field"><span>{{ __('products.brands.merge_into') }}</span><select name="into" required><option value="">—</option>@foreach ($brands as $o)@if ($o->isNot($b))<option value="{{ $o->id }}">{{ $o->name }}</option>@endif @endforeach</select></label>
                                    <button class="btn" type="submit">{{ __('products.brands.merge') }}</button>
                                </form>
                                <small class="muted">{{ __('products.brands.merge_hint') }}</small>
                            @endif
                            @if (! $b->sales_items_count)
                                <form method="post" action="{{ route('settings.products.brand.destroy', $b) }}" class="mt">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('products.delete') }}</button></form>
                            @endif
                        </details></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ __('products.brands.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
