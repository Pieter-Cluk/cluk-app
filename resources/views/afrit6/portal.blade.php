<x-layouts.member :title="__('afrit6.portal.title')" :wide="true">
    <div class="login-kicker">{{ mb_strtoupper($member->companyName()) }}</div>
    <h1>{{ __('afrit6.portal.welcome', ['name' => $member->first_name]) }}</h1>
    @include('afrit6._messages')
    <p>{{ __('afrit6.portal.intro') }}</p>
    <div class="portal-tiles">
        <div class="portal-tile"><b>{{ __('afrit6.portal.desks') }}</b><span>{{ __('afrit6.portal.soon') }}</span></div>
        <div class="portal-tile"><b>{{ __('afrit6.portal.rooms') }}</b><span>{{ __('afrit6.portal.soon') }}</span></div>
        <div class="portal-tile"><b>{{ __('afrit6.portal.warehouse') }}</b><span>{{ __('afrit6.portal.soon') }}</span></div>
    </div>
    <p class="login-note">{{ __('afrit6.portal.account', ['email' => $member->email]) }}</p>
    <form method="post" action="{{ route('afrit6.logout') }}">@csrf<button class="linkbtn light" type="submit">{{ __('afrit6.logout') }}</button></form>
</x-layouts.member>
