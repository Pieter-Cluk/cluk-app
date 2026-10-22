<x-layouts.app :title="__('warehouse.labels.title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('warehouse.index') }}">{{ mb_strtoupper(__('warehouse.office.title')) }}</a></div>
            <h1>{{ __('warehouse.labels.title') }}</h1>
            <p class="muted">{{ __('warehouse.labels.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @php($canPrint = auth()->user()->canUseModule('warehouse', 2))

    <nav class="tabs">
        <a @class(['tab', 'tab-on' => $tab === 'locations']) href="{{ route('warehouse.labels') }}">{{ __('warehouse.labels.locations') }} <small class="muted">59 × 102 mm</small></a>
        <a @class(['tab', 'tab-on' => $tab === 'items']) href="{{ route('warehouse.labels', ['tab' => 'items']) }}">{{ __('warehouse.labels.items') }} <small class="muted">25 × 25 mm</small></a>
    </nav>

    @if ($tab === 'locations')
        <form method="get" class="inline-form mt">
            <label class="field"><span>{{ __('warehouse.labels.floor') }}</span><select name="floor" onchange="this.form.submit()"><option value="">{{ __('warehouse.labels.all') }}</option>@foreach (['G', 'M'] as $f)<option value="{{ $f }}" @selected($floor === $f)>{{ __('warehouse.floors.'.$f) }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('warehouse.labels.row') }}</span><select name="row" onchange="this.form.submit()"><option value="">{{ __('warehouse.labels.all') }}</option>@foreach ($rows as $r)<option value="{{ $r }}" @selected($row === $r)>{{ $r }}</option>@endforeach</select></label>
        </form>
        <form method="post" action="{{ route('warehouse.labels.locations') }}" target="_blank" class="lbl-pick mt">@csrf
            <div class="lbl-bar">
                <label class="check"><input type="checkbox" data-all><span>{{ __('warehouse.labels.select_all') }}</span></label>
                <span class="muted" data-count>0</span>
                @if ($canPrint)<button class="primary" type="submit" data-print disabled>{{ __('warehouse.labels.print') }}</button>@endif
            </div>
            <section class="card card-flush">
                <table class="table">
                    <thead><tr><th class="w-check"></th><th>{{ __('warehouse.labels.code') }}</th><th>{{ __('warehouse.labels.name') }}</th></tr></thead>
                    <tbody>
                        @foreach ($zones as $z)
                            <tr><td><input type="checkbox" name="codes[]" value="{{ $z->code }}" aria-label="{{ $z->code }}"></td><td><b>{{ $z->code }}</b></td><td>{{ $z->label() }}</td></tr>
                        @endforeach
                        @foreach ($positions as $p)
                            <tr><td><input type="checkbox" name="codes[]" value="{{ $p->code }}" aria-label="{{ $p->code }}"></td><td><b>{{ $p->code }}</b></td><td>{{ \App\Http\Controllers\Warehouse\LabelController::positionName($p) }}</td></tr>
                        @endforeach
                        @if ($zones->isEmpty() && $positions->isEmpty())<tr><td colspan="3" class="muted">{{ __('warehouse.labels.none') }}</td></tr>@endif
                    </tbody>
                </table>
            </section>
        </form>
    @else
        <form method="get" class="inline-form mt">
            <input type="hidden" name="tab" value="items">
            <input name="q" value="{{ $q }}" placeholder="{{ __('warehouse.labels.search') }}" aria-label="{{ __('warehouse.labels.search') }}">
            <label class="check"><input type="checkbox" name="new" value="1" @checked($new) onchange="this.form.submit()"><span>{{ __('warehouse.labels.never_printed') }}</span></label>
            <button class="btn" type="submit">{{ __('warehouse.labels.find') }}</button>
        </form>
        <form method="post" action="{{ route('warehouse.labels.items') }}" target="_blank" class="lbl-pick mt">@csrf
            <div class="lbl-bar">
                <label class="check"><input type="checkbox" data-all><span>{{ __('warehouse.labels.select_all') }}</span></label>
                <span class="muted" data-count>0</span>
                @if ($canPrint)<button class="primary" type="submit" data-print disabled>{{ __('warehouse.labels.print') }}</button>@endif
            </div>
            <section class="card card-flush">
                <table class="table">
                    <thead><tr><th class="w-check"></th><th>{{ __('warehouse.labels.code') }}</th><th>{{ __('warehouse.labels.item') }}</th><th>{{ __('warehouse.labels.serial') }}</th><th>{{ __('warehouse.labels.printed') }}</th></tr></thead>
                    <tbody>
                        @forelse ($assets as $a)
                            <tr><td><input type="checkbox" name="ids[]" value="{{ $a->id }}" aria-label="{{ $a->code }}"></td><td><b>{{ $a->code }}</b></td><td>{{ $a->item?->name }}</td><td>{{ $a->serial_number ?: '—' }}</td><td class="muted">{{ $a->label_printed_at?->format('d/m/Y') ?? __('warehouse.labels.never') }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="muted">{{ __('warehouse.labels.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            @if ($assets->count() === 300)<p class="muted">{{ __('warehouse.labels.limited') }}</p>@endif
        </form>
    @endif

    <script>
        document.querySelectorAll('.lbl-pick').forEach(function (f) {
            var boxes = f.querySelectorAll('tbody input[type=checkbox]'), all = f.querySelector('[data-all]'), count = f.querySelector('[data-count]'), btn = f.querySelector('[data-print]');
            function update() {
                var n = f.querySelectorAll('tbody input:checked').length;
                count.textContent = @js(__('warehouse.labels.selected')).replace(':n', n);
                if (btn) btn.disabled = n === 0;
                all.checked = n > 0 && n === boxes.length;
            }
            all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
            boxes.forEach(function (b) { b.addEventListener('change', update); });
            update();
        });
    </script>
</x-layouts.app>
