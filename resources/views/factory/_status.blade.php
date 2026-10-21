@php($st = $b->displayStatus())
<span @class(['pill', 'pill-warn' => in_array($st, ['request', 'option']), 'pill-ok' => $st === 'confirmed', 'pill-off' => in_array($st, ['done', 'declined', 'cancelled', 'expired'])])>{{ __('factory.statuses.'.$st) }}@if ($st === 'option') · {{ $b->option_until->format('d/m H:i') }}@endif</span>
