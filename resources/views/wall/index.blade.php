<x-layouts.app :title="__('wall.admin.title')">
    <div class="pagehead"><div><div class="kicker">CLUK / {{ mb_strtoupper(__('wall.admin.title')) }}</div><h1>{{ __('wall.admin.title') }}</h1><p class="muted">{{ __('wall.admin.intro') }}</p></div></div>
    @include('partials.flash')
    <div class="split-2">
        @foreach ($screens as $s)
            @php($canManage = $s->view === 'entrance' ? $manageEntrance : $manageAll)
            <section class="card wall-card">
                <div class="card-head"><h2>{{ __('wall.admin.screen_'.$s->view) }}</h2><span @class(['pill', 'pill-ok' => $s->isOnline(), 'pill-off' => ! $s->isOnline()])>{{ $s->isOnline() ? __('wall.admin.online') : __('wall.admin.offline') }}</span></div>
                <p class="muted">{{ __('wall.admin.desc_'.$s->view) }}</p>
                <div class="inline-form">
                    <a class="primary" href="{{ route('wall.show', ['view' => $s->view]) }}" target="_blank" rel="noopener">{{ __('wall.admin.open') }}</a>
                    @if ($canManage)
                        <button class="btn js-copy" type="button" data-link="{{ $s->link() }}" data-done="{{ __('wall.admin.copied') }}">{{ __('wall.admin.copy') }}</button>
                        <form method="post" action="{{ route('wall.renew', $s) }}" onsubmit="return confirm(@js(__('wall.admin.renew_confirm')))">@csrf<button class="btn" type="submit">{{ __('wall.admin.renew') }}</button></form>
                    @endif
                </div>
                <dl class="facts">
                    @if ($canManage)<dt>{{ __('wall.admin.link') }}</dt><dd><code class="small">{{ \Illuminate\Support\Str::limit($s->link(), 60) }}</code></dd>@endif
                    <dt>{{ __('wall.admin.where') }}</dt><dd>{{ $s->location ?: '—' }}</dd>
                    <dt>{{ __('wall.admin.seen') }}</dt><dd>{{ $s->last_seen_at ? $s->last_seen_at->locale(app()->getLocale())->diffForHumans() : __('wall.admin.never') }}</dd>
                </dl>
                @if ($s->view === 'entrance')
                    <p class="small">{{ trans_choice('wall.admin.slides_n', $entrance['slides'], ['n' => $entrance['slides']]) }} · {{ trans_choice('wall.admin.guests_today', $entrance['guests'], ['n' => $entrance['guests']]) }}<br>
                        @if (Route::has('website.index'))<a href="{{ route('website.index', ['tab' => 'screen']) }}">{{ __('wall.admin.slides') }} →</a>@else{{ __('wall.admin.slides') }}@endif</p>
                    <details class="mt-xs"><summary class="linkbtn">{{ __('wall.admin.full_title') }}</summary>
                        <ol class="plain-list small">@foreach ([1, 2, 3, 4] as $n)<li>{{ __('wall.admin.full_'.$n) }}</li>@endforeach</ol>
                    </details>
                @endif
                @if ($canManage)
                    <form method="post" action="{{ route('wall.update', $s) }}" class="inline-form">
                        @csrf @method('PUT')
                        <input name="location" maxlength="120" value="{{ $s->location }}" aria-label="{{ __('wall.admin.where') }}">
                        <select name="locale" aria-label="{{ __('quotes.language') }}">@foreach (config('cluk.locales') as $c => $n)<option value="{{ $c }}" @selected($s->locale === $c)>{{ $n }}</option>@endforeach</select>
                        <button class="btn" type="submit">{{ __('wall.admin.save') }}</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
    <section class="card">
        <h2>{{ __('wall.admin.setup') }}</h2>
        <ol class="plain-list">
            <li>{{ __('wall.admin.step1') }}</li>
            <li>{{ __('wall.admin.step2') }}</li>
            <li>{{ __('wall.admin.step3') }}</li>
        </ol>
        <p class="muted small">{{ __('wall.admin.leak') }}</p>
    </section>
    <script>document.querySelectorAll('.js-copy').forEach(b => b.addEventListener('click', async () => { try { await navigator.clipboard.writeText(b.dataset.link); b.textContent = b.dataset.done; } catch (e) { prompt('', b.dataset.link); } }));</script>
</x-layouts.app>
