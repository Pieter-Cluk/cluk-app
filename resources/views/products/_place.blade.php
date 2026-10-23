{{-- Category, subcategory and the attributes of that subcategory (dry-hire item or sales product). Needs $p, $categories, $attrList. --}}
@php($catId = (int) old('category_id', $p->category_id))
@php($subId = (int) old('subcategory_id', $p->subcategory_id))
<label class="field"><span>{{ __('products.category') }}</span>
    <select name="category_id" class="js-cat"><option value="">—</option>@foreach ($categories as $c)@if ($c->active || $c->id === $catId)<option value="{{ $c->id }}" data-code="{{ $c->code }}" @selected($catId === $c->id)>{{ $c->label() }}</option>@endif @endforeach</select>
    @error('category_id')<small class="error">{{ $message }}</small>@enderror
</label>
<label class="field"><span>{{ __('products.subcategory') }}</span>
    <select name="subcategory_id" class="js-sub"><option value="">—</option>@foreach ($categories as $c)@foreach ($c->subcategories as $sc)@if ($sc->active || $sc->id === $subId)<option value="{{ $sc->id }}" data-cat="{{ $c->id }}" data-code="{{ $sc->code }}" @selected($subId === $sc->id)>{{ $sc->label() }}</option>@endif @endforeach @endforeach</select>
    @error('subcategory_id')<small class="error">{{ $message }}</small>@enderror
    @if ($p->exists && ($p->legacy_subcategory ?? $p->legacy_category))<small class="muted">{{ __('products.was', ['old' => trim($p->legacy_category.' › '.($p->legacy_subcategory ?? ''), ' ›')]) }}</small>@endif
</label>
@php($chosen = collect(old('attr', null) ?? $p->options->groupBy('attribute_id')->map(fn ($g) => $g->pluck('id')->all())->mapWithKeys(fn ($ids, $aid) => [$attrList->firstWhere('id', $aid)?->key => $ids])->all()))
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
