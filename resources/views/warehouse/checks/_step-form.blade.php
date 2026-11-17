{{-- One step of the draft: titles and instructions in Dutch and English, the answer, choices, costs, reference photos. --}}
@php($s = $s ?? null)
@php($say = fn ($t) => \App\Models\Warehouse\CheckProcedure::say($t))
<form method="post" action="{{ route('checks.step', $p) }}" enctype="multipart/form-data" class="stack-sm">
    @csrf
    @if ($s)<input type="hidden" name="key" value="{{ $s['key'] }}">@endif
    <div class="form-grid">
        <label class="field"><span>{{ __('checks.builder.title') }} (NL)</span><input name="title_nl" maxlength="120" value="{{ $s['title']['nl'] ?? '' }}" placeholder="{{ __('checks.builder.title_hint') }}"></label>
        <label class="field"><span>{{ __('checks.builder.title') }} (EN)</span><input name="title_en" maxlength="120" value="{{ $s['title']['en'] ?? '' }}"></label>
        <label class="field"><span>{{ __('checks.builder.text') }} (NL)</span><textarea name="text_nl" rows="3" maxlength="2000" placeholder="{{ __('checks.builder.text_hint') }}">{{ $s['text']['nl'] ?? '' }}</textarea></label>
        <label class="field"><span>{{ __('checks.builder.text') }} (EN)</span><textarea name="text_en" rows="3" maxlength="2000">{{ $s['text']['en'] ?? '' }}</textarea></label>
        <label class="field"><span>{{ __('checks.builder.answer') }}</span><select name="answer">@foreach (\App\Models\Warehouse\CheckProcedure::ANSWERS as $a)<option value="{{ $a }}" @selected(($s['answer'] ?? 'okfail') === $a)>{{ __('checks.answers.'.$a) }}</option>@endforeach</select></label>
        <label class="field"><span>{{ __('checks.builder.choices') }}</span><textarea name="choices" rows="2" placeholder="{{ __('checks.builder.choices_hint') }}">{{ collect($s['choices'] ?? [])->map(fn ($c) => $c['nl'].' | '.$c['en'])->implode("\n") }}</textarea></label>
        <label class="field cb-wide"><span>{{ __('checks.builder.costs') }}</span><textarea name="costs" rows="3" placeholder="{{ __('checks.builder.costs_hint') }}">{{ collect($s['costs'] ?? [])->map(fn ($c) => $c['label']['nl'].' | '.$c['label']['en'].(isset($c['cents']) ? ' | '.number_format($c['cents'] / 100, 2, ',', '') : ''))->implode("\n") }}</textarea></label>
    </div>
    <div class="cb-photos">
        @foreach ($s['photos'] ?? [] as $i => $ph)
            <label class="cb-photo"><img src="{{ route('checks.photo', ['p' => $ph]) }}" alt="" loading="lazy"><span class="check"><input type="checkbox" name="remove[]" value="{{ $i }}"> {{ __('checks.builder.remove_photo') }}</span></label>
        @endforeach
        <label class="field"><span>{{ __('checks.builder.photos') }}</span><input type="file" name="photos[]" accept="image/*" multiple></label>
    </div>
    <div><button class="btn primary" type="submit">{{ $s ? __('checks.builder.save_step') : __('checks.builder.add_step') }}</button></div>
</form>
