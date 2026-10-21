<x-layouts.app :title="__('nav.items.documents')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.settings')) }} / {{ mb_strtoupper(__('nav.items.documents')) }}</div>
            <h1>{{ __('nav.items.documents') }}</h1>
            <p class="muted">{{ __('documents.intro') }} <a href="{{ route('settings.company.edit') }}">{{ __('nav.items.company') }}</a>.</p>
        </div>
    </div>
    @include('partials.flash')
    <nav class="chips">
        <a @class(['chip', 'chip-on' => $family === 'all']) href="{{ route('settings.documents.index', ['doc' => $type]) }}">{{ __('documents.all', ['n' => count($all)]) }}</a>
        @foreach (config('documents.families') as $f => $docs)<a @class(['chip', 'chip-on' => $family === $f]) href="{{ route('settings.documents.index', ['family' => $f, 'doc' => array_key_first($docs)]) }}">{{ __('documents.families_short.'.$f) }} ({{ count($docs) }})</a>@endforeach
    </nav>

    <div class="split mt">
        <section class="card">
            <div class="dl-list">
                @foreach (config('documents.families') as $f => $docs)
                    @continue($family !== 'all' && $family !== $f)
                    <div class="dl-group">{{ mb_strtoupper(__('documents.families.'.$f)) }}</div>
                    @foreach ($docs as $t => $d)
                        <a href="{{ route('settings.documents.index', array_filter(['family' => $family !== 'all' ? $family : null, 'doc' => $t])) }}" @class(['dl-item', 'dl-on' => $t === $type])>
                            <span>{{ __('documents.types.'.$t) }}</span><small>{{ $t }}-YYYY-#### · {{ $d['prices'] ? __('documents.prices') : __('documents.no_prices') }}</small>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </section>

        <aside class="stack">
            <section class="card">
                <div class="kicker">{{ mb_strtoupper(__('documents.families.'.$doc['family'])) }}</div>
                <h2>{{ __('documents.types.'.$type) }}</h2>
                <p>{{ __('documents.meaning.'.$type) }}</p>
                <dl class="facts">
                    <dt>{{ __('documents.numbering') }}</dt><dd><code>{{ $type }}-{{ now()->year }}-0001</code></dd>
                    <dt>{{ __('documents.prices_label') }}</dt><dd>{{ $doc['prices'] ? __('documents.yes_prices') : __('documents.never_prices') }}</dd>
                    <dt>{{ __('documents.signatures') }}</dt><dd>{{ $doc['signs'] ? __('documents.signs_n', ['n' => $doc['signs']]) : __('documents.no_signs') }}</dd>
                    <dt>{{ __('documents.languages') }}</dt><dd>{{ $doc['customer'] ? 'EN · NL · FR · ES · '.__('documents.customer_lang') : __('documents.your_lang') }}</dd>
                    <dt>{{ __('documents.vat') }}</dt><dd>{{ __('documents.vat_rule.'.(in_array($doc['family'], ['venue', 'afrit'], true) ? 'place' : ($doc['customer'] ? 'customer' : 'supplier'))) }}</dd>
                </dl>
                @if ($example)<a class="btn" href="{{ $example }}" target="_blank" rel="noopener">{{ __('documents.open_example') }}</a>@else<p class="muted small">{{ __('documents.no_example') }}</p>@endif
            </section>

            @if ($doc['customer'])
                <section class="card">
                    <h2>{{ __('documents.note_heading') }}</h2>
                    <p class="muted small">{{ __('documents.note_hint', ['doc' => __('documents.types.'.$type)]) }}</p>
                    <form method="post" action="{{ route('settings.documents.note', $type) }}" class="stack-sm">@csrf @method('PUT')
                        <div class="dl-locs">
                            @foreach (config('cluk.locales') as $c => $name)
                                <label class="field"><span>{{ $name }}</span><textarea name="text[{{ $c }}]" rows="3" maxlength="600">{{ old('text.'.$c, $note['text'][$c] ?? '') }}</textarea></label>
                            @endforeach
                        </div>
                        <input type="hidden" name="on" value="0">
                        <label class="check"><input type="checkbox" name="on" value="1" @checked(old('on', $note['on'] ?? false))> {{ __('documents.note_on') }}</label>
                        <div><button class="primary" type="submit">{{ __('documents.save') }}</button></div>
                    </form>
                </section>
            @endif

            <section class="card">
                <h2>{{ __('documents.season_heading') }}</h2>
                <p class="muted small">{{ __('documents.season_hint') }}</p>
                @php($live = \App\Models\DocumentSetting::season(app()->getLocale()))
                <p><span @class(['pill', 'pill-ok' => $live, 'pill-off' => ! $live])>{{ $live ? __('documents.season_live') : __('documents.season_not_live') }}</span></p>
                <form method="post" action="{{ route('settings.documents.season') }}" class="stack-sm">@csrf @method('PUT')
                    <div class="dl-locs">
                        @foreach (config('cluk.locales') as $c => $name)
                            <label class="field"><span>{{ $name }}</span><input name="text[{{ $c }}]" maxlength="300" value="{{ old('text.'.$c, $season['text'][$c] ?? '') }}"></label>
                        @endforeach
                    </div>
                    <div class="inline-form">
                        <label class="field"><span>{{ __('documents.from') }}</span><input type="date" name="from" value="{{ old('from', $season['from'] ?? '') }}"></label>
                        <label class="field"><span>{{ __('documents.to') }}</span><input type="date" name="to" value="{{ old('to', $season['to'] ?? '') }}"></label>
                    </div>
                    <input type="hidden" name="on" value="0">
                    <label class="check"><input type="checkbox" name="on" value="1" @checked(old('on', $season['on'] ?? false))> {{ __('documents.season_on') }}</label>
                    <div><button class="primary" type="submit">{{ __('documents.save') }}</button></div>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.app>
