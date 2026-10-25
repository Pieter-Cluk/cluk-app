{{-- Where a click goes: a kind of link and its value (with suggestions per kind). --}}
@php($lt = old('link_type', $s?->link_type ?? 'none'))
<div class="ws-link" data-ws-link>
    <label class="field"><span>{{ __('website.link.label') }}</span>
        <select name="link_type" data-ws-type>@foreach (\App\Models\Website\Slide::LINKS as $t)<option value="{{ $t }}" @selected($lt === $t)>{{ __('website.link.types.'.$t) }}</option>@endforeach</select>
    </label>
    <label class="field" @if ($lt === 'none') hidden @endif data-ws-value-wrap><span>{{ __('website.link.value') }}</span>
        <input name="link_value" value="{{ old('link_value', $s?->link_value) }}" maxlength="255" list="ws-list-{{ $lt }}" data-ws-value autocomplete="off" placeholder="{{ __('website.link.hint') }}">
    </label>
</div>
