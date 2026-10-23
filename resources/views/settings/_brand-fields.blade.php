<div class="form-grid">
    <label class="field"><span>{{ __('products.brands.name') }} *</span><input name="name" required maxlength="80" value="{{ $b?->name }}"></label>
    <label class="field"><span>{{ __('products.brands.kind') }}</span><select name="kind">@foreach (\App\Models\Product\Brand::KINDS as $k)<option value="{{ $k }}" @selected(($b?->kind ?? 'other') === $k)>{{ __('products.brand_kinds.'.$k) }}</option>@endforeach</select><small>{{ __('products.brands.kind_hint') }}</small></label>
    <label class="field"><span>{{ __('products.slug') }}</span><input name="slug" maxlength="90" value="{{ $b?->slug }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="{{ __('products.brands.slug_auto') }}"><small>{{ __('products.brands.slug_hint') }}</small></label>
    <label class="field"><span>{{ __('products.brands.website') }}</span><input type="url" name="website" maxlength="190" value="{{ $b?->website }}" placeholder="https://"></label>
    <label class="field"><span>{{ __('products.brands.logo') }}</span><input type="file" name="logo" accept="image/*"><small>{{ __('products.brands.logo_hint') }}</small></label>
    <label class="field"><span>{{ __('products.sort') }}</span><input type="number" name="sort" min="0" max="9999" value="{{ $b?->sort ?? 100 }}" class="w-sm"></label>
</div>
<p class="muted small">{{ __('products.brands.intro_hint') }}</p>
<div class="form-grid">
    @foreach ($locales as $code => $lang)
        <label class="field"><span>{{ $lang }}</span><textarea name="intro[{{ $code }}]" rows="2" maxlength="2000">{{ $b?->intro[$code] ?? '' }}</textarea></label>
    @endforeach
</div>
@if ($canTranslate)<label class="check"><input type="checkbox" name="translate" value="1"> {{ __('products.translate') }}</label>@endif
