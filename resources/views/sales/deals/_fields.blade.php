<div class="form-grid">
    <label class="field span-2"><span>{{ __('deals.fields.title') }}</span><input name="title" required maxlength="200" value="{{ old('title', $deal->title) }}" placeholder="{{ __('deals.title_hint') }}"></label>
    <label class="field"><span>{{ __('deals.fields.module') }}</span><select name="module">@foreach (\App\Models\Sales\Deal::MODULES as $m)<option value="{{ $m }}" @selected(old('module', $deal->module) === $m)>{{ __('deals.modules.'.$m) }}</option>@endforeach</select></label>
    <label class="field"><span>{{ __('deals.fields.source') }}</span><select name="source"><option value="">—</option>@foreach (\App\Models\Sales\Deal::SOURCES as $s)<option value="{{ $s }}" @selected(old('source', $deal->source) === $s)>{{ __('deals.sources.'.$s) }}</option>@endforeach</select></label>
    <label class="field"><span>{{ __('deals.fields.company_id') }}</span>
        <select data-pick name="company_id"><option value="">{{ __('deals.private') }}</option>@foreach ($companies ?? \App\Models\Company::active()->orderBy('name')->get(['id', 'name']) as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $deal->company_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
    </label>
    @if ($contacts->isNotEmpty())
        <label class="field"><span>{{ __('deals.fields.contact_id') }}</span><select name="contact_id"><option value="">—</option>@foreach ($contacts as $c)<option value="{{ $c->id }}" @selected((string) old('contact_id', $deal->contact_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select></label>
    @else
        <input type="hidden" name="contact_id" value="{{ $deal->contact_id }}">
    @endif
    <label class="field"><span>{{ __('deals.fields.owner_id') }}</span><select name="owner_id">@foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('owner_id', $deal->owner_id) === (string) $o->id)>{{ $o->name }}</option>@endforeach</select></label>
    <label class="field"><span>{{ __('deals.fields.value') }}</span><input type="number" step="0.01" min="0" name="value" value="{{ old('value', $deal->value_cents ? number_format($deal->value_cents / 100, 2, '.', '') : '') }}"><small>{{ __('deals.value_hint') }}</small></label>
    <label class="field"><span>{{ __('deals.fields.event_start') }}</span><input type="date" name="event_start" value="{{ old('event_start', $deal->event_start?->toDateString()) }}"></label>
    <label class="field"><span>{{ __('deals.fields.event_end') }}</span><input type="date" name="event_end" value="{{ old('event_end', $deal->event_end?->toDateString()) }}"></label>
    <label class="field span-2"><span>{{ __('deals.fields.location') }}</span><input name="location" maxlength="160" value="{{ old('location', $deal->location) }}"></label>
</div>
