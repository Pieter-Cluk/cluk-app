{{-- Why VIES said what it said, in plain words. --}}
@php($differs = $reason === 'name_differs' || ($viesName && \App\Services\Vies::nameDiffers($given, $viesName)))
@if ($reason && $reason !== 'name_differs')
    <small class="vies-why">{{ __('companies.vies.why_'.$reason) }}</small>
@endif
@if ($differs)
    <small class="vies-why">{{ __('companies.vies.why_name_differs', ['vies' => $viesName, 'given' => $given]) }}</small>
@endif
