@php($loc = app()->getLocale())
<x-layouts.app :title="__('entrance.tab')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.media')) }} / {{ mb_strtoupper(__('nav.items.messages')) }}</div>
            <h1>{{ __('entrance.title') }}</h1>
            <p class="muted">{{ __('entrance.intro') }}</p>
        </div>
        @if ($screen)<a class="btn" href="{{ route('wall.show', ['view' => 'entrance']) }}" target="_blank" rel="noopener">{{ __('entrance.open') }}</a>@endif
    </div>
    @include('partials.flash')
    <nav class="tabs">
        @foreach (\App\Models\Media\Message::KINDS as $k)<a class="tab" href="{{ route('messages.index', ['kind' => $k]) }}">{{ __('messages.kinds.'.$k) }}</a>@endforeach
        <a class="tab tab-on" href="{{ route('messages.entrance') }}" aria-current="page">{{ __('entrance.tab') }}</a>
    </nav>

    <div class="split mt">
        <section class="card">
            <h2>{{ __('entrance.coming') }}</h2>
            @php($days = $entries->groupBy(fn ($e) => $e->date->toDateString()))
            @forelse ($days as $day => $list)
                @php($d = \Illuminate\Support\Carbon::parse($day))
                <h3 class="h3">{{ $d->isToday() ? __('workspace.room.today') : $d->locale($loc)->translatedFormat('l j F') }}</h3>
                @foreach ($list as $e)
                    <div class="p-line">
                        <span>
                            <b>@if ($e->time){{ $e->time }} · @endif{{ $e->name }}</b>@if ($e->kind === 'guest' && $e->company) <span class="muted">· {{ $e->company }}</span>@endif
                            <small class="muted">
                                @if ($e->kind === 'notice'){{ __('entrance.kinds.notice') }}
                                @else{{ __('entrance.for', ['host' => $e->member ? ($e->member->companyName() ?: $e->member->name) : 'CLUK']) }} · {{ $e->on_screen ? __('guests.shown') : __('guests.hidden') }}@endif
                                · {{ $e->member ? __('entrance.by_member') : ($e->user?->name ?? '—') }}@if ($e->note) · {{ $e->note }}@endif
                            </small>
                        </span>
                        @if ($canEdit)
                            <form method="post" action="{{ route('messages.entrance.cancel', $e) }}" onsubmit="return confirm(@js(__('entrance.remove_confirm')))">@csrf<button class="linkbtn" type="submit">{{ __('guests.cancel') }}</button></form>
                        @endif
                    </div>
                @endforeach
            @empty
                <p class="muted">{{ __('entrance.none') }}</p>
            @endforelse
        </section>

        @if ($canEdit)
            <aside class="stack">
                <section class="card card-accent">
                    <h2>{{ __('entrance.add_guest') }}</h2>
                    <form method="post" action="{{ route('messages.entrance.store') }}" class="form-grid form-grid-1">
                        @csrf <input type="hidden" name="kind" value="guest">
                        <div class="form-grid">
                            <label class="field"><span>{{ __('guests.date') }}</span><input type="date" name="date" required min="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}"></label>
                            <label class="field"><span>{{ __('guests.time') }}</span><input type="time" name="time" step="900" value="{{ old('time') }}"></label>
                        </div>
                        <label class="field"><span>{{ __('guests.names') }}</span><textarea name="names" rows="3" maxlength="1000" placeholder="{{ __('guests.names_hint') }}">{{ old('names') }}</textarea>@error('names')<small class="txt-danger">{{ $message }}</small>@enderror</label>
                        <label class="field"><span>{{ __('guests.company') }}</span><input name="company" maxlength="120" value="{{ old('company') }}"></label>
                        <input type="hidden" name="on_screen" value="0">
                        <label class="check"><input type="checkbox" name="on_screen" value="1" checked><span>{{ __('guests.on_screen') }}</span></label>
                        <label class="field"><span>{{ __('entrance.note') }}</span><input name="note" maxlength="300"><small class="muted">{{ __('guests.note_hint') }}</small></label>
                        <button class="primary" type="submit">{{ __('guests.save') }}</button>
                    </form>
                </section>
                <section class="card">
                    <h2>{{ __('entrance.add_notice') }}</h2>
                    <p class="muted small">{{ __('entrance.notice_hint') }}</p>
                    <form method="post" action="{{ route('messages.entrance.store') }}" class="form-grid form-grid-1">
                        @csrf <input type="hidden" name="kind" value="notice">
                        <div class="form-grid">
                            <label class="field"><span>{{ __('guests.date') }}</span><input type="date" name="date" required min="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}"></label>
                            <label class="field"><span>{{ __('entrance.time_opt') }}</span><input type="time" name="time" step="900"></label>
                        </div>
                        <label class="field"><span>{{ __('entrance.text') }}</span><input name="text" maxlength="120" placeholder="{{ __('entrance.text_hint') }}">@error('text')<small class="txt-danger">{{ $message }}</small>@enderror</label>
                        <button class="btn" type="submit">{{ __('guests.save') }}</button>
                    </form>
                </section>
            </aside>
        @endif
    </div>
</x-layouts.app>
