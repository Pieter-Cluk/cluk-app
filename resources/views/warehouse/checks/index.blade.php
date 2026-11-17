<x-layouts.app :title="__('nav.items.checkbuilder')">
    <div class="pagehead"><div>
        <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }} / {{ mb_strtoupper(__('nav.items.checkbuilder')) }}</div>
        <h1>{{ __('nav.items.checkbuilder') }}</h1>
        <p class="muted">{{ __('checks.builder.intro') }}</p>
    </div></div>
    @include('partials.flash')
    <section class="card card-flush">
        <table class="table">
            <thead><tr><th>{{ __('checks.builder.procedure') }}</th><th class="num">{{ __('checks.builder.steps') }}</th><th class="num">{{ __('checks.builder.items') }}</th><th>{{ __('checks.builder.version') }}</th></tr></thead>
            <tbody>
                @forelse ($procedures as $p)
                    <tr>
                        <td><a href="{{ route('checks.edit', $p) }}"><b>{{ $p->name() }}</b></a>@unless ($p->active) <span class="pill">{{ __('checks.builder.off') }}</span>@endunless</td>
                        <td class="num">{{ count($p->draft_steps ?? []) }}</td>
                        <td class="num">{{ $p->items_count }}</td>
                        <td>@if ($p->current)v{{ $p->current->version }}@else<span class="pill pill-warn">{{ __('checks.builder.not_published') }}</span>@endif @if ($p->current && $p->draftChanged()) <span class="pill pill-warn">{{ __('checks.builder.changes') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('checks.builder.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <section class="card mt">
        <h2>{{ __('checks.builder.new') }}</h2>
        <form method="post" action="{{ route('checks.store') }}" class="form-grid">@csrf
            <label class="field"><span>{{ __('checks.builder.name') }} (NL)</span><input name="name_nl" maxlength="120" placeholder="{{ __('checks.builder.name_hint') }}"></label>
            <label class="field"><span>{{ __('checks.builder.name') }} (EN)</span><input name="name_en" maxlength="120"></label>
            <div><button class="primary" type="submit">{{ __('checks.builder.create') }}</button></div>
        </form>
    </section>
</x-layouts.app>
