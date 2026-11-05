{{-- Who arranges the transport: must be chosen, no default. $value = current choice or null. --}}
<div class="lg-choice" role="radiogroup" aria-label="{{ __('purchasing.delivery_choose') }}">
    @foreach (\App\Models\Purchasing\PurchaseRequest::DELIVERY as $o)
        <label class="lg-opt"><input type="radio" name="delivery" value="{{ $o }}" required @checked(old('delivery', $value ?? null) === $o)><span><b>{{ __('purchasing.delivery_by.'.$o) }}</b><small class="muted">{{ __('purchasing.delivery_hint.'.$o) }}</small></span></label>
    @endforeach
</div>
