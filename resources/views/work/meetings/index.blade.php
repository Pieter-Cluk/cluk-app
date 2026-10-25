<x-layouts.app :title="__('nav.items.meetings')">
    <div class="pagehead">
        <div><h1>{{ __('nav.items.meetings') }}</h1><p class="muted">{{ __('meetings.intro') }}</p></div>
        <a class="primary" href="{{ route('meetings.create') }}">+ {{ __('meetings.new') }}</a>
    </div>
    @include('partials.flash')
    <form method="get" class="filters"><label class="field-search"><input type="search" name="q" value="{{ $q }}" placeholder="{{ __('meetings.search') }}" aria-label="{{ __('meetings.search') }}"></label></form>
    <section class="card card-flush mt">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('meetings.fields.held_on') }}</th><th>{{ __('meetings.fields.title') }}</th><th>{{ __('meetings.fields.kind') }}</th><th class="num">{{ __('meetings.tasks') }}</th></tr></thead>
                <tbody>
                    @forelse ($meetings as $m)
                        <tr class="db-row">
                            <td class="nowrap">{{ $m->held_on->format('d/m/Y') }}</td>
                            <td><a class="db-row-link" href="{{ route('meetings.show', $m) }}"><b>{{ $m->title }}</b></a>@if ($m->proposals) <span class="pill pill-warn">{{ __('meetings.to_check') }}</span>@endif<br><small class="muted">{{ $m->createdBy?->name }}</small></td>
                            <td>{{ $m->kind === 'customer' ? ($m->company?->name ?? __('meetings.kinds.customer')) : __('meetings.kinds.internal') }}</td>
                            <td class="num nowrap">@if ($m->todos_count){{ $m->open_count }} / {{ $m->todos_count }} <small class="muted">{{ __('meetings.open') }}</small>@else — @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ __('meetings.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$meetings"/>
</x-layouts.app>
