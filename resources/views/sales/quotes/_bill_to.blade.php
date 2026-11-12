{{-- Accepted quote: invoice to another company. $q = the quote, $action = the post route, $resend = offer the confirmation mail again. --}}
@php($blocked = \App\Services\Sales\InvoiceCompany::blocked($q))
<details class="mt" @if ($errors->has('company_id')) open @endif>
    <summary>{{ __('quotes.bill_to.title') }}</summary>
    @if ($blocked)
        <p class="muted small">{{ $blocked }}</p>
    @else
        <p class="muted small">{{ __('quotes.bill_to.hint') }}</p>
        <form method="post" action="{{ $action }}" class="stack-sm">
            @csrf
            <label class="field"><span>{{ __('quotes.bill_to.company') }}</span>
                <select data-pick name="company_id" required><option value="">—</option>@foreach (\App\Models\Company::active()->whereKeyNot($q->company_id)->orderBy('name')->get(['id', 'name']) as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $q->deal?->company_id !== $q->company_id ? $q->deal?->company_id : null) === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
            </label>
            @error('company_id')<p class="flash flash-err">{{ $message }}</p>@enderror
            @if ($resend ?? false)<label class="check"><input type="checkbox" name="resend" value="1"> {{ __('quotes.bill_to.resend') }}</label>@endif
            <div><button class="btn" type="submit">{{ __('quotes.bill_to.go') }}</button></div>
        </form>
    @endif
</details>
