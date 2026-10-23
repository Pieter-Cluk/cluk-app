{{-- Customer card › Credit check: traffic light with its reasons, and what the official register says. --}}
@php($check = \App\Services\Credit\CreditCheck::for($company))
@php($reg = $company->registry)
@php($belgian = (bool) \App\Models\CompanyRegistry::numberFromVat($company->vat_number))
<section class="card credit" id="credit">
    <div class="card-head">
        <h2>{{ __('credit.title') }}</h2>
        <span class="tl" role="img" aria-label="{{ __('credit.levels.'.$check['level']) }}"><i @class(['r', 'on' => $check['level'] === 'red'])></i><i @class(['o', 'on' => $check['level'] === 'orange'])></i><i @class(['g', 'on' => $check['level'] === 'green'])></i></span>
    </div>
    <p class="credit-level credit-{{ $check['level'] }}"><b>{{ __('credit.levels.'.$check['level']) }}</b> · {{ __('credit.advice.'.$check['level']) }}</p>
    @if ($check['reasons'])
        <ul class="credit-reasons">@foreach ($check['reasons'] as $r)<li class="cr-{{ $r['level'] }}">{{ $r['text'] }}</li>@endforeach</ul>
    @elseif ($check['level'] === 'green')
        <p class="muted small">{{ __('credit.no_signs') }}</p>
    @endif

    @if ($reg && $reg->found)
        <dl class="facts mt">
            @if ($reg->official_name)<dt>{{ __('credit.reg.name') }}</dt><dd>{{ $reg->official_name }}</dd>@endif
            @if ($reg->form)<dt>{{ __('credit.reg.form') }}</dt><dd>{{ $reg->text('form') }}</dd>@endif
            <dt>{{ __('credit.reg.status') }}</dt><dd>{{ $reg->status === 'AC' ? __('credit.reg.active') : __('credit.reg.stopped') }}@if (! $reg->isNormal()) · {{ $reg->text('situation') ?? $reg->situation_code }}@endif</dd>
            @if ($reg->start_date)<dt>{{ __('credit.reg.since') }}</dt><dd>{{ $reg->start_date->format('d/m/Y') }} <small class="muted">({{ trans_choice('credit.reg.years', (int) $reg->start_date->diffInYears(today()), ['n' => (int) $reg->start_date->diffInYears(today())]) }})</small></dd>@endif
            @if ($reg->address)<dt>{{ __('credit.reg.address') }}</dt><dd>{{ $reg->address }}</dd>@endif
        </dl>
    @endif
    <p class="muted small mt">
        @if ($reg)
            {{ __('credit.reg.source', ['date' => $reg->snapshot_date?->format('d/m/Y') ?? '—']) }} · <a href="{{ $reg->publicUrl() }}" target="_blank" rel="noopener">{{ __('credit.reg.open') }}</a>
        @elseif ($belgian)
            {{ __('credit.reg.pending') }}
        @else
            {{ __('credit.reg.not_belgian') }}
        @endif
        <br>{{ __('credit.nbb_later') }}
    </p>
</section>
