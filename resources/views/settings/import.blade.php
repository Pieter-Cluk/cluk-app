<x-layouts.app :title="__('nav.items.import')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('users.kicker')) }}</div>
            <h1>{{ __('nav.items.import') }}</h1>
            <p class="muted">{{ __('import.intro') }}</p>
        </div>
        <a class="btn" href="{{ route('settings.import.template') }}">{{ __('import.template') }}</a>
    </div>
    @include('partials.flash')

    @isset($reports)
        <section class="card">
            <h2>{{ $dryRun ? __('import.preview_title', ['file' => $fileName]) : __('import.done_title', ['file' => $fileName]) }}</h2>
            <p class="muted">{{ $dryRun ? __('import.preview_intro') : __('import.done_intro') }}</p>
            @foreach ($reports as $kind => $r)
                <h3>{{ __('import.kinds.'.$kind) }}</h3>
                <div class="stats">
                    <div class="stat"><b>{{ $r['created'] }}</b><span>{{ __($dryRun ? 'import.will_create' : 'import.created') }}</span></div>
                    <div class="stat"><b>{{ $r['updated'] }}</b><span>{{ __($dryRun ? 'import.will_update' : 'import.updated') }}</span></div>
                    <div class="stat"><b>{{ $r['skipped'] }}</b><span>{{ __('import.skipped') }}</span></div>
                </div>
                @if ($r['messages'])
                    <details @if (count($r['messages']) <= 12) open @endif>
                        <summary class="linkbtn">{{ trans_choice('import.messages', count($r['messages']), ['count' => count($r['messages'])]) }}</summary>
                        <ul class="import-messages">
                            @foreach ($r['messages'] as $m)
                                <li @class(['msg-error' => $m['level'] === 'error'])><span class="muted">{{ __('import.row', ['row' => $m['row']]) }}</span> {{ $m['text'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endforeach
            @if ($dryRun)
                <form method="post" action="{{ route('settings.import.run') }}" class="form-actions">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <a class="btn" href="{{ route('settings.import.index') }}">{{ __('settings.cancel') }}</a>
                    <button class="primary" type="submit">{{ __('import.run') }}</button>
                </form>
            @endif
        </section>
    @endisset

    <div class="split">
        <form class="card" method="post" action="{{ route('settings.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <h2>{{ __('import.upload_title') }}</h2>
            <div class="form-grid form-grid-1">
                <label class="field"><span>{{ __('import.file') }}</span>
                    <input type="file" name="file" accept=".xlsx,.csv" required>
                    <small>{{ __('import.file_hint') }}</small></label>
                <label class="field"><span>{{ __('import.kind') }}</span>
                    <select name="kind">
                        <option value="auto">{{ __('import.kind_auto') }}</option>
                        @foreach (array_keys(\App\Http\Controllers\Settings\ImportController::KINDS) as $k)<option value="{{ $k }}">{{ __('import.kinds.'.$k) }}</option>@endforeach
                    </select></label>
            </div>
            <div class="form-actions"><button class="primary" type="submit">{{ __('import.check') }}</button></div>
        </form>

        <aside class="stack">
            <section class="card">
                <h2>{{ __('import.how_title') }}</h2>
                <ul class="plain-list">
                    <li>{{ __('import.how_1') }}</li>
                    <li>{{ __('import.how_2') }}</li>
                    <li>{{ __('import.how_3') }}</li>
                    <li>{{ __('import.how_4') }}</li>
                </ul>
                <p class="muted small">{{ __('import.later') }}</p>
            </section>
            @if ($viesPending > 0)
                <section class="card">
                    <h2>{{ __('import.vies_title') }}</h2>
                    <p class="muted">{{ trans_choice('import.vies_pending', $viesPending, ['count' => $viesPending]) }}</p>
                    <form method="post" action="{{ route('settings.import.vies') }}">@csrf<button class="btn" type="submit">{{ __('import.vies_check') }}</button></form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
