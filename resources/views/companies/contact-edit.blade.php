<x-layouts.app :title="$c->name">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ $back ?? route('companies.index', ['view' => 'contacts']) }}">{{ mb_strtoupper(__('companies.index.contacts')) }}</a> · <a href="{{ route('companies.show', $company) }}">{{ $company->name }}</a></div>
            <h1><span class="person"><x-avatar :who="$c"/>{{ $c->name }}</span></h1>
        </div>
    </div>
    @include('partials.flash')
    <form class="card" method="post" action="{{ route('companies.contacts.update', [$company, $c]) }}">
        @csrf @method('put')
        @if ($back)<input type="hidden" name="back" value="{{ $back }}">@endif
        @include('companies._contact-fields', ['c' => $c, 'pivot' => $c->pivot])
        <div class="form-actions">
            <button class="primary" type="submit">{{ __('settings.save') }}</button>
            <a class="btn" href="{{ $back ?? route('companies.show', $company) }}">{{ __('settings.cancel') }}</a>
        </div>
    </form>
    <section class="card mt">
        <form method="post" action="{{ route('companies.contacts.photo', [$company, $c]) }}" enctype="multipart/form-data" class="inline-form">
            @csrf
            <span class="muted small">{{ __('pictures.contact_photo') }}</span>
            <input type="file" name="photo" accept="image/*" required aria-label="{{ __('pictures.contact_photo') }}">
            <button class="btn" type="submit">{{ __('pictures.upload') }}</button>
        </form>
    </section>
    <div class="mt">@include('web._activity', ['act' => \App\Services\Website\WebActivity::summary(\App\Services\Website\WebActivity::visitorsOfContact($c))])</div>
</x-layouts.app>
