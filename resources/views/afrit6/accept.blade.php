<x-layouts.member :title="__('terms.accept_title')" :wide="true">
    <h1>{{ __('terms.accept_title') }}</h1>
    <p>{{ __('terms.accept_intro') }}</p>
    @include('afrit6._messages')
    @foreach (['rules', 'payment'] as $doc)
        <details class="terms-box">
            <summary><b>{{ __('terms.'.$doc.'.title') }}</b></summary>
            <ol class="terms-list">
                @foreach (array_keys(trans('terms.'.$doc.'.items')) as $i)
                    <li>{{ __('terms.'.$doc.'.items.'.$i, \App\Services\Afrit6\Pricing::termsParams()) }}</li>
                @endforeach
            </ol>
        </details>
    @endforeach
    <form method="post" action="{{ route('afrit6.accept.store') }}" class="member-form">
        @csrf
        @include('afrit6._terms-ticks')
        <button class="member-btn mrow" type="submit">{{ __('terms.accept_btn') }}</button>
    </form>
    <form method="post" action="{{ route('afrit6.logout') }}">@csrf<button class="linkbtn light" type="submit">{{ __('afrit6.logout') }}</button></form>
</x-layouts.member>
