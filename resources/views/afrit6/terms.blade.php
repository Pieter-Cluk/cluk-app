<x-layouts.member :title="__('terms.'.$doc.'.title')" :wide="true">
    <h1>{{ __('terms.'.$doc.'.title') }}</h1>
    <p class="login-note">{{ __('terms.version', ['version' => config('afrit6.terms_version')]) }}</p>
    <ol class="terms-list">
        @foreach (array_keys(trans('terms.'.$doc.'.items')) as $i)
            <li>{{ __('terms.'.$doc.'.items.'.$i, \App\Services\Afrit6\Pricing::termsParams()) }}</li>
        @endforeach
    </ol>
    <p><a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('afrit6.login') }}">← {{ __('terms.back') }}</a></p>
</x-layouts.member>
