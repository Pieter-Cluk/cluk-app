@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('purchasing.new')">
    <div class="pagehead"><div><div class="kicker"><a href="{{ route('purchasing.index') }}">{{ mb_strtoupper(__('nav.items.purchasing')) }}</a></div><h1>{{ __('purchasing.new') }}</h1><p class="muted">{{ __('purchasing.new_intro') }}</p></div></div>
    @include('partials.flash')
    <form method="post" action="{{ route('purchasing.store') }}" class="card card-flush">@csrf
        <table class="table">
            <thead><tr><th>{{ __('purchasing.cols.items') }}</th><th class="num">{{ __('psales.items.stock') }}</th><th>{{ __('purchasing.cols.supplier') }}</th><th class="num">{{ __('purchasing.cols.qty') }}</th><th>{{ __('purchasing.purpose') }}</th></tr></thead>
            <tbody>
                @forelse ($items as $it)
                    <tr @class(['ps-low' => in_array($it->id, $low, true)])><td><b>{{ $it->name() }}</b><br><small class="muted">{{ $it->number }}@if (in_array($it->id, $low, true)) · {{ __('purchasing.below_min', ['n' => $it->min_stock]) }}@endif</small></td>
                        <td class="num">{{ $it->stock_qty }}</td><td><small>{{ $it->suppliers->pluck('name')->implode(', ') ?: __('purchasing.no_supplier') }}</small></td>
                        <td class="num"><input type="number" min="0" name="qty[{{ $it->id }}]" value="{{ old('qty.'.$it->id, in_array($it->id, $low, true) ? max(0, $it->min_stock - $it->stock_qty) : '') }}" class="w-xs" @disabled($it->suppliers->isEmpty()) aria-label="{{ __('purchasing.cols.qty') }}"></td>
                        <td><select name="purpose[{{ $it->id }}]" aria-label="{{ __('purchasing.purpose') }}">@foreach (\App\Models\Purchasing\RequestLine::PURPOSES as $p)<option value="{{ $p }}">{{ __('purchasing.purposes.'.$p) }}</option>@endforeach</select></td></tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('purchasing.no_items') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pad stack-sm">
            <div class="inline-form"><label class="field"><span>{{ __('purchasing.needed_by') }}</span><input type="date" name="needed_by" value="{{ old('needed_by') }}"></label>
                <label class="field"><span>{{ __('purchasing.note') }}</span><input name="note" maxlength="500" value="{{ old('note') }}"></label></div>
            <input type="hidden" name="send" value="0"><label class="check"><input type="checkbox" name="send" value="1" checked> {{ __('purchasing.send_now') }}</label>
            <div><button class="primary" type="submit">{{ __('purchasing.create') }}</button></div>
        </div>
    </form>
</x-layouts.app>
