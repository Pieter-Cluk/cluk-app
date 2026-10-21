<div class="inline-form">
    <label class="field"><span>{{ __('psales.items.brand') }}</span><input name="brand" maxlength="80" value="{{ old('brand', $it->brand) }}"></label>
    <label class="field"><span>{{ __('psales.items.type') }}</span><input name="type" required maxlength="120" value="{{ old('type', $it->type) }}"></label>
</div>
<label class="field"><span>{{ __('psales.items.description') }}</span><input name="description" maxlength="300" value="{{ old('description', $it->description) }}"></label>
<div class="inline-form">
    <label class="field"><span>{{ __('psales.items.category') }}</span><select name="category"><option value="">—</option>@foreach (\App\Models\ProductSales\SalesItem::CATEGORIES as $c)<option value="{{ $c }}" @selected(old('category', $it->category) === $c)>{{ __('psales.cats.'.$c) }}</option>@endforeach</select></label>
    <label class="field"><span>{{ __('psales.items.unit') }}</span><select name="unit">@foreach (\App\Models\ProductSales\SalesItem::UNITS as $u)<option value="{{ $u }}" @selected(old('unit', $it->unit) === $u)>{{ __('psales.units.'.$u) }}</option>@endforeach</select></label>
</div>
<div class="inline-form">
    <label class="field"><span>{{ __('psales.items.list_price') }} (€)</span><input type="number" step="0.01" min="0" name="list_price" required value="{{ old('list_price', $it->exists ? number_format($it->list_price_cents / 100, 2, '.', '') : '') }}" class="w-sm"></label>
    <label class="field"><span>{{ __('psales.items.stock') }}</span><input type="number" min="0" name="stock_qty" value="{{ old('stock_qty', $it->stock_qty ?? 0) }}" class="w-sm"></label>
    <label class="field"><span>{{ __('psales.items.min_stock') }}</span><input type="number" min="0" name="min_stock" value="{{ old('min_stock', $it->min_stock ?? 0) }}" class="w-sm"></label>
</div>
