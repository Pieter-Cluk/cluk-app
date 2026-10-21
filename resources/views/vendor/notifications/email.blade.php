<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
<h1>{{ $greeting }}</h1>
@else
<h1>{{ __('mails.hello_plain') }}</h1>
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'error' : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@php($sig = ! empty($salutation) ? '<p>'.nl2br(e($salutation)).'</p>' : '<p>'.e(__('mails.regards')).'</p><p class="who">'.e(__('mails.team')).'</p>')
<div class="signature">{!! $sig !!}<p class="motto">Cluk it, why not!</p></div>

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
{{ __('mails.trouble', ['button' => $actionText]) }} <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
