@if (in_array($m->kind, \App\Models\Media\Message::MAILED, true))
    <span @class(['pill', 'pill-off' => $m->status === 'draft', 'pill-warn' => $m->status === 'scheduled', 'pill-info' => $m->status === 'sending', 'pill-ok' => $m->status === 'sent'])>{{ __('messages.statuses.'.$m->status) }}</span>
@else
    <span @class(['pill', 'pill-ok' => $m->active, 'pill-off' => ! $m->active])>{{ __($m->active ? 'messages.on' : 'messages.off') }}</span>
@endif
