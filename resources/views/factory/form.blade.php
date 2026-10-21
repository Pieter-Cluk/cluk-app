@php($v = fn ($k, $d = null) => old($k, $d))
<x-layouts.app :title="$b->exists ? $b->number : __('factory.new')">
    <div class="pagehead"><div><div class="kicker"><a href="{{ route('factory.index') }}">CLUK FACTORY</a></div><h1>{{ $b->exists ? $b->number : __('factory.new') }}</h1></div></div>
    @include('partials.flash')
    <form method="post" action="{{ $b->exists ? route('factory.update', $b) : route('factory.store') }}" class="card">
        @csrf @if ($b->exists) @method('PUT') @endif
        <div class="form-grid">
            <label class="field span-2"><span>{{ __('deals.fields.title') }}</span><input name="title" required maxlength="200" value="{{ $v('title', $b->title) }}"></label>
            <label class="field"><span>{{ __('factory.event_type') }}</span><select name="event_type">@foreach (config('factory.event_types') as $t)<option value="{{ $t }}" @selected($v('event_type', $b->event_type) === $t)>{{ __('factory.types.'.$t) }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('factory.book.guests') }}</span><input type="number" name="guests" min="1" max="{{ config('factory.max_guests') }}" required value="{{ $v('guests', $b->guests) }}"></label>
            <label class="field"><span>{{ __('factory.book.date') }}</span><input type="date" name="start_date" required value="{{ $v('start_date', $b->start_date?->toDateString()) }}"></label>
            <label class="field"><span>{{ __('factory.book.days') }}</span><input type="number" name="days" min="1" max="14" required value="{{ $v('days', $b->days) }}"></label>
            <label class="field"><span>{{ __('factory.book.buildup') }}</span><input type="number" name="buildup_days" min="0" max="7" required value="{{ $v('buildup_days', $b->buildup_days ?? 0) }}"></label>
            <label class="field"><span>{{ __('factory.book.breakdown') }}</span><input type="number" name="breakdown_days" min="0" max="7" required value="{{ $v('breakdown_days', $b->breakdown_days ?? 0) }}"></label>
            <label class="field"><span>{{ __('factory.space') }}</span><select name="size">@foreach (array_keys(config('factory.sizes')) as $z)<option value="{{ $z }}" @selected($v('size', $b->size) === $z)>{{ $z }} · {{ config('factory.sizes.'.$z.'.0') }} m²</option>@endforeach</select></label>
            <label class="field"><span>{{ __('factory.level') }}</span><select name="level">@foreach (array_keys(config('factory.levels')) as $l)<option value="{{ $l }}" @selected($v('level', $b->level) === $l)>{{ __('factory.levels.'.$l) }}</option>@endforeach</select></label>
            <fieldset class="plain span-2"><legend>{{ __('factory.extras_title') }}</legend><div class="chips">@foreach (config('factory.extras') as $x)<label class="check"><input type="checkbox" name="extras[]" value="{{ $x }}" @checked(in_array($x, $v('extras', $b->extras ?? []), true))> {{ __('factory.extras.'.$x) }}</label>@endforeach</div></fieldset>
            <label class="field"><span>{{ __('quotes.language') }}</span><select name="language">@foreach (config('cluk.locales') as $c => $n)<option value="{{ $c }}" @selected($v('language', $b->language) === $c)>{{ $n }}</option>@endforeach</select></label>
            <label class="field"><span>{{ __('factory.book.company') }}</span><input name="req_company" maxlength="160" value="{{ $v('req_company', $b->req_company) }}"></label>
            <label class="field"><span>{{ __('factory.book.vat') }}</span><input name="req_vat" maxlength="30" value="{{ $v('req_vat', $b->req_vat) }}"></label>
            <label class="field"><span>{{ __('factory.book.name') }}</span><input name="req_name" maxlength="160" value="{{ $v('req_name', $b->req_name) }}"></label>
            <label class="field"><span>{{ __('factory.book.email') }}</span><input type="email" name="req_email" maxlength="190" value="{{ $v('req_email', $b->req_email) }}"></label>
            <label class="field"><span>{{ __('factory.book.phone') }}</span><input name="req_phone" maxlength="40" value="{{ $v('req_phone', $b->req_phone) }}"></label>
            <label class="field span-2"><span>{{ __('factory.book.tell') }}</span><textarea name="note" rows="3" maxlength="3000">{{ $v('note', $b->note) }}</textarea></label>
        </div>
        <div class="form-actions"><a class="btn" href="{{ $b->exists ? route('factory.show', $b) : route('factory.index') }}">{{ __('equipment.cancel') }}</a><button class="primary" type="submit">{{ __('factory.save') }}</button></div>
    </form>
</x-layouts.app>
