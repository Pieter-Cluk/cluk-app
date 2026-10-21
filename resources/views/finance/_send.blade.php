{{-- "Send to pre-invoicing" for a source document, with the state of its pre-invoice. --}}
@php($pfs = \App\Models\Finance\PreInvoice::where('source_type', $type)->where('source_id', $id)->where('status', '!=', 'cancelled')->get())
<div class="pf-send">
    @foreach ($pfs as $pf)
        <p><span @class(['pill', 'pill-warn' => $pf->status === 'check', 'pill-danger' => $pf->status === 'back', 'pill-info' => $pf->status === 'sent', 'pill-ok' => $pf->status === 'paid'])>{{ $pf->number }} · {{ __('finance.statuses.'.$pf->status) }}</span>
            @if ($pf->status === 'back')<br><small>{{ $pf->back_note }}</small>@endif</p>
        @if ($pf->status === 'back')
            <form method="post" action="{{ route('finance.resend', $pf) }}" class="stack-sm">@csrf<input name="reply" maxlength="800" placeholder="{{ __('finance.reply_hint') }}" aria-label="{{ __('finance.reply_hint') }}"><div><button class="btn btn-sm" type="submit">{{ __('finance.resend') }}</button></div></form>
        @endif
    @endforeach
    @if ($pfs->isEmpty())
        <form method="post" action="{{ route('finance.make', [$type, $id]) }}">@csrf<button class="btn" type="submit">{{ __('finance.send_to') }}</button></form>
        <small class="muted">{{ __('finance.send_hint') }}</small>
    @endif
</div>
