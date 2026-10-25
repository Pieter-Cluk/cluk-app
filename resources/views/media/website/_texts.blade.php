{{-- One text in every language. $name = input name prefix, $values = [locale => text]. --}}
<div class="ws-langs">
    @foreach ($locales as $l)
        <label class="field"><span>{{ strtoupper($l) }}</span>
            @if (! empty($area))<textarea name="{{ $name }}[{{ $l }}]" rows="2" maxlength="{{ $max ?? 400 }}" placeholder="{{ $placeholder[$l] ?? '' }}">{{ $values[$l] ?? '' }}</textarea>
            @else<input name="{{ $name }}[{{ $l }}]" value="{{ $values[$l] ?? '' }}" maxlength="{{ $max ?? 400 }}" placeholder="{{ $placeholder[$l] ?? '' }}">@endif
        </label>
    @endforeach
</div>
