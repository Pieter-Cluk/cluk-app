{{-- Tech Check: the item's procedure, one step per screen. The next step shows only when this one is answered. --}}
@php($say = fn ($t) => \App\Models\Warehouse\CheckProcedure::say($t))
@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($steps = $v->steps)
@php($n = count($steps))
@php($step = $done ? null : $steps[$check->step] ?? null)
@php($prev = $step ? (($check->answers ?? [])[$step['key']] ?? null) : null)
<x-layouts.phone :title="__('checks.run.title', ['code' => $asset->code])" :back="route('w.home')">
    <section class="ph-card">
        <b>{{ $asset->item->name }} · {{ $asset->code }}</b>
        <small>@if ($asset->serial_number)S/N {{ $asset->serial_number }} · @endif{{ $check->order ? __('warehouse.check.from', ['order' => $check->order->number, 'customer' => $check->order->customerName()]) : __('warehouse.check.no_order') }}</small>
        <small>{{ $v->procedure->name() }} · v{{ $v->version }} · {{ $check->cleaned_at ? '✓ '.__('warehouse.check.cleaned', ['stage' => $check->dirt, 'stage_name' => __('warehouse.clean.stages.'.$check->dirt)]) : __('warehouse.check.not_cleaned') }}</small>
    </section>
    <ol class="tc-progress" aria-label="{{ __('checks.run.progress') }}">
        @foreach ($steps as $i => $s)
            @php($a = ($check->answers ?? [])[$s['key']] ?? null)
            <li @class(['is-done' => $a && $i < $check->step, 'is-fail' => ($a['answer'] ?? null) === 'fail', 'is-now' => ! $done && $i === $check->step])></li>
        @endforeach
    </ol>

    @if ($step)
        <form method="post" action="{{ route('w.check.step', $asset) }}" enctype="multipart/form-data" class="tc-step">
            @csrf
            <input type="hidden" name="photo_only" value="0" data-photo-only>
            <div class="tc-kicker">{{ __('checks.run.step_of', ['n' => $check->step + 1, 'total' => $n]) }}</div>
            <h2 class="tc-title">{{ $say($step['title']) }}</h2>
            @if ($say($step['text']))<p class="tc-text">{!! nl2br(e($say($step['text']))) !!}</p>@endif
            @if (! empty($step['photos']))
                <div class="tc-ref">@foreach ($step['photos'] as $p)<a href="{{ route('checks.photo', ['p' => $p]) }}" target="_blank"><img src="{{ route('checks.photo', ['p' => $p]) }}" alt="{{ __('checks.run.reference') }}" loading="lazy"></a>@endforeach</div>
            @endif

            <div class="tc-answer">
                @switch($step['answer'])
                    @case('confirm')
                        <label class="tc-big tc-ok"><input type="radio" name="answer" value="done" required @checked(old('answer', $prev['answer'] ?? null) === 'done')> ✓ {{ __('checks.run.done') }}</label>
                        @break
                    @case('okfail')
                        <label class="tc-big tc-ok"><input type="radio" name="answer" value="ok" required @checked(old('answer', $prev['answer'] ?? null) === 'ok') data-fail-off> ✓ OK</label>
                        <label class="tc-big tc-fail"><input type="radio" name="answer" value="fail" @checked(old('answer', $prev['answer'] ?? null) === 'fail') data-fail-on> ✕ {{ __('checks.run.not_ok') }}</label>
                        @break
                    @case('value')
                        <label class="field"><span>{{ __('checks.run.value') }}</span><input name="value" required maxlength="120" value="{{ old('value', $prev['value'] ?? '') }}" inputmode="text"></label>
                        @break
                    @case('choice')
                        @foreach ($step['choices'] as $ci => $c)
                            <label class="tc-big"><input type="radio" name="value" value="{{ $ci }}" required @checked((string) old('value') === (string) $ci || (! old('value') && ($prev['value'] ?? null) === $say($c)))> {{ $say($c) }}</label>
                        @endforeach
                        @break
                    @case('photo')
                        <p class="muted small">{{ __('checks.run.photo_step') }}</p>
                        @break
                @endswitch
            </div>

            <h3 class="ph-h">{{ __('warehouse.check.photos') }}</h3>
            @include('warehouse.phone._photos', ['stage' => 'step:'.$step['key']])

            <div class="tc-fail-box" @if ($step['answer'] === 'okfail') data-fail-box @endif>
                <label class="field"><span>{{ $step['answer'] === 'okfail' ? __('checks.run.note_fail') : __('checks.run.note') }}</span><textarea name="note" rows="2" maxlength="500">{{ old('note', $prev['note'] ?? '') }}</textarea></label>
                @if (! empty($step['costs']))
                    <div class="tc-costs"><b>{{ __('checks.run.costs') }}</b>
                        @foreach ($step['costs'] as $ci => $c)
                            <label class="ph-acc"><span>{{ $say($c['label']) }}</span><span class="nowrap">{{ isset($c['cents']) ? $eur($c['cents']) : __('checks.run.price_later') }} <input type="checkbox" name="costs[]" value="{{ $ci }}" @checked(in_array($ci, old('costs', $prev['costs'] ?? [])))></span></label>
                        @endforeach
                    </div>
                @endif
                <details class="ph-more" @if (old('extra_label', $prev['extra']['label'] ?? null)) open @endif><summary>{{ __('checks.run.other_cost') }}</summary>
                    <div class="ph-row"><input name="extra_label" maxlength="120" value="{{ old('extra_label', $prev['extra']['label'] ?? '') }}" placeholder="{{ __('checks.run.other_cost_hint') }}" aria-label="{{ __('checks.run.other_cost') }}">
                        <input name="extra_amount" type="number" step="0.01" min="0" class="ph-qty" value="{{ old('extra_amount', isset($prev['extra']['cents']) ? number_format($prev['extra']['cents'] / 100, 2, '.', '') : '') }}" placeholder="€" aria-label="{{ __('warehouse.office.amount') }}"></div>
                </details>
            </div>
            @error('answer')<p class="flash flash-err">{{ $message }}</p>@enderror
            @error('note')<p class="flash flash-err">{{ $message }}</p>@enderror

            <button class="primary ph-big" type="submit">{{ $check->step + 1 < $n ? __('checks.run.next') : __('checks.run.last') }}</button>
        </form>
        @if ($check->step > 0)
            <form method="post" action="{{ route('w.check.back', $asset) }}">@csrf<button class="btn ph-big" type="submit">← {{ __('checks.run.back') }}</button></form>
        @endif
    @else
        {{-- All steps answered: the summary, missing kit parts, finish. --}}
        <form method="post" action="{{ route('w.check.finish', $asset) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="photo_only" value="0" data-photo-only>
            <h2 class="ph-h">{{ __('checks.run.summary') }}</h2>
            <ul class="tc-sum">
                @foreach ($steps as $s)
                    @php($a = $check->answers[$s['key']] ?? [])
                    <li @class(['is-fail' => ($a['answer'] ?? null) === 'fail'])><b>{{ ($a['answer'] ?? null) === 'fail' ? '✕' : '✓' }} {{ $say($s['title']) }}</b>
                        @if ($a['value'] ?? null)<small>{{ $a['value'] }}</small>@endif
                        @if ($a['note'] ?? null)<small>{{ $a['note'] }}</small>@endif
                        @foreach ($a['costs'] ?? [] as $ci)<small class="txt-warn">€ {{ $say($s['costs'][$ci]['label'] ?? []) }}@if (isset($s['costs'][$ci]['cents'])) · {{ $eur($s['costs'][$ci]['cents']) }}@endif</small>@endforeach
                        @if ($a['extra'] ?? null)<small class="txt-warn">€ {{ $a['extra']['label'] }}@if ($a['extra']['cents'] !== null) · {{ $eur($a['extra']['cents']) }}@endif</small>@endif
                    </li>
                @endforeach
            </ul>
            <h2 class="ph-h">{{ __('warehouse.check.accessories') }}</h2>
            @forelse ($asset->item->kit as $k)
                <label class="ph-acc"><span><b>{{ $k->name }}</b><small>{{ $k->number }} · {{ __('warehouse.check.per_piece', ['n' => $k->pivot->qty]) }}@if ($k->catalog_price_cents) · {{ $eur($k->catalog_price_cents) }}@endif</small></span>
                    <select name="missing[{{ $k->id }}]" aria-label="{{ __('warehouse.check.missing_of', ['name' => $k->name]) }}"><option value="0">{{ __('warehouse.check.complete') }}</option>@for ($i = 1; $i <= max(1, $k->pivot->qty); $i++)<option value="{{ $i }}">{{ __('warehouse.check.missing_n', ['n' => $i]) }}</option>@endfor</select></label>
            @empty
                <p class="muted small">{{ __('warehouse.check.no_kit') }}</p>
            @endforelse
            @error('photos')<p class="flash flash-err">{{ $message }}</p>@enderror
            @error('steps')<p class="flash flash-err">{{ $message }}</p>@enderror
            <button class="primary ph-big" type="submit">{{ __('warehouse.check.finish') }}</button>
        </form>
        <form method="post" action="{{ route('w.check.back', $asset) }}">@csrf<button class="btn ph-big" type="submit">← {{ __('checks.run.back') }}</button></form>
    @endif
    <script>
    (function () {
        var box = document.querySelector('[data-fail-box]');
        if (!box) return;
        function show() { var f = document.querySelector('[data-fail-on]'); box.hidden = !(f && f.checked); }
        document.querySelectorAll('[data-fail-on],[data-fail-off]').forEach(function (r) { r.addEventListener('change', show); });
        show();
    })();
    </script>
</x-layouts.phone>
