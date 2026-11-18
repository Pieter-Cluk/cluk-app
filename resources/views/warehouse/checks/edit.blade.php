@php($say = fn ($t) => \App\Models\Warehouse\CheckProcedure::say($t))
@php($steps = $p->draft_steps ?? [])
<x-layouts.app :title="$p->name()">
    <div class="pagehead"><div>
        <div class="kicker"><a href="{{ route('checks.index') }}">{{ mb_strtoupper(__('nav.items.checkbuilder')) }}</a></div>
        <h1>{{ $p->item?->name ?? $p->name() }}</h1>
        @if ($p->item)<p class="muted"><a href="{{ route('equipment.show', $p->item) }}">{{ $p->item->number }} · {{ __('checks.builder.open_card') }} →</a></p>@endif
        <p class="muted">@if ($p->minutes)⏱ {{ __('checks.builder.duration', ['n' => $p->minutes]) }} · @endif @if ($p->current){{ __('checks.builder.live', ['n' => $p->current->version, 'date' => $p->current->created_at->format('d/m/Y')]) }}@else{{ __('checks.builder.not_published') }}@endif
            @if ($p->draftChanged()) · <b class="txt-warn">{{ __('checks.builder.changes_hint') }}</b>@endif</p>
    </div>
        <form method="post" action="{{ route('checks.copy', $p) }}">@csrf<button class="btn" type="submit">{{ __('checks.builder.copy') }}</button></form>
    </div>
    @include('partials.flash')

    <div class="cb-grid">
        <div class="stack">
            <section class="card cb-fixed">
                <div class="cb-fixed-head"><span class="cb-n">1</span><b>{{ __('checks.run.view_title') }}</b> <span class="pill">{{ __('checks.builder.automatic') }}</span></div>
                <p class="muted small">{{ __('checks.builder.view_hint', ['c2' => \App\Services\Afrit6\Pricing::eur((int) config('warehouse.cleaning')[2]), 'c3' => \App\Services\Afrit6\Pricing::eur((int) config('warehouse.cleaning')[3])]) }}</p>
            </section>
            @forelse ($steps as $i => $s)
                <div class="cb-row">
                <div class="cb-move" aria-label="{{ __('checks.builder.order') }}">
                    <form method="post" action="{{ route('checks.step.move', [$p, $s['key']]) }}">@csrf<input type="hidden" name="dir" value="up"><button class="iconbtn" type="submit" title="{{ __('checks.builder.up') }}" aria-label="{{ __('checks.builder.up') }}" @disabled($i === 0)>↑</button></form>
                    <form method="post" action="{{ route('checks.step.move', [$p, $s['key']]) }}">@csrf<input type="hidden" name="dir" value="down"><button class="iconbtn" type="submit" title="{{ __('checks.builder.down') }}" aria-label="{{ __('checks.builder.down') }}" @disabled($i === count($steps) - 1)>↓</button></form>
                </div>
                <details class="card cb-step" id="step-{{ $s['key'] }}" @if (session('open') === $s['key']) open @endif>
                    <summary><span class="cb-n">{{ $i + 2 }}</span><b>{{ $say($s['title']) ?: '—' }}</b> <span class="muted small">{{ __('checks.answers.'.$s['answer']) }}@if (! empty($s['photos'])) · 📷 {{ count($s['photos']) }}@endif @if (! empty($s['costs'])) · € {{ count($s['costs']) }}@endif</span></summary>
                    @include('warehouse.checks._step-form', ['s' => $s])
                    <div class="inline-form mt">
                        <form method="post" action="{{ route('checks.step.delete', [$p, $s['key']]) }}">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('checks.builder.delete_step') }}</button></form>
                    </div>
                </details>
                </div>
            @empty
                <p class="muted">{{ __('checks.builder.no_steps_yet') }}</p>
            @endforelse
            <section class="card">
                <h2>{{ __('checks.builder.new_step', ['n' => count($steps) + 2]) }}</h2>
                @include('warehouse.checks._step-form', ['s' => null])
            </section>
            @if ($p->item && $p->item->kit->isNotEmpty())
                <section class="card cb-fixed">
                    <div class="cb-fixed-head"><span class="cb-n">{{ count($steps) + 2 }}</span><b>{{ __('checks.run.kit_title') }}</b> <span class="pill">{{ __('checks.builder.automatic') }}</span></div>
                    <p class="muted small">{{ $p->item->kit->map(fn ($k) => $k->pivot->qty.' × '.$k->name)->implode(' · ') }}</p>
                </section>
            @endif
        </div>

        <aside class="stack">
            <section class="card">
                <h2>{{ __('checks.builder.publish_title') }}</h2>
                <p class="muted small">{{ __('checks.builder.publish_hint') }}</p>
                @error('publish')<p class="flash flash-err">{{ $message }}</p>@enderror
                <form method="post" action="{{ route('checks.publish', $p) }}" class="stack-sm">@csrf
                    <input name="note" maxlength="200" placeholder="{{ __('checks.builder.publish_note') }}" aria-label="{{ __('checks.builder.publish_note') }}">
                    <div><button class="primary" type="submit" @disabled(! $p->draftChanged())>{{ __('checks.builder.publish', ['n' => ($p->current?->version ?? 0) + 1]) }}</button></div>
                </form>
                @if ($p->versions->isNotEmpty())
                    <ul class="cb-versions">@foreach ($p->versions as $v)<li>v{{ $v->version }} · {{ $v->created_at->format('d/m/Y') }} · {{ $v->publishedBy?->first_name }}@if ($v->note) · <span class="muted">{{ $v->note }}</span>@endif</li>@endforeach</ul>
                @endif
            </section>
            @if ($p->item)
                <section class="card">
                    <h2>{{ __('checks.builder.kit_title') }}</h2>
                    <p class="muted small">{{ __('checks.builder.kit_hint') }}</p>
                    <ul class="cb-items">@forelse ($p->item->kit as $k)<li>{{ $k->pivot->qty }} × {{ $k->name }} <small class="muted">{{ $k->number }}</small></li>@empty<li class="muted">{{ __('checks.builder.kit_none') }}</li>@endforelse</ul>
                </section>
            @endif
            <section class="card">
                <h2>{{ __('checks.builder.items') }} <small class="muted">{{ $p->items->count() }}</small></h2>
                <p class="muted small">{{ __('checks.builder.items_hint') }}</p>
                <ul class="cb-items">
                    @foreach ($p->items as $it)
                        <li>{{ $it->name }} <small class="muted">{{ $it->number }}</small>
                            <form method="post" action="{{ route('checks.unassign', [$p, $it]) }}">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('checks.builder.unassign') }}</button></form></li>
                    @endforeach
                </ul>
                <form method="post" action="{{ route('checks.assign', $p) }}" class="stack-sm">@csrf
                    <select data-pick name="items[]" multiple size="6" aria-label="{{ __('checks.builder.add_items') }}">
                        @foreach ($allItems->where('check_procedure_id', '!=', $p->id) as $it)<option value="{{ $it->id }}">{{ $it->name }} · {{ $it->number }}@if ($it->check_procedure_id) ({{ __('checks.builder.has_other') }})@endif</option>@endforeach
                    </select>
                    <div><button class="btn" type="submit">{{ __('checks.builder.add_items') }}</button></div>
                </form>
            </section>
            <section class="card">
                <h2>{{ __('checks.builder.settings') }}</h2>
                <form method="post" action="{{ route('checks.update', $p) }}" class="stack-sm">@csrf @method('PUT')
                    <label class="field"><span>{{ __('checks.builder.name') }} (NL)</span><input name="name_nl" maxlength="120" value="{{ $p->names['nl'] ?? '' }}"></label>
                    <label class="field"><span>{{ __('checks.builder.name') }} (EN)</span><input name="name_en" maxlength="120" value="{{ $p->names['en'] ?? '' }}"></label>
                    <label class="field"><span>{{ __('checks.builder.minutes') }}</span><input type="number" name="minutes" min="1" max="600" class="w-xs" value="{{ $p->minutes }}"><small class="muted">{{ __('checks.builder.minutes_hint') }}</small></label>
                    <input type="hidden" name="active" value="0"><label class="check"><input type="checkbox" name="active" value="1" @checked($p->active)> {{ __('checks.builder.active') }}</label>
                    <div><button class="btn" type="submit">{{ __('checks.builder.save') }}</button></div>
                </form>
            </section>
            <details class="card">
                <summary class="linkbtn danger">{{ __('checks.builder.delete') }}</summary>
                <form method="post" action="{{ route('checks.destroy', $p) }}" class="stack-sm" onsubmit="return confirm(@js(__('checks.builder.delete_confirm')))">@csrf @method('DELETE')
                    <p class="muted small">{{ __('checks.builder.delete_hint') }}</p>
                    <div><button class="btn danger" type="submit">{{ __('checks.builder.delete_go') }}</button></div>
                </form>
            </details>
        </aside>
    </div>
    <script>(function () { var d = location.hash && document.querySelector(location.hash + '.cb-step'); if (d) d.open = true; })();</script>
</x-layouts.app>
