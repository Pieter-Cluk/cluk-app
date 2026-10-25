{{-- Media › Website › Downloads: documents on the website's downloads page. --}}
@php($names = ['en' => 'English', 'nl' => 'Nederlands', 'fr' => 'Français', 'es' => 'Español'])
<section class="card mt" id="downloads">
    <div class="card-head">
        <h2>{{ __('website.downloads.title') }}</h2>
        <a class="btn btn-sm" href="{{ route('website.downloads.log') }}">{{ __('website.downloads.who_all') }}</a>
    </div>
    <p class="muted small">{{ __('website.downloads.hint', ['url' => route('site.downloads')]) }}</p>
    @forelse ($docs as $i => $d)
        <article @class(['ws-slide', 'ws-off' => ! $d->active]) id="download-{{ $d->id }}">
            <div class="ws-dl-ext" aria-hidden="true">{{ $d->type() }}</div>
            <div class="ws-info">
                <b>{{ $d->label(null, 'title') }}</b>
                <small class="muted">{{ $d->original_name }} · {{ $d->sizeLabel() }} · {{ __('website.downloads.access.'.$d->access) }}@unless ($d->active) · {{ __('website.slide.off') }}@endunless</small>
                <div class="small"><a href="{{ route('website.downloads.log', ['doc' => $d->id]) }}">{{ trans_choice('website.downloads.count', $d->downloads, ['n' => $d->downloads]) }}</a> · <a href="{{ route('site.downloads.file', $d->id) }}" target="_blank" rel="noopener">{{ __('website.downloads.link') }}</a></div>
                @if ($canEdit)
                    <div class="ws-actions">
                        <form method="post" action="{{ route('website.downloads.move', [$d, 'up']) }}">@csrf<button class="btn btn-sm" type="submit" @disabled($i === 0) aria-label="{{ __('website.up') }}">↑</button></form>
                        <form method="post" action="{{ route('website.downloads.move', [$d, 'down']) }}">@csrf<button class="btn btn-sm" type="submit" @disabled($loop->last) aria-label="{{ __('website.down') }}">↓</button></form>
                        <form method="post" action="{{ route('website.downloads.destroy', $d) }}" onsubmit="return confirm(@js(__('website.downloads.remove_confirm')))">@csrf @method('DELETE')<button class="btn btn-sm" type="submit">{{ __('website.remove') }}</button></form>
                    </div>
                    <details class="mt-xs">
                        <summary class="linkbtn">{{ __('website.change') }}</summary>
                        <form method="post" action="{{ route('website.downloads.update', $d) }}" enctype="multipart/form-data" class="stack-sm mt">@csrf
                            @include('media.website._download-fields', ['d' => $d])
                            <label class="field"><span>{{ __('website.downloads.new_file') }}</span><input type="file" name="file"></label>
                            <input type="hidden" name="active" value="0"><label class="check"><input type="checkbox" name="active" value="1" @checked($d->active)> {{ __('website.downloads.active') }}</label>
                            <div><button class="btn" type="submit">{{ __('website.save') }}</button></div>
                        </form>
                    </details>
                @endif
            </div>
        </article>
    @empty
        <p class="muted">{{ __('website.downloads.empty') }}</p>
    @endforelse
    @if ($canEdit)
        <details class="ws-add" @if ($errors->hasAny(['file', 'title.en', 'access'])) open @endif>
            <summary class="primary">+ {{ __('website.downloads.add') }}</summary>
            <form method="post" action="{{ route('website.downloads.store') }}" enctype="multipart/form-data" class="stack-sm mt">@csrf
                <label class="field"><span>{{ __('website.downloads.file') }}</span><input type="file" name="file" required><small>{{ __('website.downloads.file_hint') }}</small></label>
                @include('media.website._download-fields', ['d' => null])
                <div><button class="primary" type="submit">{{ __('website.add_go') }}</button></div>
            </form>
        </details>
    @endif
</section>
