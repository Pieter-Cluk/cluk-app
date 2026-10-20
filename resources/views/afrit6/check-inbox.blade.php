<x-layouts.member :title="__('afrit6.register.inbox_title')">
    @include('afrit6._steps', ['step' => 3])
    <h1>{{ __('afrit6.register.inbox_title') }}</h1>
    <p>{{ __('afrit6.register.inbox_text', ['email' => $member->email]) }}</p>
    @include('afrit6._messages')
    <form method="post" action="{{ route('afrit6.resend') }}">@csrf<button class="ms-btn member-btn" type="submit">{{ __('afrit6.register.resend') }}</button></form>
    <p class="login-note">{{ __('afrit6.register.inbox_note') }}</p>
    <p class="login-note"><a href="{{ route('afrit6.login') }}">{{ __('afrit6.register.back_to_login') }}</a></p>
</x-layouts.member>
