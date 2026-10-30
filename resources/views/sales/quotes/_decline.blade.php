{{-- Staff records that the customer declined (by phone or mail). Draft, sent or change asked. --}}
<details class="mt" @if ($errors->has('reason')) open @endif>
    <summary>{{ __('quotes.mark_declined') }}</summary>
    <p class="muted small">{{ __('quotes.mark_declined_hint') }}</p>
    <form method="post" action="{{ route('quotes.declined', $quote) }}" class="inline-form">
        @csrf
        <select name="reason" required aria-label="{{ __('quotes.decline_reason') }}"><option value="">{{ __('quotes.decline_reason') }}</option>@foreach (\App\Models\Sales\Quote::DECLINE_REASONS as $r)<option value="{{ $r }}">{{ __('quotes.decline_reasons.'.$r) }}</option>@endforeach</select>
        <input name="note" maxlength="1000" placeholder="{{ __('quotes.decline_note') }}" aria-label="{{ __('quotes.decline_note') }}">
        <button class="btn btn-danger" type="submit">{{ __('quotes.mark_declined_go') }}</button>
    </form>
</details>
