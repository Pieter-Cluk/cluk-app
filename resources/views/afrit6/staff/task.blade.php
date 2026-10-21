@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
@php($me = auth()->user())
@php($st = $task->taskStatus())
<x-layouts.app :title="__('workspace.kinds.'.$task->kind)">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('afrit6.staff.tasks') }}">{{ mb_strtoupper(__('workspace.tasks.title')) }}</a></div>
            <h1>{{ __('workspace.kinds.'.$task->kind) }}</h1>
            <p class="muted">@include('afrit6.staff._task-title', ['t' => $task]) · {{ $task->date->locale(app()->getLocale())->translatedFormat('l j F') }}</p>
        </div>
        <span @class(['pill', 'pill-lg', 'pill-warn' => in_array($st, ['pending', 'waiting']), 'pill-danger' => $st === 'overdue', 'pill-info' => $st === 'confirmed', 'pill-ok' => $st === 'done', 'pill-off' => $st === 'declined'])>{{ __('workspace.tasks.status.'.$st) }}</span>
    </div>
    @include('partials.flash')

    <div class="split">
        <div class="stack">
            <section class="card">
                <h2>{{ __('workspace.tasks.details') }}</h2>
                <dl class="facts">
                    <dt>{{ __('workspace.tasks.cols.member') }}</dt><dd><a href="{{ route('afrit6.staff.show', $task->member) }}">{{ $task->member->name }}</a> · {{ $task->member->companyName() }}@if ($task->member->mobile) · <a href="tel:{{ $task->member->mobile }}">{{ $task->member->mobile }}</a>@endif</dd>
                    <dt>{{ __('workspace.tasks.cols.when') }}</dt><dd>{{ $task->date->format('d/m/Y') }}@if ($task->ends_on) · {{ __('workspace.tasks.until', ['date' => $task->ends_on->format('d/m/Y')]) }}@endif</dd>
                    @if ($task->from_text)<dt>{{ __('workspace.services.fields.from_text') }}</dt><dd>{{ $task->from_text }}</dd>@endif
                    @if ($task->carrier)<dt>{{ __('workspace.services.fields.carrier') }}</dt><dd>{{ $task->carrier }}</dd>@endif
                    @if ($task->booking)<dt>{{ __('workspace.room.title') }}</dt><dd>{{ $task->booking->timeRange() }} · {{ __('workspace.room.setups.'.$task->booking->setup) }}</dd>@endif
                    <dt>{{ __('workspace.tasks.fee') }}</dt>
                    <dd>
                        @if ($task->isStorage()){{ __('workspace.tasks.per_day', ['amount' => $eur($task->qty * \App\Services\Afrit6\Pricing::dayPrice($task->kind))]) }}
                        @elseif ($task->kind === 'desk_add'){{ __('workspace.desks.per_month', ['amount' => $eur($task->amount_cents)]) }}
                        @elseif ($task->kind === 'office'){{ __('workspace.office.price_on_request') }} · {{ __('workspace.office.staff_hint') }}
                        @else {{ $task->amount_cents ? $eur($task->amount_cents) : __('workspace.services.free') }}@endif
                    </dd>
                    <dt>{{ __('workspace.tasks.who_has_it') }}</dt><dd>{{ $task->assignee?->name ?? __('workspace.tasks.unassigned') }}</dd>
                    <dt>{{ __('workspace.tasks.requested', ['date' => '']) }}</dt><dd>{{ $task->created_at->format('d/m/Y H:i') }}</dd>
                </dl>
                @if ($task->note)<p><b>{{ __('workspace.tasks.member_note') }}:</b> {{ $task->note }}</p>@endif
                @if ($task->decline_reason)<p class="muted">{{ __('workspace.services.reason', ['reason' => $task->decline_reason]) }}</p>@endif

                <div class="form-actions">
                    @if ($task->isOpen() && $canAct)
                        @if ($task->status === 'pending')
                            <form method="post" action="{{ route('afrit6.staff.tasks.confirm', $task) }}">@csrf<button class="primary" type="submit">{{ $task->kind === 'desk_add' ? __('workspace.tasks.confirm_desk') : __('workspace.tasks.confirm') }}</button></form>
                        @endif
                        @if ($task->assignee_id === $me->id && $task->status === 'confirmed')
                            <form method="post" action="{{ route('afrit6.staff.tasks.done', $task) }}">@csrf<button class="primary" type="submit">{{ __('workspace.tasks.done') }}</button></form>
                        @elseif ($task->status === 'confirmed' && $task->assignee_id === null)
                            <form method="post" action="{{ route('afrit6.staff.tasks.take', $task) }}">@csrf<button class="primary" type="submit">{{ __('workspace.tasks.take') }}</button></form>
                        @endif
                    @elseif (! $task->isOpen() && $canAct && $task->kind !== 'desk_add')
                        <form method="post" action="{{ route('afrit6.staff.tasks.reopen', $task) }}">@csrf<button class="btn" type="submit">{{ __('workspace.tasks.reopen') }}</button></form>
                    @endif
                </div>
                @if ($task->isOpen() && $task->assignee && $task->assignee_id !== $me->id && $task->status === 'confirmed')
                    <p class="muted small">{{ __('workspace.tasks.only_assignee', ['name' => $task->assignee->name]) }}</p>
                @endif
            </section>

            <section class="card">
                <h2>{{ __('workspace.tasks.messages') }}</h2>
                @forelse ($task->messages as $m)
                    <div @class(['msg', 'msg-staff' => $m->fromStaff()])><small>{{ $m->fromStaff() ? $m->user?->name : $m->member?->name }} · {{ $m->created_at->format('d/m H:i') }}</small><div>{{ $m->body }}</div></div>
                @empty
                    <p class="muted">{{ __('workspace.tasks.no_messages') }}</p>
                @endforelse
                @if ($task->kind !== 'room_reset' && $me->canUseModule('afrit6', 2))
                    <form method="post" action="{{ route('afrit6.staff.tasks.message', $task) }}" class="form-grid form-grid-1 mt">
                        @csrf
                        <label class="field"><span>{{ __('workspace.tasks.write') }}</span><textarea name="body" rows="2" maxlength="2000" required></textarea></label>
                        @if (in_array($task->status, ['pending', 'confirmed']))
                            <label class="check"><input type="checkbox" name="wait" value="1"><span>{{ __('workspace.tasks.wait_for_answer') }}</span></label>
                        @endif
                        <div><button class="btn" type="submit">{{ __('workspace.tasks.send') }}</button></div>
                    </form>
                @endif
            </section>
        </div>

        <aside class="stack">
            @if ($task->isOpen() && $canAct)
                <section class="card">
                    <h2>{{ __('workspace.tasks.reassign') }}</h2>
                    <form method="post" action="{{ route('afrit6.staff.tasks.reassign', $task) }}" class="form-grid form-grid-1">
                        @csrf
                        <fieldset class="field radios cands">
                            <span>{{ __('workspace.tasks.reassign_to') }}</span>
                            @foreach ($candidates as $c)
                                <label @class(['check', 'is-off' => ! $c['ok']])>
                                    <input type="radio" name="assignee_id" value="{{ $c['user']->id }}" @disabled(! $c['ok'])>
                                    <span><b>{{ $c['user']->name }}</b> <small>{{ $c['user']->title }} · {{ __('workspace.tasks.why.'.$c['why']) }}</small></span>
                                </label>
                            @endforeach
                        </fieldset>
                        <label class="field"><span>{{ __('workspace.tasks.handover_note') }}</span><input name="note" maxlength="1000"></label>
                        <div><button class="btn" type="submit">{{ __('workspace.tasks.reassign') }}</button></div>
                    </form>
                </section>
                @if (in_array($task->kind, ['packing', 'receive', 'send']))
                    <section class="card">
                        <h2>{{ __('workspace.tasks.correct') }}</h2>
                        <form method="post" action="{{ route('afrit6.staff.tasks.amount', $task) }}" class="form-grid">
                            @csrf
                            <label class="field"><span>{{ __('workspace.services.fields.colis') }}</span><input type="number" name="colis" min="0" max="999" value="{{ $task->colis }}"></label>
                            <label class="field"><span>{{ __('workspace.services.fields.pallets') }}</span><input type="number" name="pallets" min="0" max="99" value="{{ $task->pallets }}"></label>
                            @if ($task->kind === 'receive')
                                <label class="field span-2"><span>{{ __('workspace.services.fields.receive_mode') }}</span>
                                    <select name="receive_mode"><option value="shelf24" @selected($task->receive_mode !== 'store')>{{ __('workspace.tasks.receive_shelf24') }}</option><option value="store" @selected($task->receive_mode === 'store')>{{ __('workspace.tasks.receive_store') }}</option></select>
                                </label>
                            @endif
                            <div class="span-2"><button class="btn" type="submit">{{ __('workspace.tasks.save') }}</button></div>
                        </form>
                    </section>
                @endif
                <section class="card">
                    <h2>{{ __('workspace.tasks.decline') }}</h2>
                    <form method="post" action="{{ route('afrit6.staff.tasks.decline', $task) }}" class="form-grid form-grid-1">
                        @csrf
                        <label class="field"><span>{{ __('workspace.tasks.decline_reason') }}</span><input name="reason" maxlength="500" required></label>
                        <div><button class="btn btn-danger" type="submit">{{ __('workspace.tasks.decline') }}</button></div>
                    </form>
                </section>
            @endif
            <section class="card">
                <h2>{{ __('workspace.tasks.history') }}</h2>
                @forelse ($activity as $log)
                    <div class="log-line"><small class="muted">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small><div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div></div>
                @empty
                    <p class="muted">{{ __('users.edit.no_activity') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.app>
