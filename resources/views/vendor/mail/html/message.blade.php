@php($me = \App\Models\CompanyDetail::current())
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="'https://'.($me->website ?: 'cluk.live')">
CLUK
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**{{ trim($me->legal_name.' '.$me->legal_form) }}** · {{ $me->street }}, {{ $me->postcode }} {{ $me->city }} · {{ \App\Support\VatNumber::format($me->vat_number) }}<br>
[{{ $me->website ?: 'cluk.live' }}](https://{{ $me->website ?: 'cluk.live' }}) · [{{ __('mails.terms') }}]({{ rtrim(config('app.url'), '/') }}/terms)
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
