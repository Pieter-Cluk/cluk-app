@php($fmt = fn ($d) => $d ? $d->locale(app()->getLocale())->translatedFormat('D j M · H:i') : '—')
@php($in = $dir === 'in')
@php($closed = $in ? $r->received_at : $r->handed_back_at)
@php($photos = ($in ? $r->in_photos : $r->out_photos) ?? [])
<x-layouts.phone :title="__('warehouse.sub.title_'.$dir)" :back="route('w.home')">
    <section class="ph-card">
        <small class="ph-step">{{ $r->code() }} · {{ __('warehouse.sub.not_ours') }}</small>
        <b>{{ $r->supplier->name }}</b>
        <small>{{ $r->order->title }} · {{ $r->order->customerName() }}</small>
        <small>{{ $in ? __('warehouse.sub.expected', ['when' => $fmt($r->delivery_at)]) : __('warehouse.sub.back_by', ['when' => $fmt($r->return_at)]) }}</small>
        @if ($r->their_ref)<small>{{ __('warehouse.sub.their_ref', ['ref' => $r->their_ref]) }}</small>@endif
    </section>
    @if ($closed)
        <p class="flash flash-ok">{{ __('warehouse.sub.closed_'.$dir, ['when' => $fmt($closed), 'name' => ($in ? $r->receivedBy : $r->handedBackBy)?->name ?? '—']) }}</p>
    @endif

    <h2 class="ph-h">1 · {{ __('warehouse.sub.count') }}</h2>
    <ul class="ph-lines">
        @foreach ($r->lines as $l)
            @php($n = $in ? $l->received_qty : $l->back_qty)
            @php($of = $in ? $l->qty : $l->received_qty)
            <li @class(['ph-line', 'ph-line-ok' => $n >= $of])>
                <div class="ph-line-head"><b>{{ $l->name }}</b><span class="ph-cnt">{{ $n }} / {{ $of }}</span></div>
                @unless ($closed)
                    <div class="ph-step-btns">
                        @foreach ([-1 => '−', 1 => '+'] as $delta => $label)<form method="post" action="{{ route('w.sub.count', [$r, $dir, $l]) }}">@csrf<input type="hidden" name="delta" value="{{ $delta }}"><button class="btn" type="submit" aria-label="{{ $delta > 0 ? '+1' : '−1' }}">{{ $label }}</button></form>@endforeach
                        <form method="post" action="{{ route('w.sub.count', [$r, $dir, $l]) }}">@csrf<input type="hidden" name="delta" value="{{ $of - $n }}"><button class="btn" type="submit">{{ __('warehouse.pick.all') }}</button></form>
                    </div>
                @endunless
            </li>
        @endforeach
    </ul>

    <h2 class="ph-h">2 · {{ __('warehouse.sub.photos') }}</h2>
    <p class="muted small">{{ __('warehouse.sub.photos_hint') }}</p>
    <form method="post" action="{{ route('w.sub.photo', [$r, $dir]) }}" enctype="multipart/form-data">@csrf
        <div class="ph-photos">
            @foreach ($photos as $i => $p)<a href="{{ route('w.sub.media', [$r, $dir, $i]) }}" target="_blank"><img src="{{ route('w.sub.media', [$r, $dir, $i]) }}" alt="" loading="lazy"></a>@endforeach
            @unless ($closed)<label class="ph-photo-add">＋<span>{{ __('warehouse.photo') }}</span><input type="file" name="photos[]" accept="image/*" capture="environment" multiple data-autosend hidden></label>@endunless
        </div>
    </form>

    @unless ($closed)
        <form method="post" action="{{ route('w.sub.done', [$r, $dir]) }}" class="stack-sm">@csrf
            <h2 class="ph-h">3 · {{ __('warehouse.sub.note') }}</h2>
            <textarea name="note" rows="3" maxlength="1000" placeholder="{{ __('warehouse.sub.note_hint') }}" aria-label="{{ __('warehouse.sub.note') }}">{{ old('note') }}</textarea>
            <button class="primary ph-big" type="submit">{{ __('warehouse.sub.done_btn_'.$dir) }}</button>
        </form>
    @else
        @if ($in ? $r->in_note : $r->out_note)<p class="ph-card">“{{ $in ? $r->in_note : $r->out_note }}”</p>@endif
    @endunless
</x-layouts.phone>
