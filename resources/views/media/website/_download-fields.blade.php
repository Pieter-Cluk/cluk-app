<div><b class="small">{{ __('website.downloads.doc_title') }}</b>
    <div class="ws-langs">@foreach ($locales as $l)<label class="field"><span>{{ strtoupper($l) }}</span><input name="title[{{ $l }}]" value="{{ old('title.'.$l, $d?->title[$l] ?? '') }}" maxlength="160" @required($l === 'en')></label>@endforeach</div></div>
<div><b class="small">{{ __('website.downloads.doc_text') }}</b>
    <div class="ws-langs">@foreach ($locales as $l)<label class="field"><span>{{ strtoupper($l) }}</span><input name="description[{{ $l }}]" value="{{ old('description.'.$l, $d?->description[$l] ?? '') }}" maxlength="400"></label>@endforeach</div></div>
<div class="form-grid">
    <label class="field"><span>{{ __('website.downloads.category') }}</span>
        <select name="category">@foreach (config('website.download_categories') as $cat)<option value="{{ $cat }}" @selected(old('category', $d?->category ?? 'technical') === $cat)>{{ __('site.downloads.cats.'.$cat) }}</option>@endforeach</select></label>
    <label class="field"><span>{{ __('website.downloads.doc_language') }}</span>
        <select name="locale"><option value="">{{ __('site.downloads.any_language') }}</option>@foreach ($locales as $l)<option value="{{ $l }}" @selected(old('locale', $d?->locale) === $l)>{{ __('site.downloads.langs.'.$l) }}</option>@endforeach</select></label>
</div>
<label class="field"><span>{{ __('website.downloads.who_may') }}</span>
    <select name="access">@foreach (\App\Models\Website\Download::ACCESS as $a)<option value="{{ $a }}" @selected(old('access', $d?->access ?? 'email') === $a)>{{ __('website.downloads.access.'.$a) }}</option>@endforeach</select>
    <small>{{ __('website.downloads.who_may_hint') }}</small></label>
@if ($claude)<label class="check"><input type="checkbox" name="translate" value="1" checked> {{ __('website.translate') }}</label>@endif
