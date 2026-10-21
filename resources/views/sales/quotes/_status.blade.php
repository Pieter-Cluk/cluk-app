@php($st = $quote->displayStatus())
<span @class(['pill', 'pill-lg' => $lg ?? false, 'pill-off' => in_array($st, ['draft', 'replaced']), 'pill-info' => $st === 'sent', 'pill-ok' => $st === 'accepted', 'pill-warn' => in_array($st, ['change', 'expired']), 'pill-danger' => $st === 'declined'])>{{ __('quotes.statuses.'.$st) }}</span>
