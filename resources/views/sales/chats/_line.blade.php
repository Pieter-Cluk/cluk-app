@if ($m->role === 'note')
    <div class="tc-note">{{ $m->body === 'handed_over' ? __('chats.handed') : $m->body }} · {{ $m->created_at?->timezone('Europe/Brussels')->format('d/m H:i') }}</div>
@else
    <div @class(['tc-msg', 'tc-'.$m->role]) data-id="{{ $m->id }}">
        <small class="tc-who">{{ match ($m->role) { 'visitor' => __('chats.roles.visitor'), 'clukie' => 'Clukie', default => ($staff[$m->user_id] ?? null)?->name ?? __('chats.roles.staff') } }} · {{ $m->created_at?->timezone('Europe/Brussels')->format('d/m H:i') }}@if (! empty($m->meta['mailed'])) · ✉ {{ __('chats.mailed') }}@endif</small>
        <div class="tc-b">{!! nl2br(e($m->body)) !!}</div>
        @if (! empty($m->meta['cards']))
            <div class="tc-cards">@foreach ($m->meta['cards'] as $card)<a class="tc-card" href="{{ $card['url'] ?? '#' }}" target="_blank" rel="noopener">@if (! empty($card['photo']))<img src="{{ $card['photo'] }}" alt="" loading="lazy">@endif<b>{{ $card['name'] ?? '' }}</b><small>{{ $card['code'] ?? '' }} · {{ ($card['price'] ?? 0) > 0 ? \App\Services\Afrit6\Pricing::eur((int) $card['price']) : __('chats.on_request') }}</small></a>@endforeach</div>
        @endif
    </div>
@endif
