<x-layouts.app :title="__('users.create.title')">
    @include('settings.users._head', ['heading' => __('users.create.title'), 'tab' => 'people'])

    <form class="card" method="post" action="{{ route('settings.users.store') }}">
        @csrf
        @include('settings.users._details')
        <div class="form-actions">
            <a class="btn" href="{{ route('settings.users.index') }}">{{ __('users.edit.back') }}</a>
            <button class="primary" type="submit">{{ __('users.create.save') }}</button>
        </div>
    </form>
</x-layouts.app>
