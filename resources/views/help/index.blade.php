<x-layouts.app :title="__('help.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('me.kicker')) }} / {{ mb_strtoupper(__('nav.items.help')) }}</div>
            <h1>{{ __('help.title') }}</h1>
            <p class="muted">{{ __('help.intro') }}</p>
        </div>
        @if ($canEdit)
            <div class="inline-form">
                <a class="btn" href="{{ route('help.manage') }}">{{ __('help.manage') }}@if ($openQuestions) <span class="pill pill-warn">{{ $openQuestions }}</span>@endif</a>
                <a class="primary" href="{{ route('help.create') }}">{{ __('help.new_article') }}</a>
            </div>
        @endif
    </div>
    @include('partials.flash')

    <div class="help-layout">
        <div>
            <form method="get" action="{{ route('help.index') }}" class="help-search" role="search">
                <x-icon name="search"/>
                <input type="search" name="q" id="help-q" value="{{ $q }}" placeholder="{{ __('help.search_placeholder') }}" aria-label="{{ __('help.search_placeholder') }}" autocomplete="off" autofocus>
                @if ($topic)<input type="hidden" name="topic" value="{{ $topic }}">@endif
                <button class="primary" type="submit">{{ __('help.search') }}</button>
            </form>
            <nav class="chips mt" aria-label="{{ __('help.topics_label') }}">
                <a @class(['chip', 'chip-on' => ! $topic]) href="{{ route('help.index', array_filter(['q' => $q ?: null])) }}">{{ __('help.all_topics') }}</a>
                @foreach ($topics as $t)<a @class(['chip', 'chip-on' => $topic === $t]) href="{{ route('help.index', array_filter(['topic' => $t, 'q' => $q ?: null])) }}">{{ __('help.topics.'.$t) }}</a>@endforeach
            </nav>

            <p class="muted mt" id="help-count" aria-live="polite">@if ($q !== ''){{ trans_choice('help.found', $articles->count(), ['n' => $articles->count(), 'q' => $q]) }}@endif</p>
            <div class="help-list" id="help-list">
                @foreach ($articles as $a)
                    <a class="help-item" href="{{ route('help.show', $a) }}" data-hay="{{ $a->haystack() }}" data-title="{{ \App\Models\Help\Article::fold(implode(' ', array_values($a->title ?? [])).' '.$a->keywords) }}" data-topic="{{ $a->topic }}">
                        <span class="pill">{{ __('help.topics.'.$a->topic) }}</span>
                        <b>{{ $a->t('title') }}</b>
                        @if ($a->t('intro'))<span class="muted">{{ $a->t('intro') }}</span>@endif
                        <span class="help-more">{{ __('help.read') }} →</span>
                    </a>
                @endforeach
            </div>
            <div class="card help-none" id="help-none" @if ($articles->isNotEmpty()) hidden @endif>
                <b>{{ __('help.nothing', ['q' => $q]) }}</b>
                <p class="muted">{{ __('help.nothing_hint') }}</p>
            </div>
        </div>

        <aside class="help-side">
            <section class="card" id="ask">
                <h2>{{ __('help.stuck') }}</h2>
                <p class="muted">{{ __('help.stuck_hint') }}</p>
                <form method="post" action="{{ route('help.ask') }}">@csrf
                    <input type="hidden" name="page" value="{{ url()->previous() !== url()->current() ? url()->previous() : '' }}">
                    <label class="field"><span class="sr-only">{{ __('help.question') }}</span><textarea name="question" id="help-ask" rows="4" required minlength="5" maxlength="2000" placeholder="{{ __('help.ask_placeholder') }}">{{ old('question') }}</textarea></label>
                    @error('question')<p class="error">{{ $message }}</p>@enderror
                    <button class="primary mt" type="submit">{{ __('help.ask') }}</button>
                </form>
                @if ($mine->isNotEmpty())
                    <h3 class="mt">{{ __('help.my_questions') }}</h3>
                    <ul class="help-qs">
                        @foreach ($mine as $m)
                            <li><span>{{ \Illuminate\Support\Str::limit($m->question, 90) }}</span>
                                @if ($m->status === 'answered')<details><summary>{{ __('help.see_answer') }}</summary><p>{!! nl2br(e($m->answer)) !!}</p>@if ($m->article)<a href="{{ route('help.show', $m->article) }}">{{ __('help.mail.read_article') }} →</a>@endif</details>
                                @else<small class="muted">{{ __('help.statuses.'.$m->status) }} · {{ $m->created_at->format('d/m H:i') }}</small>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card">
                <h2>{{ __('help.downloads') }}</h2>
                <ul class="help-files">
                    @foreach ($files as $f)
                        <li><a href="{{ route('help.file', $f) }}"><span class="ext">{{ $f->extension() }}</span><span><b>{{ $f->title }}</b>@if ($f->note)<small class="muted">{{ $f->note }}</small>@endif</span><span aria-hidden="true">↓</span></a></li>
                    @endforeach
                    @if (\App\Support\Navigation::allows(auth()->user(), 'import'))
                        <li><a href="{{ route('settings.import.template') }}"><span class="ext">XLSX</span><span><b>{{ __('help.import_templates') }}</b><small class="muted">{{ __('help.import_templates_hint') }}</small></span><span aria-hidden="true">↓</span></a></li>
                    @endif
                    @if (auth()->user()->canUseModule('warehouse'))
                        <li class="help-qr"><a href="{{ route('w.home') }}">{!! \App\Support\QrSvg::make(route('w.home'), true) !!}<span><b>{{ __('help.phone_app') }}</b><small class="muted">{{ __('help.phone_app_hint') }}</small></span></a></li>
                    @endif
                </ul>
                @if ($files->isEmpty() && $canEdit)<p class="muted">{{ __('help.no_files_admin') }}</p>@endif
            </section>

            @if ($shortcuts)
                <section class="card">
                    <h2>{{ __('help.shortcuts') }}</h2>
                    <table class="table table-tight">
                        <tbody>@foreach ($shortcuts as $k)<tr><td>{{ $k['what'] }}</td><td class="num">@foreach (explode(' ', $k['keys']) as $key)<kbd>{{ $key }}</kbd> @endforeach</td></tr>@endforeach</tbody>
                    </table>
                    <p class="muted">{{ __('help.shortcuts_hint') }}</p>
                </section>
            @endif
        </aside>
    </div>

    <script>
        (function () {
            const input = document.getElementById('help-q'), list = document.getElementById('help-list'), none = document.getElementById('help-none'), count = document.getElementById('help-count');
            const items = Array.from(list.children), order = items.slice();
            const stop = new Set(@js(\App\Services\Help\Search::STOP));
            const fold = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            const found = @js(__('help.found_js'));
            function run() {
                const words = [...new Set(fold(input.value).split(/[^a-z0-9]+/).filter(w => w.length >= 2 && !stop.has(w)))];
                if (!words.length) { order.forEach(el => { el.hidden = false; list.appendChild(el); }); none.hidden = order.length > 0; count.textContent = ''; return; }
                const scored = order.map(el => {
                    const hay = el.dataset.hay, title = el.dataset.title; let s = 0;
                    words.forEach(w => {
                        if (new RegExp('\\b' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).test(hay)) s += 2 + (title.includes(w) ? 3 : 0);
                        else if (w.length >= 5 && hay.includes(w.slice(0, 4))) s += 1;
                    });
                    return [el, s];
                });
                scored.sort((a, b) => b[1] - a[1]).forEach(([el, s]) => { el.hidden = s === 0; list.appendChild(el); });
                const n = scored.filter(x => x[1] > 0).length;
                none.hidden = n > 0;
                none.querySelector('b').textContent = @js(__('help.nothing', ['q' => '__Q__'])).replace('__Q__', input.value);
                count.textContent = found.replace(':n', n);
                const ask = document.getElementById('help-ask'); if (ask && !n && !ask.dataset.touched) ask.value = input.value;
            }
            let t; input.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 120); });
            document.getElementById('help-ask')?.addEventListener('input', e => { e.target.dataset.touched = '1'; });
        })();
    </script>
</x-layouts.app>
