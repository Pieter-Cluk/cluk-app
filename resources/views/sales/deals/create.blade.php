<x-layouts.app :title="__('deals.new')">
    <div class="pagehead"><div><div class="kicker"><a href="{{ route('deals.index') }}">{{ mb_strtoupper(__('nav.items.sales')) }}</a></div><h1>{{ __('deals.new') }}</h1></div></div>
    @include('partials.flash')
    <form method="post" action="{{ route('deals.store') }}" class="card">
        @csrf
        @include('sales.deals._fields', ['deal' => new \App\Models\Sales\Deal(['module' => $module ?? 'dryhire', 'company_id' => $company?->id, 'owner_id' => auth()->id()]), 'contacts' => collect()])
        <div class="form-actions"><a class="btn" href="{{ route('deals.index') }}">{{ __('equipment.cancel') }}</a><button class="primary" type="submit">{{ __('deals.create') }}</button></div>
    </form>
</x-layouts.app>
