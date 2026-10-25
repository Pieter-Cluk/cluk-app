<x-layouts.app :title="__('meetings.new')">
    <div class="pagehead"><div><div class="kicker"><a href="{{ route('meetings.index') }}">{{ mb_strtoupper(__('nav.items.meetings')) }}</a></div><h1>{{ __('meetings.new') }}</h1><p class="muted">{{ __('meetings.new_intro') }}</p></div></div>
    @include('partials.flash')
    <form method="post" action="{{ route('meetings.store') }}" class="card">
        @csrf
        @include('work.meetings._fields', ['m' => new \App\Models\Work\Meeting(['kind' => $company ? 'customer' : 'internal', 'company_id' => $company?->id])])
        <div class="form-actions"><a class="btn" href="{{ route('meetings.index') }}">{{ __('equipment.cancel') }}</a><button class="primary" type="submit">{{ __('meetings.read') }}</button></div>
    </form>
</x-layouts.app>
