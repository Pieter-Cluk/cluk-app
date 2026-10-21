<x-layouts.phone :title="__('warehouse.clean.title', ['code' => $asset->code])" :back="route('w.home')">
    <section class="ph-card">
        <b>{{ $asset->item->name }} · {{ $asset->code }}</b>
        <small>{{ \App\Services\Warehouse\Moves::label($asset->location) }} · {{ __('warehouse.clean.room') }}</small>
    </section>
    <form method="post" action="{{ route('w.clean.save', $asset) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="photo_only" value="0" data-photo-only>
        <h2 class="ph-h">{{ __('warehouse.clean.how_dirty') }}</h2>
        <div class="ph-choice">
            @foreach (config('warehouse.cleaning') as $stage => $cents)
                <label class="ph-opt"><input type="radio" name="dirt" value="{{ $stage }}" @checked((int) old('dirt', $check->dirt ?? 1) === $stage)>
                    <b>{{ $stage }}</b><span>{{ __('warehouse.clean.stages.'.$stage) }}</span><small>{{ $cents ? '+ '.\App\Services\Afrit6\Pricing::eur($cents) : __('warehouse.clean.no_cost') }}</small></label>
            @endforeach
        </div>
        <p class="muted small">{{ __('warehouse.clean.stage_hint') }}</p>
        <h2 class="ph-h">{{ __('warehouse.clean.photo', ['need' => __('warehouse.clean.photo_need')]) }}</h2>
        @include('warehouse.phone._photos', ['stage' => 'clean'])
        <button class="primary ph-big" type="submit">{{ __('warehouse.clean.done_btn') }}</button>
    </form>
</x-layouts.phone>
