<div class="pagehead">
    <div>
        <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }}</div>
        <h1>{{ __('nav.items.afrit6') }}</h1>
    </div>
    <a class="btn" href="{{ route('afrit6.login') }}" target="_blank" rel="noopener">{{ __('afrit6.staff.open_portal') }}</a>
</div>
@include('partials.flash')
@include('afrit6.staff._tabs', ['section' => $section])
