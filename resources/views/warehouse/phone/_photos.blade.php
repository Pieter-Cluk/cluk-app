{{-- Photos of a check: thumbnails and the camera button (the form sends right away). --}}
@php($list = collect($check->photos ?? [])->map(fn ($p, $i) => $p + ['i' => $i])->when($stage ?? null, fn ($c) => $c->where('stage', $stage)))
<div class="ph-photos">
    @foreach ($list as $p)
        <a href="{{ route('w.photo', [$check, $p['i']]) }}" target="_blank"><img src="{{ route('w.photo', [$check, $p['i']]) }}" alt="" loading="lazy"></a>
    @endforeach
    <label class="ph-photo-add">＋<span>{{ __('warehouse.photo') }}</span><input type="file" name="photos[]" accept="image/*" capture="environment" multiple data-autosend hidden></label>
</div>
