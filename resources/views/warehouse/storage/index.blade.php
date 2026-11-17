<x-layouts.app :title="__('nav.items.storage')">
    <div class="pagehead"><div>
        <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }} / {{ mb_strtoupper(__('nav.items.storage')) }}</div>
        <h1>{{ __('nav.items.storage') }}</h1>
        <p class="muted">{{ __('storage.intro') }}</p>
    </div></div>
    @include('partials.flash')
    <section class="card card-flush">
        <table class="table">
            <thead><tr><th>{{ __('storage.customer') }}</th><th>{{ __('storage.since') }}</th><th class="num">{{ __('storage.places') }}</th><th class="num">{{ __('storage.pieces_in') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($contracts as $c)
                    <tr><td><a href="{{ route('storage.show', $c) }}"><b>{{ $c->company->name }}</b></a> <small class="muted">{{ $c->code() }}</small></td>
                        <td>{{ $c->start_date->format('d/m/Y') }}@if ($c->end_date) – {{ $c->end_date->format('d/m/Y') }}@endif</td>
                        <td class="num">{{ $c->places_count }}</td><td class="num">{{ $c->pieces_count }}</td>
                        <td>@if ($c->status === 'ended')<span class="pill">{{ __('storage.ended') }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="5" class="muted">{{ __('storage.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    @if (auth()->user()->canUseModule('warehouse', 2))
        <section class="card mt">
            <h2>{{ __('storage.new') }}</h2>
            <form method="post" action="{{ route('storage.store') }}" class="form-grid">@csrf
                <label class="field"><span>{{ __('storage.customer') }}</span><select data-pick name="company_id" required><option value="">—</option>@foreach ($companies as $co)<option value="{{ $co->id }}">{{ $co->name }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('storage.start') }}</span><input type="date" name="start_date" value="{{ today()->toDateString() }}" required></label>
                <label class="field"><span>{{ __('storage.note') }}</span><input name="note" maxlength="300"></label>
                <div><button class="primary" type="submit">{{ __('storage.create') }}</button></div>
            </form>
            <p class="muted small mt">{{ __('storage.prices', ['shelf' => \App\Services\Afrit6\Pricing::eur(\App\Services\Afrit6\Pricing::dayPrice('shelf')), 'pallet' => \App\Services\Afrit6\Pricing::eur(\App\Services\Afrit6\Pricing::dayPrice('pallet')), 'coli' => \App\Services\Afrit6\Pricing::eur(\App\Services\Afrit6\Pricing::handling(1, 0)), 'pal' => \App\Services\Afrit6\Pricing::eur(\App\Services\Afrit6\Pricing::handling(0, 1))]) }}</p>
        </section>
    @endif
</x-layouts.app>
