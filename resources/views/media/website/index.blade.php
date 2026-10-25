@php($site = route('site.home'))
@php($pageUrl = ['home' => route('site.home'), 'dryhire' => route('site.dryhire'), 'sales' => route('site.sales')])
@php($n = $slides->filter->isLive()->count())
<x-layouts.app :title="__('nav.items.website')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.media')) }}</div>
            <h1>{{ __('nav.items.website') }}</h1>
            <p class="muted">{{ __('website.intro') }}</p>
        </div>
        <a class="btn" href="{{ $pageUrl[$tab] ?? $site }}" target="_blank" rel="noopener">{{ __('website.open_page') }} ↗</a>
    </div>
    @include('partials.flash')
    <nav class="tabs">@foreach (\App\Http\Controllers\Media\WebsiteController::TABS as $t)<a @class(['tab', 'tab-on' => $tab === $t]) href="{{ route('website.index', ['tab' => $t]) }}">{{ __('website.tabs.'.$t) }}</a>@endforeach</nav>
    @unless ($canEdit)<p class="flash flash-info mt">{{ __('website.read_only') }}</p>@endunless

    @if ($place)
        <section class="card mt" id="slides">
            <div class="card-head">
                <h2>{{ __('website.places.'.$place) }}</h2>
                <span @class(['pill', 'pill-ok' => $n > 0, 'pill-off' => $n === 0])>{{ $n === 0 ? __('website.status.none') : ($n === 1 ? __('website.status.still') : __('website.status.show', ['n' => $n, 's' => \App\Services\Website\SiteSettings::get('general.slide_seconds', 6)])) }}</span>
            </div>
            <p class="muted small">{{ __('website.places_hint.'.$place) }}</p>
            @include('media.website._format', ['format' => $place, 'open' => $slides->isEmpty()])
            @foreach ($slides as $i => $s)
                @php($url = \App\Services\Website\SiteSettings::link($s->link_type, $s->link_value))
                <article @class(['ws-slide', 'ws-off' => ! $s->isLive()]) id="slide-{{ $s->id }}">
                    <div class="ws-media">
                        @if ($s->kind === 'video')<video src="{{ route('site.webmedia', ['f' => basename($s->path)]) }}" @if ($s->poster_path) poster="{{ route('site.webmedia', ['f' => basename($s->poster_path)]) }}" @endif muted playsinline preload="metadata" controls></video>
                        @else<img src="{{ route('site.webmedia', ['f' => basename($s->path)]) }}" alt="{{ $s->alt }}" loading="lazy">@endif
                        @include('media.website._crops', ['format' => $place, 'src' => route('site.webmedia', ['f' => basename($s->path)]), 'video' => $s->kind === 'video', 'poster' => $s->poster_path ? route('site.webmedia', ['f' => basename($s->poster_path)]) : null])
                    </div>
                    <div class="ws-info">
                        <b>{{ $i + 1 }}. {{ $s->kind === 'video' ? __('website.slide.video') : __('website.slide.image') }}</b>@if ($s->text('title')) · {{ $s->text('title') }}@endif
                        <div class="small">{{ __('website.link.goes') }}
                            @if ($s->link_type === 'none'){{ __('website.link.types.none') }}
                            @elseif ($url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ __('website.link.types.'.$s->link_type) }}: {{ $s->link_type === 'page' ? __('website.link.pages.'.$s->link_value) : $s->link_value }}</a>
                            @else<b class="txt-danger">{{ __('website.link.broken', ['value' => $s->link_value]) }}</b>@endif
                        </div>
                        <small class="muted">@if (! $s->active){{ __('website.slide.off') }} · @endif @if ($s->starts_on || $s->ends_on){{ __('website.slide.dates', ['from' => $s->starts_on?->format('d/m/Y') ?? '…', 'until' => $s->ends_on?->format('d/m/Y') ?? '…']) }} · @endif{{ $s->updated_at->format('d/m/Y') }}</small>
                        @if ($canEdit)
                            <div class="ws-actions">
                                <form method="post" action="{{ route('website.slides.move', [$s, 'up']) }}">@csrf<button class="btn btn-sm" type="submit" @disabled($i === 0) aria-label="{{ __('website.up') }}">↑</button></form>
                                <form method="post" action="{{ route('website.slides.move', [$s, 'down']) }}">@csrf<button class="btn btn-sm" type="submit" @disabled($loop->last) aria-label="{{ __('website.down') }}">↓</button></form>
                                <form method="post" action="{{ route('website.slides.destroy', $s) }}" onsubmit="return confirm(@js(__('website.remove_confirm')))">@csrf @method('DELETE')<button class="btn btn-sm" type="submit">{{ __('website.remove') }}</button></form>
                            </div>
                            <details class="mt-xs">
                                <summary class="linkbtn">{{ __('website.change') }}</summary>
                                <form method="post" action="{{ route('website.slides.update', $s) }}" enctype="multipart/form-data" class="stack-sm mt">@csrf
                                    @include('media.website._link', ['s' => $s])
                                    @include('media.website._slide-texts', ['s' => $s])
                                    <div class="form-grid">
                                        <label class="field"><span>{{ __('website.slide.from') }}</span><input type="date" name="starts_on" value="{{ $s->starts_on?->toDateString() }}"></label>
                                        <label class="field"><span>{{ __('website.slide.until') }}</span><input type="date" name="ends_on" value="{{ $s->ends_on?->toDateString() }}"></label>
                                    </div>
                                    @if ($s->kind === 'video')<label class="field"><span>{{ __('website.slide.poster') }}</span><input type="file" name="poster" accept="image/*"><small>{{ __('website.slide.poster_hint') }}</small></label>@endif
                                    <input type="hidden" name="active" value="0"><label class="check"><input type="checkbox" name="active" value="1" @checked($s->active)> {{ __('website.slide.active') }}</label>
                                    @if ($claude && $place === 'sales_slider')<label class="check"><input type="checkbox" name="translate" value="1" checked> {{ __('website.translate') }}</label>@endif
                                    <div><button class="btn" type="submit">{{ __('website.save') }}</button></div>
                                </form>
                            </details>
                        @endif
                    </div>
                </article>
            @endforeach
            @if ($slides->isEmpty())<p class="muted">{{ __('website.empty') }}</p>@endif
            @if ($canEdit)
                <details class="ws-add" @if ($errors->hasAny(['file', 'link_value', 'link_type'])) open @endif>
                    <summary class="primary">+ {{ __('website.add') }}</summary>
                    <form method="post" action="{{ route('website.slides.store') }}" enctype="multipart/form-data" class="stack-sm mt">@csrf
                        <input type="hidden" name="place" value="{{ $place }}">
                        <label class="field"><span>{{ __('website.slide.file') }}</span><input type="file" name="file" required accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"><small>{{ __('website.slide.file_hint') }}</small></label>
                        @include('media.website._link', ['s' => null])
                        @include('media.website._slide-texts', ['s' => null])
                        <div class="form-grid">
                            <label class="field"><span>{{ __('website.slide.from') }}</span><input type="date" name="starts_on"></label>
                            <label class="field"><span>{{ __('website.slide.until') }}</span><input type="date" name="ends_on"></label>
                        </div>
                        <label class="field"><span>{{ __('website.slide.poster') }}</span><input type="file" name="poster" accept="image/*"><small>{{ __('website.slide.poster_hint') }}</small></label>
                        <input type="hidden" name="active" value="1">
                        @if ($claude && $place === 'sales_slider')<label class="check"><input type="checkbox" name="translate" value="1" checked> {{ __('website.translate') }}</label>@endif
                        <div><button class="primary" type="submit">{{ __('website.add_go') }}</button></div>
                    </form>
                </details>
            @endif
        </section>

        @php($keys = array_values(array_filter(array_keys(\App\Services\Website\SiteSettings::TEXTS), fn ($k) => str_starts_with($k, $tab.'.'))))
        @if ($keys)
            <section class="card" id="texts">
                <h2>{{ __('website.texts') }}</h2>
                <p class="muted small">{{ __('website.texts_hint') }}</p>
                <form method="post" action="{{ route('website.texts', $tab) }}" class="stack-sm">@csrf
                    @foreach ($keys as $k)
                        @php($v = \App\Services\Website\SiteSettings::get($k, []))
                        <div class="ws-textrow"><b>{{ __('website.text_keys.'.str_replace('.', '_', $k)) }}</b>
                            @include('media.website._texts', ['name' => 't['.str_replace('.', '__', $k).']', 'values' => is_array($v) ? $v : [], 'area' => str_ends_with($k, '_text'),
                                'placeholder' => collect($locales)->mapWithKeys(fn ($l) => [$l => __(\App\Services\Website\SiteSettings::TEXTS[$k], [], $l)])->all()])
                        </div>
                    @endforeach
                    @if ($canEdit)
                        @if ($claude)<label class="check"><input type="checkbox" name="translate" value="1" checked> {{ __('website.translate') }}</label>@endif
                        <div><button class="btn" type="submit">{{ __('website.save') }}</button></div>
                    @endif
                </form>
            </section>
        @endif

        @if ($tab === 'home')
            <section class="card" id="tiles">
                <h2>{{ __('website.tiles') }}</h2>
                <p class="muted small">{{ __('website.tiles_hint') }}</p>
                @include('media.website._format', ['format' => 'tile'])
                <div class="ws-tiles">
                    @foreach (\App\Services\Website\SiteSettings::TILES as $key => $d)
                        @php($v = \App\Services\Website\SiteSettings::get('home.tile.'.$key, []))
                        @php($cur = \App\Services\Website\SiteSettings::tile($key))
                        <form method="post" action="{{ route('website.tile', $key) }}" enctype="multipart/form-data" class="ws-tile stack-sm">@csrf
                            <div class="ws-tile-img" style="background-image:url('{{ $cur['img'] }}')"><b>{{ __('site.nav.'.$key) }}</b></div>
                            <div><b class="small">{{ __('website.tile_text') }}</b>@include('media.website._texts', ['name' => 'text', 'values' => $v['text'] ?? [], 'max' => 200, 'placeholder' => collect($locales)->mapWithKeys(fn ($l) => [$l => __($d['t'], [], $l)])->all()])</div>
                            <div><b class="small">{{ __('website.tile_cta') }}</b>@include('media.website._texts', ['name' => 'cta', 'values' => $v['cta'] ?? [], 'max' => 80, 'placeholder' => collect($locales)->mapWithKeys(fn ($l) => [$l => __($d['c'], [], $l)])->all()])</div>
                            @if ($canEdit)
                                <label class="field"><span>{{ __('website.tile_image') }}</span><input type="file" name="image" accept="image/*"></label>
                                @if (! empty($v['image']))<input type="hidden" name="remove_image" value="0"><label class="check"><input type="checkbox" name="remove_image" value="1"> {{ __('website.tile_default') }}</label>@endif
                                @if ($claude)<label class="check"><input type="checkbox" name="translate" value="1" checked> {{ __('website.translate') }}</label>@endif
                                <div><button class="btn" type="submit">{{ __('website.save') }}</button></div>
                            @endif
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($tab === 'dryhire')
            <section class="card" id="popular">
                <h2>{{ __('website.popular') }}</h2>
                <form method="post" action="{{ route('website.general') }}" class="stack-sm">@csrf<input type="hidden" name="tab" value="dryhire">
                    <label class="field"><span>{{ __('website.popular_label') }}</span><input name="popular" maxlength="500" value="{{ implode(', ', \App\Services\Website\SiteSettings::popular()) }}"><small>{{ __('website.popular_hint') }}</small></label>
                    @if ($canEdit)<div><button class="btn" type="submit">{{ __('website.save') }}</button></div>@endif
                </form>
            </section>
        @endif
    @else
        <section class="card mt">
            <h2>{{ __('website.tabs.general') }}</h2>
            <form method="post" action="{{ route('website.general') }}" class="stack-sm">@csrf<input type="hidden" name="tab" value="general">
                <div class="form-grid">
                    <label class="field"><span>{{ __('website.slide_seconds') }}</span><input type="number" name="slide_seconds" min="3" max="30" value="{{ \App\Services\Website\SiteSettings::get('general.slide_seconds', 6) }}"><small>{{ __('website.slide_seconds_hint') }}</small></label>
                    @foreach (['instagram', 'facebook', 'linkedin'] as $nw)
                        <label class="field"><span>{{ ucfirst($nw) }}</span><input type="url" name="{{ $nw }}" maxlength="200" value="{{ \App\Services\Website\SiteSettings::social($nw) }}"></label>
                    @endforeach
                </div>
                <p class="muted small">{{ __('website.general_hint') }}</p>
                @if ($canEdit)<div><button class="btn" type="submit">{{ __('website.save') }}</button></div>@endif
            </form>
        </section>
    @endif

    {{-- Suggestions for the link field, per kind of link. --}}
    @if ($place && $canEdit)
        <datalist id="ws-list-page">@foreach (\App\Models\Website\Slide::PAGES as $p)<option value="{{ $p }}">{{ __('website.link.pages.'.$p) }}</option>@endforeach</datalist>
        <datalist id="ws-list-dryhire_cat">@foreach (\App\Models\Product\Category::of('dryhire')->where('active', true)->orderBy('sort')->get() as $c)<option value="{{ $c->slug }}">{{ $c->label() }}</option>@endforeach</datalist>
        <datalist id="ws-list-sales_cat">@foreach (\App\Models\Product\Category::of('sales')->where('active', true)->orderBy('sort')->get() as $c)<option value="{{ $c->slug }}">{{ $c->label() }}</option>@endforeach</datalist>
        <datalist id="ws-list-brand">@foreach (\App\Models\Product\Brand::where('active', true)->orderBy('name')->get(['slug', 'name']) as $b)<option value="{{ $b->slug }}">{{ $b->name }}</option>@endforeach</datalist>
        <datalist id="ws-list-dryhire_item">@foreach (\App\Models\Equipment\Item::where('active', true)->orderBy('number')->get(['number', 'name']) as $it)<option value="{{ $it->number }}">{{ $it->name }}</option>@endforeach</datalist>
        <datalist id="ws-list-sales_item">@foreach (\App\Models\ProductSales\SalesItem::where('active', true)->orderBy('number')->get(['number', 'brand', 'type']) as $it)<option value="{{ $it->number }}">{{ $it->name() }}</option>@endforeach</datalist>
        <script>
            document.querySelectorAll('[data-ws-link]').forEach(function (box) {
                var type = box.querySelector('[data-ws-type]'), val = box.querySelector('[data-ws-value]'), wrap = box.querySelector('[data-ws-value-wrap]');
                var set = function () { wrap.hidden = type.value === 'none'; val.setAttribute('list', 'ws-list-' + type.value); val.placeholder = type.value === 'url' ? 'https://…' : @js(__('website.link.hint')); };
                type.addEventListener('change', function () { val.value = ''; set(); }); set();
            });
        </script>
    @endif
</x-layouts.app>
