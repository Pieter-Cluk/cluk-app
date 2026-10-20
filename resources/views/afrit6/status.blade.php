<x-layouts.member :title="__('afrit6.status.title')">
    @if ($member->status === \App\Models\Member::DECLINED)
        <h1>{{ __('afrit6.status.declined_title') }}</h1>
        <p>{{ __('afrit6.status.declined_text') }}</p>
    @else
        <h1>{{ __('afrit6.status.pending_title', ['name' => $member->first_name]) }}</h1>
        <p>{{ __('afrit6.status.pending_text') }}</p>
    @endif
    @include('afrit6._messages')
    <ul class="status-list">
        <li class="ok">✓ {{ __('afrit6.status.email_ok') }}</li>
        <li @class(['ok' => $member->vies_status === 'valid', 'wait' => $member->vies_status !== 'valid'])>{{ $member->vies_status === 'valid' ? '✓ '.__('afrit6.status.vat_ok') : '! '.__('afrit6.status.vat_check') }}</li>
        <li @class(['wait' => $member->status === 'pending', 'bad' => $member->status === 'declined'])>{{ $member->status === 'declined' ? '✕ '.__('afrit6.status.not_approved') : '… '.__('afrit6.status.waiting') }}</li>
    </ul>
    <form method="post" action="{{ route('afrit6.logout') }}">@csrf<button class="linkbtn light" type="submit">{{ __('afrit6.logout') }}</button></form>
</x-layouts.member>
