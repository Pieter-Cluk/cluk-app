{{-- Tags and hidden search words. Needs $p, $tags. --}}
@if ($tags->isNotEmpty())
    <div class="field mt"><span>{{ __('products.tags') }}</span>
        @php($myTags = collect(old('tags', $p->exists ? $p->tags->pluck('id')->all() : []))->map(fn ($x) => (int) $x)->all())
        <div class="checks-inline">@foreach ($tags as $t)<label class="check"><input type="checkbox" name="tags[]" value="{{ $t->id }}" @checked(in_array($t->id, $myTags, true))><span>{{ $t->label() }}</span></label>@endforeach</div>
    </div>
@endif
<label class="field mt"><span>{{ __('products.search_words') }}</span><textarea name="search_words" rows="2" maxlength="1000" placeholder="{{ __('products.search_words_placeholder') }}">{{ old('search_words', $p->search_words) }}</textarea><small>{{ __('products.search_words_hint') }}</small></label>
