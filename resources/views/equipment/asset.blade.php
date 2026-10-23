@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($fmt = fn ($d) => $d?->timezone(config('app.timezone'))->format('d/m/Y H:i'))
@php($retired = $asset->status === 'retired')
<x-layouts.app :title="$asset->code">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('equipment.show', $asset->item) }}">{{ $asset->item->number }} · {{ $asset->item->name }}</a></div>
            <h1>{{ $asset->code }}</h1>
            <p class="muted">{{ __('equipment.fields.serial_number') }}: {{ $asset->serial_number ?? '—' }} · <span @class(['pill', 'pill-ok' => $asset->status === 'available', 'pill-info' => $asset->status === 'out', 'pill-warn' => $asset->status === 'repair', 'pill-off' => $retired])>{{ __('equipment.statuses.'.$asset->status) }}@if ($retired) · {{ __('equipment.reasons.'.$asset->retired_reason) }}@endif</span></p>
        </div>
    </div>
    @include('partials.flash')

    <div class="split">
        <div class="stack">
            <section class="card">
                <h2>{{ __('equipment.details') }}</h2>
                <dl class="facts">
                    <dt>{{ __('equipment.fields.location') }}</dt><dd>{{ $asset->location ? \App\Services\Warehouse\Moves::label($asset->location).($asset->location !== \App\Services\Warehouse\Moves::label($asset->location) ? ' · '.$asset->location : '') : '—' }}@if ($asset->outOrder) · {{ $asset->outOrder->number }}@endif @if ($asset->container) · {{ __('warehouse.carriers.in_it', ['carrier' => $asset->container->code]) }}@endif</dd>
                    @if ($asset->isContainer())<dt>{{ __('warehouse.carriers.contents') }}</dt><dd>{{ $asset->packed->isEmpty() && $asset->counted->isEmpty() ? __('warehouse.carriers.empty') : $asset->packed->pluck('code')->merge($asset->counted->map(fn ($c) => $c->qty.' × '.($c->item->type ?: $c->item->name)))->implode(', ') }}</dd>@endif
                    <dt>{{ __('equipment.fields.purchase_date') }}</dt><dd>{{ $asset->purchase_date?->format('d/m/Y') ?? '—' }}</dd>
                    @if ($canMoney)
                        <dt>{{ __('equipment.fields.purchase_price') }}</dt><dd>{{ $asset->purchase_price_cents !== null ? $eur($asset->purchase_price_cents) : '—' }}</dd>
                        <dt>{{ __('equipment.fields.supplier') }}</dt><dd>{{ $asset->supplier ?? '—' }}</dd>
                        <dt>{{ __('equipment.book_value') }}</dt><dd>{{ ($b = $asset->bookValueCents()) !== null ? $eur($b) : '—' }}</dd>
                    @endif
                    <dt>{{ __('equipment.insured_value') }}</dt><dd>{{ $asset->item->catalog_price_cents !== null ? $eur($asset->item->catalog_price_cents) : '—' }}</dd>
                    <dt>{{ __('equipment.fields.warranty_until') }}</dt><dd>{{ $asset->warranty_until?->format('d/m/Y') ?? '—' }}</dd>
                    <dt>{{ __('equipment.fields.note') }}</dt><dd>{{ $asset->note ?? '—' }}</dd>
                    <dt>{{ __('equipment.label') }}</dt><dd>{{ $asset->label_printed_at ? $fmt($asset->label_printed_at) : __('equipment.not_printed') }}</dd>
                </dl>
            </section>

            @if ($canEdit && ! $retired)
                <section class="card">
                    <h2>{{ __('equipment.place.title') }}</h2>
                    <form method="post" action="{{ route('equipment.assets.place_one', $asset) }}" class="inline-form">@csrf
                        <input name="place" list="place-codes" required placeholder="{{ $asset->isContainer() ? 'GA1B1' : __('equipment.place.placeholder') }}" aria-label="{{ __('equipment.place.title') }}">
                        <button class="btn" type="submit">{{ __('equipment.place.go') }}</button>
                    </form>
                    @error('place')<p class="txt-danger small">{{ $message }}</p>@enderror
                    @include('equipment._place-codes', ['cases' => ! $asset->isContainer(), 'except' => $asset->id])
                    <p class="muted small">{{ $asset->isContainer() ? __('equipment.place.case_hint') : __('equipment.place.hint') }}</p>
                    <h2 class="mt">{{ __('equipment.edit_asset') }}</h2>
                    <form method="post" action="{{ route('equipment.assets.update', $asset) }}" class="form-grid">
                        @csrf @method('PUT')
                        <label class="field"><span>{{ __('equipment.fields.serial_number') }}</span><input name="serial_number" maxlength="80" value="{{ old('serial_number', $asset->serial_number) }}"></label>
                        <label class="field"><span>{{ __('equipment.fields.status') }}</span><select name="status">@foreach (['available', 'out', 'repair'] as $s)<option value="{{ $s }}" @selected(old('status', $asset->status) === $s)>{{ __('equipment.statuses.'.$s) }}</option>@endforeach</select></label>
                        <label class="field"><span>{{ __('equipment.fields.location') }}</span><input name="location" maxlength="60" value="{{ old('location', $asset->location) }}"></label>
                        <label class="field"><span>{{ __('equipment.fields.purchase_date') }}</span><input type="date" name="purchase_date" value="{{ old('purchase_date', $asset->purchase_date?->toDateString()) }}"></label>
                        @if ($canMoney)
                            <label class="field"><span>{{ __('equipment.fields.purchase_price') }}</span><input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $asset->purchase_price_cents !== null ? number_format($asset->purchase_price_cents / 100, 2, '.', '') : '') }}"></label>
                            <label class="field"><span>{{ __('equipment.fields.supplier') }}</span><input name="supplier" maxlength="160" value="{{ old('supplier', $asset->supplier) }}"></label>
                        @endif
                        <label class="field"><span>{{ __('equipment.fields.warranty_until') }}</span><input type="date" name="warranty_until" value="{{ old('warranty_until', $asset->warranty_until?->toDateString()) }}"></label>
                        <label class="field"><span>{{ __('equipment.fields.note') }}</span><input name="note" maxlength="500" value="{{ old('note', $asset->note) }}"></label>
                        <div class="span-2"><button class="primary" type="submit">{{ __('equipment.save') }}</button></div>
                    </form>
                </section>
            @endif
            @if ($canRetire && ! $retired)
                <section class="card">
                    <h2>{{ __('equipment.retire') }}</h2>
                    <p class="muted small">{{ __('equipment.retire_hint') }}</p>
                    <form method="post" action="{{ route('equipment.assets.retire', $asset) }}" class="inline-form" onsubmit="return confirm(@js(__('equipment.retire_confirm', ['code' => $asset->code])))">
                        @csrf
                        <select name="reason" required aria-label="{{ __('equipment.retire') }}"><option value="">{{ __('equipment.choose_reason') }}</option>@foreach (\App\Models\Equipment\Asset::RETIRE_REASONS as $r)<option value="{{ $r }}">{{ __('equipment.reasons.'.$r) }}</option>@endforeach</select>
                        <button class="btn btn-danger" type="submit">{{ __('equipment.retire') }}</button>
                    </form>
                </section>
            @endif
        </div>
        <aside class="stack">
            <section class="card qr-card">
                <h2>{{ __('equipment.label') }}</h2>
                <div class="qr-preview">{!! \App\Support\QrSvg::make(route('equipment.scan', $asset->code)) !!}<b>{{ $asset->code }}</b></div>
                <p class="muted small">{{ __('equipment.label_hint') }}</p>
                @if ($canEdit && ! $retired)<a class="btn wide" href="{{ route('equipment.labels', ['assets' => $asset->id]) }}" target="_blank" rel="noopener">{{ __('equipment.print') }}</a>@endif
            </section>
            <section class="card">
                <h2>{{ __('warehouse.office.moves') }}</h2>
                @forelse ($asset->moves()->with(['user', 'order'])->limit(15)->get() as $m)
                    <div class="log-line"><small class="muted">{{ $fmt($m->created_at) }} · {{ $m->user?->name }}</small><div>{{ __('warehouse.kinds.'.$m->kind) }} · {{ \App\Services\Warehouse\Moves::label($m->from_code) }} → {{ \App\Services\Warehouse\Moves::label($m->to_code) }}@if ($m->order) <span class="muted">· {{ $m->order->number }}</span>@endif</div></div>
                @empty
                    <p class="muted">{{ __('warehouse.office.no_moves') }}</p>
                @endforelse
            </section>
            <section class="card">
                <h2>{{ __('users.edit.activity') }}</h2>
                @forelse ($activity as $log)
                    <div class="log-line"><small class="muted">{{ $fmt($log->created_at) }} · {{ $log->actor?->name ?? __('users.audit.system') }}</small><div>{{ \App\Support\AuditText::action($log) }}@if ($d = \App\Support\AuditText::details($log)) <span class="muted">· {{ $d }}</span>@endif</div></div>
                @empty
                    <p class="muted">{{ __('users.edit.no_activity') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layouts.app>
