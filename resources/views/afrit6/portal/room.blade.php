@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
@php($loc = app()->getLocale())
@php($me = auth('member')->id())
<x-layouts.portal :title="__('workspace.room.title')" tab="room">
    <div class="pagehead">
        <div>
            <h1>{{ __('workspace.room.title') }}</h1>
            <p class="muted">{{ __('workspace.room.intro') }}</p>
        </div>
        <div class="slot-count"><b>{{ max(0, $allowance - $used) }}</b> / {{ $allowance }}<small>{{ __('workspace.room.free_left', ['left' => max(0, $allowance - $used), 'total' => $allowance]) }}</small></div>
    </div>

    <nav class="chips" aria-label="{{ __('workspace.room.title') }}">
        @foreach ($days as $d)
            <a href="{{ route('afrit6.portal.room', ['day' => $d->toDateString()]) }}" @class(['chip', 'chip-on' => $d->isSameDay($day)])>{{ $d->locale($loc)->translatedFormat('D j M') }}@if ($d->isToday()) · {{ __('workspace.room.today') }}@endif</a>
        @endforeach
    </nav>

    <div class="split">
        <section class="card">
            <h2>{{ $day->locale($loc)->translatedFormat('l j F') }}</h2>
            <div class="slots" role="list">
                @foreach ($plan as $i => $s)
                    @php($state = $s['state'] === 'booked' && $s['booking']->member_id === $me ? 'mine' : $s['state'])
                    <button type="button" role="listitem" @class(['slot', 's-'.$state]) data-slot="{{ $i }}" @disabled($state !== 'free') aria-label="{{ $s['time'] }} {{ __('workspace.room.states.'.$state) }}">
                        <b>{{ $s['time'] }}</b><small>{{ __('workspace.room.states.'.$state) }}</small>
                    </button>
                @endforeach
            </div>
            <div class="legend">
                @foreach (['free', 'mine', 'booked', 'reset', 'past'] as $st)<span><i class="lg s-{{ $st }}"></i>{{ __('workspace.room.states.'.$st) }}</span>@endforeach
            </div>
        </section>

        <aside class="stack">
            <section class="card card-accent">
                <h2>{{ __('workspace.room.book') }}</h2>
                <form method="post" action="{{ route('afrit6.portal.room.book') }}" class="form-grid form-grid-1" id="book-form">
                    @csrf
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <fieldset class="field radios">
                        <span>{{ __('workspace.room.setup') }}</span>
                        @foreach (array_keys(config('afrit6.room.setups')) as $setup)
                            <label class="check"><input type="radio" name="setup" value="{{ $setup }}" @checked(old('setup', 'meeting') === $setup)><span>{{ __('workspace.room.setups.'.$setup) }}</span></label>
                        @endforeach
                    </fieldset>
                    <label class="field"><span>{{ __('workspace.room.start') }}</span>
                        <select name="start" id="book-start" required>
                            <option value="">{{ __('workspace.room.pick_start') }}</option>
                            @foreach ($plan as $i => $s)
                                @if ($s['state'] === 'free')<option value="{{ $i }}" @selected((string) old('start') === (string) $i)>{{ $s['time'] }}</option>@endif
                            @endforeach
                        </select>
                    </label>
                    <label class="field"><span>{{ __('workspace.room.duration') }}</span>
                        <select name="slots">
                            @foreach ([1, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20] as $n)
                                <option value="{{ $n }}" @selected((int) old('slots', 2) === $n)>{{ $n === 1 ? '30 min' : rtrim(rtrim(number_format($n / 2, 1, ',', ''), '0'), ',').' h' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field"><span>{{ __('workspace.room.note') }}</span><input name="note" maxlength="255" value="{{ old('note') }}"></label>
                    <p class="muted small">{{ __('workspace.room.classroom_note') }}</p>
                    <p class="muted small">{{ __('workspace.room.price_note', ['amount' => $eur((int) config('afrit6.prices.room_half_hour'))]) }}</p>
                    <button class="primary" type="submit">{{ __('workspace.room.book') }}</button>
                </form>
            </section>

            <section class="card">
                <h2>{{ __('workspace.room.your_bookings') }}</h2>
                @forelse ($mine as $b)
                    <div class="p-line">
                        <span><b>{{ $b->date->locale($loc)->translatedFormat('D j M') }} · {{ $b->timeRange() }}</b><small class="muted">{{ __('workspace.room.setups.'.$b->setup) }}@if ($b->note) · {{ $b->note }}@endif</small></span>
                        @unless ($b->hasStarted())
                            <form method="post" action="{{ route('afrit6.portal.room.cancel', $b) }}" onsubmit="return confirm(@js(__('workspace.room.cancel_confirm')))">@csrf<button class="linkbtn" type="submit">{{ __('workspace.room.cancel') }}</button></form>
                        @endunless
                    </div>
                @empty
                    <p class="muted">{{ __('workspace.room.no_bookings') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
    <script>
        document.querySelectorAll('.slot.s-free').forEach(function (b) {
            b.addEventListener('click', function () {
                var sel = document.getElementById('book-start');
                sel.value = b.dataset.slot;
                document.querySelectorAll('.slot').forEach(function (x) { x.classList.toggle('s-pick', x === b); });
                sel.focus();
            });
        });
    </script>
</x-layouts.portal>
