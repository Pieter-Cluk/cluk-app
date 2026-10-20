@php($active = collect([$item['path'], ...($item['also'] ?? [])])->contains(fn ($p) => request()->is(ltrim($p, '/')) || request()->is(ltrim($p, '/').'/*')))
<a class="nav-link{{ $active ? ' nav-active' : '' }}" href="{{ url($item['path']) }}" @if ($active) aria-current="page" @endif>
    <x-icon :name="$item['icon']"/>{{ __('nav.items.'.$item['key']) }}
</a>
