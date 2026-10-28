@php($loc = app()->getLocale())
<x-layouts.portal :title="__('guests.title')" tab="guests">
    <div class="pagehead">
        <div>
            <h1>{{ __('guests.title') }}</h1>
            <p class="muted">{{ __('guests.intro') }}</p>
        </div>
    </div>

    <div class="split">
        <section class="card card-accent">
            <h2>{{ __('guests.add') }}</h2>
            <form method="post" action="{{ route('afrit6.portal.guests.store') }}" class="form-grid form-grid-1">
                @csrf
                <div class="form-grid">
                    <label class="field"><span>{{ __('guests.date') }}</span><input type="date" name="date" required min="{{ today()->toDateString() }}" max="{{ today()->addDays(90)->toDateString() }}" value="{{ old('date', today()->toDateString()) }}"></label>
                    <label class="field"><span>{{ __('guests.time') }}</span><input type="time" name="time" step="900" value="{{ old('time') }}"></label>
                </div>
                <label class="field"><span>{{ __('guests.names') }}</span><textarea name="names" rows="3" required maxlength="1000" placeholder="{{ __('guests.names_hint') }}">{{ old('names') }}</textarea>@error('names')<small class="txt-danger">{{ $message }}</small>@enderror</label>
                <label class="field"><span>{{ __('guests.company') }}</span><input name="company" maxlength="120" value="{{ old('company') }}"></label>
                <input type="hidden" name="on_screen" value="0">
                <label class="check"><input type="checkbox" name="on_screen" value="1" @checked(old('on_screen', '1') === '1')><span>{{ __('guests.on_screen') }}</span></label>
                <p class="muted small">{{ __('guests.on_screen_hint') }}</p>
                <label class="field"><span>{{ __('guests.note') }}</span><input name="note" maxlength="300" value="{{ old('note') }}"><small class="muted">{{ __('guests.note_hint') }}</small></label>
                <button class="primary" type="submit">{{ __('guests.save') }}</button>
            </form>
        </section>

        <section class="card">
            <h2>{{ __('guests.upcoming') }}</h2>
            @forelse ($upcoming as $g)
                <div class="p-line">
                    <span><b>{{ $g->date->isToday() ? __('workspace.room.today') : $g->date->locale($loc)->translatedFormat('D j M') }}@if ($g->time) · {{ $g->time }}@endif · {{ $g->name }}</b>
                        <small class="muted">@if ($g->company){{ $g->company }} · @endif{{ $g->on_screen ? __('guests.shown') : __('guests.hidden') }}@if ($g->note) · {{ $g->note }}@endif</small></span>
                    <form method="post" action="{{ route('afrit6.portal.guests.cancel', $g) }}" onsubmit="return confirm(@js(__('guests.cancel_confirm')))">@csrf<button class="linkbtn" type="submit">{{ __('guests.cancel') }}</button></form>
                </div>
            @empty
                <p class="muted">{{ __('guests.none') }}</p>
            @endforelse
        </section>
    </div>
</x-layouts.portal>
