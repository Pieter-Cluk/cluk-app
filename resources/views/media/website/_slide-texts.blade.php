{{-- Texts on a slide (shown on the sales slideshow; on the other pages only the description for screen readers). --}}
@if ($place === 'sales_slider')
    <div class="ws-slide-texts">
        @foreach (['eyebrow', 'title', 'text', 'cta'] as $f)
            <div><b class="small">{{ __('website.slide.'.$f) }}</b>
                <div class="ws-langs">@foreach ($locales as $l)<label class="field"><span>{{ strtoupper($l) }}</span><input name="texts[{{ $l }}][{{ $f }}]" value="{{ $s?->texts[$l][$f] ?? '' }}" maxlength="{{ $f === 'text' ? 300 : 120 }}"></label>@endforeach</div>
            </div>
        @endforeach
    </div>
@endif
<label class="field"><span>{{ __('website.slide.alt') }}</span><input name="alt" value="{{ $s?->alt }}" maxlength="160" placeholder="{{ __('website.slide.alt_hint') }}"></label>
