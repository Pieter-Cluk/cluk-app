<x-layouts.app :title="__('psales.new')">
    <div class="pagehead"><div><div class="kicker"><a href="{{ route('psales.index') }}">{{ mb_strtoupper(__('nav.items.productsales')) }}</a></div><h1>{{ __('psales.new') }}</h1></div></div>
    @include('partials.flash')
    <form method="post" action="{{ route('psales.store') }}" class="card stack-sm">@csrf
        <label class="field"><span>{{ __('psales.customer') }}</span><select name="company_id" required><option value="">—</option>@foreach ($companies as $c)<option value="{{ $c->id }}" @selected((int) old('company_id', $company) === $c->id)>{{ $c->name }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('psales.title') }}</span><input name="title" required maxlength="200" value="{{ old('title') }}" placeholder="{{ __('psales.title_hint') }}"></label>
        <label class="field"><span>{{ __('quotes.language') }}</span><select name="language"><option value="">{{ __('psales.company_language') }}</option>@foreach (config('cluk.locales') as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach</select></label>
        <div><button class="primary" type="submit">{{ __('psales.create') }}</button></div>
    </form>
</x-layouts.app>
