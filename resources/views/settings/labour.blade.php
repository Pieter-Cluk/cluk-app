<x-layouts.app :title="__('nav.items.labour')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('users.kicker')) }}</div>
            <h1>{{ __('nav.items.labour') }}</h1>
            <p class="muted">{{ __('settings.labour.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')

    <form method="post" action="{{ route('settings.labour.save') }}" class="card card-flush">
        @csrf @method('PUT')
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>{{ __('settings.labour.name') }}</th><th>{{ __('settings.labour.unit') }}</th><th>{{ __('settings.labour.price') }}</th><th>{{ __('settings.labour.active') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($types as $t)
                        <tr @class(['row-off' => ! $t->active])>
                            <td><div class="ws-langs">@foreach ($locales as $l)<label class="field"><span>{{ strtoupper($l) }}</span><input name="t[{{ $t->id }}][names][{{ $l }}]" maxlength="80" value="{{ $t->names[$l] ?? '' }}"></label>@endforeach</div></td>
                            <td><select name="t[{{ $t->id }}][unit]" aria-label="{{ __('settings.labour.unit') }}">@foreach (\App\Models\Sales\LabourType::UNITS as $u)<option value="{{ $u }}" @selected($t->unit === $u)>{{ __('settings.labour.units.'.$u) }}</option>@endforeach</select></td>
                            <td class="nowrap">€ <input type="number" name="t[{{ $t->id }}][price]" step="0.01" min="0" class="in-num" value="{{ $t->price_cents !== null ? number_format($t->price_cents / 100, 2, '.', '') : '' }}" aria-label="{{ __('settings.labour.price') }}">
                                @if ($t->price_cents === null)<br><small class="txt-warn">{{ __('settings.labour.no_rate') }}</small>@endif</td>
                            <td><input type="hidden" name="t[{{ $t->id }}][active]" value="0"><label class="check"><input type="checkbox" name="t[{{ $t->id }}][active]" value="1" @checked($t->active)> {{ __('settings.labour.on') }}</label></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><b class="small">{{ __('settings.labour.add') }}</b><div class="ws-langs">@foreach ($locales as $l)<label class="field"><span>{{ strtoupper($l) }}</span><input name="new[names][{{ $l }}]" maxlength="80"></label>@endforeach</div></td>
                        <td><select name="new[unit]" aria-label="{{ __('settings.labour.unit') }}">@foreach (\App\Models\Sales\LabourType::UNITS as $u)<option value="{{ $u }}">{{ __('settings.labour.units.'.$u) }}</option>@endforeach</select></td>
                        <td class="nowrap">€ <input type="number" name="new[price]" step="0.01" min="0" class="in-num" aria-label="{{ __('settings.labour.price') }}"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="form-actions pad"><button class="primary" type="submit">{{ __('settings.labour.save') }}</button></div>
    </form>
    <p class="muted small">{{ __('settings.labour.hint') }}</p>
</x-layouts.app>
