{{-- Meeting details and notes (new meeting, and change on the meeting page). --}}
@php($att = array_map('intval', old('attendees', $m->attendees ?? [auth()->id()])))
<div class="form-grid">
    <label class="field span-2"><span>{{ __('meetings.fields.title') }}</span><input name="title" required maxlength="200" value="{{ old('title', $m->title) }}" placeholder="{{ __('meetings.fields.title_ph') }}"></label>
    <label class="field"><span>{{ __('meetings.fields.held_on') }}</span><input type="date" name="held_on" required value="{{ old('held_on', ($m->held_on ?? today())->toDateString()) }}"></label>
    <div class="field"><span>{{ __('meetings.fields.kind') }}</span>
        <div class="chips mt-xs">@foreach (\App\Models\Work\Meeting::KINDS as $k)<label class="chip chip-radio"><input type="radio" name="kind" value="{{ $k }}" @checked(old('kind', $m->kind ?? 'internal') === $k)> {{ __('meetings.kinds.'.$k) }}</label>@endforeach</div>
    </div>
    <label class="field span-2 mt-company"><span>{{ __('meetings.fields.company') }}</span>
        <select name="company_id"><option value="">—</option>@foreach (\App\Models\Company::active()->orderBy('name')->get(['id', 'name']) as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $m->company_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
        <small>{{ __('meetings.fields.company_hint') }}</small>
    </label>
    <div class="field span-2"><span>{{ __('meetings.fields.attendees') }}</span>
        <div class="mt-people">@foreach ($staff as $u)<label class="check"><input type="checkbox" name="attendees[]" value="{{ $u->id }}" @checked(in_array($u->id, $att, true))> {{ $u->first_name }} {{ $u->last_name }}</label>@endforeach</div>
    </div>
    <label class="field span-2"><span>{{ __('meetings.fields.notes') }}</span>
        <textarea name="notes" rows="14" required maxlength="200000" placeholder="{{ __('meetings.fields.notes_ph') }}">{{ old('notes', $m->notes) }}</textarea>
        <small>{{ $claude ? __('meetings.fields.notes_hint_ai') : __('meetings.fields.notes_hint_rules') }}</small>
    </label>
</div>
