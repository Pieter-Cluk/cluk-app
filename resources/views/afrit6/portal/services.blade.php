@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
@php($p = fn ($k) => $eur((int) config('afrit6.prices.'.$k)))
@php($openKind = old('kind', $open))
<x-layouts.portal :title="__('workspace.services.title')" tab="services">
    <div class="pagehead">
        <div>
            <h1>{{ __('workspace.services.title') }}</h1>
            <p class="muted">{{ __('workspace.services.intro') }}</p>
        </div>
    </div>

    <div class="split">
        <section class="stack">
            <h2 class="h2">{{ __('workspace.services.your_requests') }}</h2>
            @forelse ($requests as $r)
                @php($running = $r->isStorage() && $r->status === 'confirmed' && (! $r->ends_on || $r->ends_on->gte(today())))
                @php($ended = $r->isStorage() && $r->ends_on && $r->ends_on->lt(today()))
                <article @class(['card', 'req', 'req-wait' => $r->status === 'waiting'])>
                    <header class="req-head">
                        <div>
                            <b>{{ __('workspace.kinds.'.$r->kind) }}</b>
                            <div class="muted small">
                                @switch($r->kind)
                                    @case('shelf') @case('pallet')
                                        {{ $r->qty }} × · {{ __('workspace.services.since', ['date' => $r->date->format('d/m/Y')]) }}@if ($r->ends_on) · {{ __('workspace.services.last_day', ['date' => $r->ends_on->format('d/m/Y')]) }}@endif · {{ __('workspace.services.days_price', ['amount' => $eur(\App\Services\Afrit6\Pricing::dayPrice($r->kind))]) }}
                                        @break
                                    @case('desk_add')
                                        {{ $r->space?->code }} · {{ __('workspace.desks.from', ['date' => $r->date->format('d/m/Y')]) }}
                                        @break
                                    @case('desk_swap')
                                        {{ $r->fromSpace?->code }} → {{ $r->space?->code }} · {{ __('workspace.desks.from', ['date' => $r->date->format('d/m/Y')]) }}
                                        @break
                                    @default
                                        {{ $r->date->format('d/m/Y') }} · {{ \App\Services\Afrit6\StatementBuilder::counts($r->colis, $r->pallets) }}@if ($r->from_text) · {{ $r->from_text }}@endif @if ($r->carrier) · {{ $r->carrier }}@endif
                                        · {{ $r->amount_cents ? $eur($r->amount_cents) : __('workspace.services.free') }}
                                @endswitch
                            </div>
                        </div>
                        <span @class(['pill', 'pill-warn' => in_array($r->status, ['pending', 'waiting']), 'pill-ok' => $r->status === 'confirmed' || $r->status === 'done', 'pill-off' => $r->status === 'declined' || $ended])>
                            {{ $ended ? __('workspace.services.status.ended') : ($running ? __('workspace.services.status.running') : __('workspace.services.status.'.$r->status)) }}
                        </span>
                    </header>
                    @if ($r->note)<p class="small">{{ $r->note }}</p>@endif
                    @if ($r->status === 'declined' && $r->decline_reason)<p class="small muted">{{ __('workspace.services.reason', ['reason' => $r->decline_reason]) }}</p>@endif
                    @foreach ($r->messages as $m)
                        <div @class(['msg', 'msg-staff' => $m->fromStaff()])><small>{{ $m->fromStaff() ? __('workspace.services.team') : __('workspace.services.you') }} · {{ $m->created_at->format('d/m H:i') }}</small><div>{{ $m->body }}</div></div>
                    @endforeach
                    <div class="req-actions">
                        @if ($r->isOpen() || $running)
                            <form method="post" action="{{ route('afrit6.portal.services.reply', $r) }}" class="reply">
                                @csrf
                                <input name="body" maxlength="2000" required placeholder="{{ __('workspace.services.reply_placeholder') }}" aria-label="{{ __('workspace.services.reply') }}">
                                <button class="btn" type="submit">{{ __('workspace.services.reply') }}</button>
                            </form>
                        @endif
                        @if ($r->status === 'pending' || ($r->status === 'waiting' && $r->status_before_wait === 'pending'))
                            <form method="post" action="{{ route('afrit6.portal.services.cancel', $r) }}">@csrf<button class="linkbtn" type="submit">{{ __('workspace.services.cancel') }}</button></form>
                        @endif
                        @if ($running)
                            <form method="post" action="{{ route('afrit6.portal.services.stop', $r) }}" onsubmit="return confirm(@js(__('workspace.services.stop_confirm')))">@csrf<button class="linkbtn" type="submit">{{ __('workspace.services.stop') }}</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <section class="card"><p class="muted">{{ __('workspace.services.none') }}</p></section>
            @endforelse
        </section>

        <aside class="stack">
            <section class="card card-accent">
                <h2>{{ __('workspace.services.new') }}</h2>
                @foreach (['shelf', 'pallet', 'packing', 'receive', 'send'] as $kind)
                    <details class="svc" @if ($openKind === $kind) open @endif>
                        <summary><b>{{ __('workspace.kinds.'.$kind) }}</b>
                            <small class="muted">{{ __('workspace.services.help.'.$kind, ['amount' => $kind === 'shelf' ? $p('shelf_day') : $p('pallet_day'), 'coli' => $kind === 'packing' ? $p('packing_coli') : $p('handling_coli'), 'pallet' => $kind === 'packing' ? $p('packing_pallet') : $p('handling_pallet')]) }}</small>
                        </summary>
                        <form method="post" action="{{ route('afrit6.portal.services.store') }}" class="form-grid">
                            @csrf
                            <input type="hidden" name="kind" value="{{ $kind }}">
                            @if (in_array($kind, ['shelf', 'pallet']))
                                <label class="field"><span>{{ __('workspace.services.fields.qty_'.$kind) }}</span><input type="number" name="qty" min="1" max="50" value="{{ $openKind === $kind ? old('qty', 1) : 1 }}" required></label>
                            @else
                                <label class="field"><span>{{ __('workspace.services.fields.colis') }}</span><input type="number" name="colis" min="0" max="999" value="{{ $openKind === $kind ? old('colis', 0) : 0 }}"></label>
                                <label class="field"><span>{{ __('workspace.services.fields.pallets') }}</span><input type="number" name="pallets" min="0" max="99" value="{{ $openKind === $kind ? old('pallets', 0) : 0 }}"></label>
                            @endif
                            <label class="field"><span>{{ __('workspace.services.fields.date.'.$kind) }}</span><input type="date" name="date" min="{{ today()->toDateString() }}" value="{{ $openKind === $kind ? old('date', today()->toDateString()) : today()->toDateString() }}" required></label>
                            @if ($kind === 'receive')
                                <label class="field span-2"><span>{{ __('workspace.services.fields.from_text') }}</span><input name="from_text" maxlength="160" value="{{ $openKind === $kind ? old('from_text') : '' }}"></label>
                                <fieldset class="field span-2 radios"><span>{{ __('workspace.services.fields.receive_mode') }}</span>
                                    <label class="check"><input type="radio" name="receive_mode" value="shelf24" @checked(($openKind === $kind ? old('receive_mode', 'shelf24') : 'shelf24') === 'shelf24')><span>{{ __('workspace.services.fields.shelf24') }}</span></label>
                                    <label class="check"><input type="radio" name="receive_mode" value="store" @checked($openKind === $kind && old('receive_mode') === 'store')><span>{{ __('workspace.services.fields.store') }}</span></label>
                                </fieldset>
                            @endif
                            @if ($kind === 'packing')
                                <label class="check span-2"><input type="checkbox" name="then_send" value="1" @checked($openKind === $kind && old('then_send'))><span>{{ __('workspace.services.fields.then_send') }}</span></label>
                            @endif
                            @if (in_array($kind, ['packing', 'send']))
                                <label class="field span-2"><span>{{ __('workspace.services.fields.carrier') }}</span><input name="carrier" maxlength="120" value="{{ $openKind === $kind ? old('carrier') : '' }}"></label>
                            @endif
                            <label class="field span-2"><span>{{ __('workspace.services.fields.note') }}</span><textarea name="note" rows="2" maxlength="1000" placeholder="{{ __('workspace.services.fields.note_hint.'.$kind) }}">{{ $openKind === $kind ? old('note') : '' }}</textarea></label>
                            <div class="span-2"><button class="primary" type="submit">{{ __('workspace.services.send_btn') }}</button></div>
                        </form>
                    </details>
                @endforeach
            </section>
        </aside>
    </div>
</x-layouts.portal>
