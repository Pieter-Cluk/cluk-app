@php($locales = config('cluk.locales'))
@php($names = function ($m, $prefix = 'name') use ($locales) {
    $out = '';
    foreach ($locales as $code => $lang) {
        $out .= '<label class="field"><span>'.e($lang).($code === 'en' ? ' *' : '').'</span><input name="'.$prefix.'['.$code.']" maxlength="80" value="'.e(old($prefix.'.'.$code, $m?->{$prefix}[$code] ?? '')).'"'.($code === 'en' ? ' required' : '').'></label>';
    }
    return $out;
})
<x-layouts.app :title="__('products.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.settings')) }}</div>
            <h1>{{ __('products.title') }}</h1>
            <p class="muted">{{ __('products.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif

    <nav class="tabs">
        @foreach (\App\Models\Product\Category::CATALOGUES as $cat)<a @class(['tab', 'tab-on' => $catalogue === $cat]) href="{{ route('settings.products.index', ['c' => $cat]) }}">{{ __('products.catalogues.'.$cat) }}</a>@endforeach
        <a class="tab" href="{{ route('settings.products.brands') }}">{{ __('products.brands.tab') }}</a>
    </nav>
    <section class="card mt">
        <h2>{{ __('products.safe_title') }}</h2>
        <ul class="plain-list">@foreach (__('products.safe_rules') as $r)<li>{{ $r }}</li>@endforeach</ul>
    </section>

    <h2 class="mt">{{ __('products.categories') }}</h2>
    @foreach ($categories as $c)
        <section @class(['card', 'mt', 'is-off' => ! $c->active])>
            <div class="pl-head"><h3>{{ $c->label() }} <small class="muted">{{ $c->code }} · /{{ $c->slug }} · {{ trans_choice('products.items', $catCounts[$c->id] ?? 0, ['n' => $catCounts[$c->id] ?? 0]) }}</small>@unless ($c->active) <span class="pill pill-off">{{ __('products.hidden') }}</span>@endunless</h3></div>
            <details><summary>{{ __('products.edit_category') }}</summary>
                <form method="post" action="{{ route('settings.products.category.update', $c) }}" class="pl-form">@csrf @method('put')
                    <div class="pl-grid">{!! $names($c) !!}
                        <label class="field"><span>{{ __('products.code') }}</span><input name="code" maxlength="6" required value="{{ $c->code }}"></label>
                        <label class="field"><span>{{ __('products.slug') }}</span><input name="slug" maxlength="80" value="{{ $c->slug }}" pattern="[a-z0-9]+(-[a-z0-9]+)*"><small>{{ __('products.slug_hint') }}</small></label>
                        <label class="field"><span>{{ __('products.sort') }}</span><input type="number" name="sort" value="{{ $c->sort }}" class="w-sm"></label>
                    </div>
                    <label class="check"><input type="checkbox" name="active" value="1" @checked($c->active)><span>{{ __('products.active') }}</span></label>
                    <button class="primary" type="submit">{{ __('products.save') }}</button>
                </form>
                @if (! ($catCounts[$c->id] ?? 0) && $c->subcategories->isEmpty())
                    <form method="post" action="{{ route('settings.products.category.destroy', $c) }}" onsubmit="return confirm(@js(__('products.delete_confirm')))">@csrf @method('delete')<button class="linkbtn" type="submit">{{ __('products.delete') }}</button></form>
                @endif
            </details>

            <div class="table-wrap mt"><table class="table table-tight">
                <thead><tr><th>{{ __('products.code') }}</th><th>{{ __('products.subcategory') }}</th><th class="num">{{ __('products.items_col') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($c->subcategories as $s)
                        <tr @class(['is-off' => ! $s->active])>
                            <td>{{ $s->code }}</td>
                            <td>{{ $s->label() }} <small class="muted">/{{ $s->slug }}</small>@unless ($s->active) <span class="pill pill-off">{{ __('products.hidden') }}</span>@endunless</td>
                            <td class="num">{{ $subCounts[$s->id] ?? 0 }}</td>
                            <td class="num"><details class="pl-row"><summary>{{ __('products.edit') }}</summary>
                                <form method="post" action="{{ route('settings.products.subcategory.update', $s) }}" class="pl-form">@csrf @method('put')
                                    <div class="pl-grid">{!! $names($s) !!}
                                        <label class="field"><span>{{ __('products.code') }}</span><input name="code" maxlength="6" required value="{{ $s->code }}"></label>
                                        <label class="field"><span>{{ __('products.slug') }}</span><input name="slug" maxlength="80" value="{{ $s->slug }}" pattern="[a-z0-9]+(-[a-z0-9]+)*"></label>
                                        <label class="field"><span>{{ __('products.sort') }}</span><input type="number" name="sort" value="{{ $s->sort }}" class="w-sm"></label>
                                    </div>
                                    <label class="check"><input type="checkbox" name="active" value="1" @checked($s->active)><span>{{ __('products.active') }}</span></label>
                                    @if ($subCounts[$s->id] ?? 0)
                                        <label class="field"><span>{{ __('products.move_to') }}</span><select name="move_to"><option value="">—</option>@foreach ($allSubs as $o)@if ($o->active && $o->id !== $s->id)<option value="{{ $o->id }}">{{ $o->category->label() }} › {{ $o->label() }}</option>@endif @endforeach</select><small>{{ __('products.move_hint') }}</small></label>
                                    @endif
                                    <button class="primary" type="submit">{{ __('products.save') }}</button>
                                </form>
                                @unless ($subCounts[$s->id] ?? 0)
                                    <form method="post" action="{{ route('settings.products.subcategory.destroy', $s) }}" onsubmit="return confirm(@js(__('products.delete_confirm')))">@csrf @method('delete')<button class="linkbtn" type="submit">{{ __('products.delete') }}</button></form>
                                @endunless
                            </details></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <details class="mt"><summary>{{ __('products.add_subcategory') }}</summary>
                <form method="post" action="{{ route('settings.products.subcategory.store', $c) }}" class="pl-form">@csrf
                    <div class="pl-grid">{!! $names(null) !!}<label class="field"><span>{{ __('products.code') }}</span><input name="code" maxlength="6" required></label></div>
                    @if ($canTranslate)<label class="check"><input type="checkbox" name="translate" value="1" checked><span>{{ __('products.translate') }}</span></label>@endif
                    <button class="primary" type="submit">{{ __('products.add') }}</button>
                </form>
            </details>
        </section>
    @endforeach
    <details class="card mt"><summary>{{ __('products.add_category') }}</summary>
        <form method="post" action="{{ route('settings.products.category.store') }}" class="pl-form">@csrf<input type="hidden" name="c" value="{{ $catalogue }}">
            <div class="pl-grid">{!! $names(null) !!}<label class="field"><span>{{ __('products.code') }}</span><input name="code" maxlength="6" required></label></div>
            @if ($canTranslate)<label class="check"><input type="checkbox" name="translate" value="1" checked><span>{{ __('products.translate') }}</span></label>@endif
            <button class="primary" type="submit">{{ __('products.add') }}</button>
        </form>
    </details>

    <h2 class="mt">{{ __('products.attributes') }}</h2>
    <p class="muted">{{ __('products.attributes_intro') }}</p>
    @foreach ($attrList as $a)
        <section class="card mt">
            <h3>{{ $a->label() }} <small class="muted">{{ $a->multiple ? __('products.several') : __('products.one') }}@if ($a->unit) · {{ $a->unit }}@endif · {{ trans_choice('products.subcats_n', $a->subcategories->count(), ['n' => $a->subcategories->count()]) }}</small></h3>
            <div class="pl-chips">@foreach ($a->options as $o)<details @class(['pl-chip', 'is-off' => ! $o->active])><summary>{{ $o->display() }} <small class="muted">{{ $optCounts[$o->id] ?? 0 }}</small></summary>
                <form method="post" action="{{ route('settings.products.option.update', $o) }}" class="pl-form">@csrf @method('put')
                    <div class="pl-grid">@foreach ($locales as $code => $lang)<label class="field"><span>{{ $lang }}</span><input name="label[{{ $code }}]" maxlength="60" value="{{ $o->label[$code] ?? '' }}" placeholder="{{ $o->value }}"></label>@endforeach<label class="field"><span>{{ __('products.sort') }}</span><input type="number" name="sort" value="{{ $o->sort }}" class="w-sm"></label></div>
                    <label class="check"><input type="checkbox" name="active" value="1" @checked($o->active)><span>{{ __('products.active') }}</span></label>
                    <button class="primary" type="submit">{{ __('products.save') }}</button>
                </form>
                @unless ($optCounts[$o->id] ?? 0)<form method="post" action="{{ route('settings.products.option.destroy', $o) }}">@csrf @method('delete')<button class="linkbtn" type="submit">{{ __('products.delete') }}</button></form>@endunless
            </details>@endforeach</div>
            <form method="post" action="{{ route('settings.products.option.store', $a) }}" class="inline-form mt">@csrf
                <label class="field"><span>{{ __('products.new_choice') }}</span><input name="value" required maxlength="60" placeholder="{{ $a->unit ? '2.5' : 'White' }}"></label>
                <button class="btn" type="submit">{{ __('products.add') }}</button>
            </form>
            <details class="mt"><summary>{{ __('products.edit_attribute') }}</summary>
                <form method="post" action="{{ route('settings.products.attribute.update', $a) }}" class="pl-form">@csrf @method('put')
                    <div class="pl-grid">{!! $names($a) !!}<label class="field"><span>{{ __('products.unit') }}</span><input name="unit" maxlength="10" value="{{ $a->unit }}" class="w-sm"></label></div>
                    <p class="muted">{{ __('products.applies_to') }}</p>
                    @foreach ($categories as $c)<div class="checks-inline"><b>{{ $c->label() }}</b>@foreach ($c->subcategories as $s)<label class="check"><input type="checkbox" name="subs[]" value="{{ $s->id }}" @checked($a->subcategories->contains('id', $s->id))><span>{{ $s->label() }}</span></label>@endforeach</div>@endforeach
                    <button class="primary mt" type="submit">{{ __('products.save') }}</button>
                </form>
            </details>
        </section>
    @endforeach
    <details class="card mt"><summary>{{ __('products.add_attribute') }}</summary>
        <form method="post" action="{{ route('settings.products.attribute.store') }}" class="pl-form">@csrf<input type="hidden" name="c" value="{{ $catalogue }}">
            <div class="pl-grid">{!! $names(null) !!}<label class="field"><span>{{ __('products.unit') }}</span><input name="unit" maxlength="10" class="w-sm"></label></div>
            <label class="check"><input type="checkbox" name="multiple" value="1"><span>{{ __('products.several_hint') }}</span></label>
            @if ($canTranslate)<label class="check"><input type="checkbox" name="translate" value="1" checked><span>{{ __('products.translate') }}</span></label>@endif
            <button class="primary" type="submit">{{ __('products.add') }}</button>
        </form>
    </details>

    <h2 class="mt">{{ __('products.tags') }}</h2>
    <section class="card mt">
        <div class="pl-chips">@foreach ($tags as $t)<details @class(['pl-chip', 'is-off' => ! $t->active])><summary>{{ $t->label() }} <small class="muted">{{ $tagCounts[$t->id] ?? 0 }}</small></summary>
            <form method="post" action="{{ route('settings.products.tag.update', $t) }}" class="pl-form">@csrf @method('put')
                <div class="pl-grid">{!! $names($t) !!}<label class="field"><span>{{ __('products.sort') }}</span><input type="number" name="sort" value="{{ $t->sort }}" class="w-sm"></label></div>
                <label class="check"><input type="checkbox" name="active" value="1" @checked($t->active)><span>{{ __('products.active') }}</span></label>
                <button class="primary" type="submit">{{ __('products.save') }}</button>
            </form>
            @unless ($tagCounts[$t->id] ?? 0)<form method="post" action="{{ route('settings.products.tag.destroy', $t) }}">@csrf @method('delete')<button class="linkbtn" type="submit">{{ __('products.delete') }}</button></form>@endunless
        </details>@endforeach</div>
        <details class="mt"><summary>{{ __('products.add_tag') }}</summary>
            <form method="post" action="{{ route('settings.products.tag.store') }}" class="pl-form">@csrf<input type="hidden" name="c" value="{{ $catalogue }}">
                <div class="pl-grid">{!! $names(null) !!}</div>
                @if ($canTranslate)<label class="check"><input type="checkbox" name="translate" value="1" checked><span>{{ __('products.translate') }}</span></label>@endif
                <button class="primary" type="submit">{{ __('products.add') }}</button>
            </form>
        </details>
    </section>
</x-layouts.app>
