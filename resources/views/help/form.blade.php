<x-layouts.app :title="$a->exists ? __('help.edit') : __('help.new_article')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('help.manage') }}">{{ mb_strtoupper(__('help.manage')) }}</a></div>
            <h1>{{ $a->exists ? $a->t('title') : __('help.new_article') }}</h1>
            <p class="muted">{{ __('help.form_intro') }}</p>
        </div>
    </div>
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ $a->exists ? route('help.update', $a) : route('help.store') }}" class="me-grid">@csrf @if ($a->exists) @method('put') @endif
        @if ($fromQuestion)<input type="hidden" name="from_question" value="{{ $fromQuestion }}">@endif
        <section class="card">
            <div class="inline-form">
                <label class="field"><span>{{ __('help.fields.topic') }}</span><select name="topic">@foreach (\App\Models\Help\Article::TOPICS as $t)<option value="{{ $t }}" @selected(old('topic', $a->topic) === $t)>{{ __('help.topics.'.$t) }}</option>@endforeach</select></label>
                <label class="field"><span>{{ __('help.fields.link') }}</span><input name="link_path" value="{{ old('link_path', $a->link_path) }}" placeholder="/warehouse/labels" maxlength="255"></label>
            </div>
            <label class="field"><span>{{ __('help.fields.keywords') }}</span><input name="keywords" value="{{ old('keywords', $a->keywords) }}" maxlength="500" placeholder="{{ __('help.fields.keywords_hint') }}"></label>
            <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $a->active ?? true))><span>{{ __('help.fields.active') }}</span></label>
        </section>
        @foreach (config('cluk.locales') as $code => $lang)
            <section class="card">
                <h2>{{ $lang }} @if ($code === 'en')<small class="muted">· {{ __('help.required') }}</small>@endif</h2>
                <label class="field"><span>{{ __('help.fields.title') }}</span><input name="title[{{ $code }}]" value="{{ old('title.'.$code, $a->title[$code] ?? '') }}" maxlength="160" @required($code === 'en')></label>
                <label class="field"><span>{{ __('help.fields.intro') }}</span><input name="intro[{{ $code }}]" value="{{ old('intro.'.$code, $a->intro[$code] ?? '') }}" maxlength="500"></label>
                <label class="field"><span>{{ __('help.fields.body') }} <small class="muted">{{ __('help.fields.body_hint') }}</small></span><textarea name="body[{{ $code }}]" rows="{{ $code === 'en' ? 10 : 6 }}" maxlength="10000">{{ old('body.'.$code, $a->body[$code] ?? '') }}</textarea></label>
            </section>
        @endforeach
        <div class="me-save">
            @if ($canTranslate)<label class="check"><input type="checkbox" name="translate" value="1" checked><span>{{ __('help.fields.translate') }}</span></label>@endif
            <button class="primary" type="submit">{{ __('help.save') }}</button>
        </div>
    </form>
    @if ($a->exists)
        <form method="post" action="{{ route('help.destroy', $a) }}" class="mt" onsubmit="return confirm(@js(__('help.remove_confirm')))">@csrf @method('delete')<button class="linkbtn" type="submit">{{ __('help.delete_article') }}</button></form>
    @endif
</x-layouts.app>
