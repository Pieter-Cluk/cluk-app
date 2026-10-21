<x-mail::message>
<h1>{{ $greeting }}</h1>

@foreach ($intro as $line)
{{ $line }}

@endforeach
@if ($details)
<x-mail::panel>
@foreach ($details as $label => $value)
**{{ $label }}:** {{ $value }}<br>
@endforeach
</x-mail::panel>
@endif
@if ($rows)
<x-mail::table>
| {{ __('quotes.cols.qty') }} | {{ __('quotes.cols.description') }} | {{ __('quotes.cols.amount') }} |
|:--|:--|--:|
@foreach ($rows as [$qty, $desc, $amount])
| {{ $qty }} | {{ str_replace('|', '/', $desc) }} | {{ $amount }} |
@endforeach
@foreach ($totals as $label => $value)
| | **{{ $label }}** | **{{ $value }}** |
@endforeach
</x-mail::table>
@endif
@foreach ($next as $line)
{{ $line }}

@endforeach
@if ($button)
<x-mail::button :url="$button[1]">
{{ $button[0] }}
</x-mail::button>
@endif
<div class="signature">@if ($signer)<p>{{ __('mails.regards') }}</p><p class="who">{{ $signer->name }}</p><p class="meta">{{ $signer->title ?: 'CLUK' }}@if ($signer->email) · {{ $signer->email }}@endif @if ($signer->mobile ?: $signer->phone) · {{ $signer->mobile ?: $signer->phone }}@endif</p>@else<p>{{ __('mails.regards') }}</p><p class="who">{{ $team ?? __('mails.team') }}</p>@endif<p class="motto">Cluk it, why not!</p></div>
@if ($button)
<x-slot:subcopy>
{{ __('mails.trouble', ['button' => $button[0]]) }} <span class="break-all">[{{ $button[1] }}]({{ $button[1] }})</span>
</x-slot:subcopy>
@endif
</x-mail::message>
