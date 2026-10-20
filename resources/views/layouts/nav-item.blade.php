@php($active = request()->is(ltrim($item['path'], '/')) || request()->is(ltrim($item['path'], '/').'/*'))
<a class="nav-link{{ $active ? ' nav-active' : '' }}" href="{{ url($item['path']) }}" @if ($active) aria-current="page" @endif>
    <x-icon :name="$item['icon']"/>{{ __('nav.items.'.$item['key']) }}
</a>
