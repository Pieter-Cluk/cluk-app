{{-- Suggestions for "Where is it?": rack places and storage, plus the boxes/dollies/cases (not for a case itself). --}}
<datalist id="{{ $id ?? 'place-codes' }}">
    @if ($racks ?? true)
    @foreach (\App\Models\Warehouse\Zone::where('active', true)->where('type', 'storage')->orderBy('sort')->get() as $z)<option value="{{ $z->code }}">{{ $z->label() }}</option>@endforeach
    @foreach (\App\Models\Warehouse\Position::where('active', true)->whereIn('use', ['rental', 'spare'])->orderBy('code')->get(['code', 'floor', 'row', 'rack', 'layer', 'spot']) as $p)<option value="{{ $p->code }}">{{ \App\Http\Controllers\Warehouse\LabelController::positionName($p) }}</option>@endforeach
    @endif
    @if ($cases ?? true)
        @foreach (\App\Models\Equipment\Asset::with('item:id,name')->whereHas('item', fn ($q) => $q->where('kind', 'case'))->where('status', '!=', 'retired')->when($except ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->orderBy('code')->get(['id', 'code', 'item_id']) as $c)<option value="{{ $c->code }}">{{ $c->item->name }}</option>@endforeach
    @endif
</datalist>
