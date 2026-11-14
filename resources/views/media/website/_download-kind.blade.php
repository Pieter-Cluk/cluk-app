{{-- File or link (a web tool such as the dB meter). The radio decides which field counts. --}}
@php($kind = old('kind', $d?->isLink() ? 'link' : 'file'))
@php($uid = 'dk'.($d?->id ?? 'new'))
<fieldset class="dl-kind" id="{{ $uid }}">
    <div class="inline-form">
        <label class="check"><input type="radio" name="kind" value="file" @checked($kind === 'file')> {{ __('website.downloads.kind_file') }}</label>
        <label class="check"><input type="radio" name="kind" value="link" @checked($kind === 'link')> {{ __('website.downloads.kind_link') }}</label>
    </div>
    <label class="field" data-kind="file" @if ($kind === 'link') hidden @endif><span>{{ $d ? __('website.downloads.new_file') : __('website.downloads.file') }}</span><input type="file" name="file">
        <small>{{ $d && ! $d->isLink() ? __('website.downloads.same_link') : __('website.downloads.file_hint') }}</small></label>
    <label class="field" data-kind="link" @if ($kind !== 'link') hidden @endif><span>{{ __('website.downloads.url') }}</span><input type="url" name="url" maxlength="500" placeholder="https://" value="{{ old('url', $d?->url) }}">
        <small>{{ __('website.downloads.url_hint') }}</small></label>
    @error('url')<p class="error small">{{ $message }}</p>@enderror
    @error('file')<p class="error small">{{ $message }}</p>@enderror
</fieldset>
<script>
(function () {
    var box = document.getElementById(@js($uid));
    box.querySelectorAll('input[name=kind]').forEach(function (r) {
        r.addEventListener('change', function () {
            box.querySelectorAll('[data-kind]').forEach(function (f) { f.hidden = f.dataset.kind !== r.value; });
        });
    });
})();
</script>
