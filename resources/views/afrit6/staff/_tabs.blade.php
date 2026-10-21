<nav class="tabs" aria-label="{{ __('nav.items.afrit6') }}">
    @foreach (['tasks' => 'afrit6.staff.tasks', 'room' => 'afrit6.staff.room', 'desks' => 'afrit6.staff.desks', 'members' => 'afrit6.staff.index'] as $key => $route)
        <a href="{{ route($route) }}" @class(['tab', 'tab-on' => $section === $key]) @if ($section === $key) aria-current="page" @endif>{{ __('workspace.staff.tabs.'.$key) }}</a>
    @endforeach
</nav>
