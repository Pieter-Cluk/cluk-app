<x-layouts.app :title="__('nav.items.checkbuilder')">
    <div class="pagehead"><div>
        <div class="kicker">{{ mb_strtoupper(__('nav.groups.settings')) }} / {{ mb_strtoupper(__('nav.items.checkbuilder')) }}</div>
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
                        <td><a href="{{ route('checks.edit', $p) }}"><b>{{ $p->item?->name ?? $p->name() }}</b></a>@if ($p->item) <small class="muted">{{ $p->item->number }}</small>@endif @unless ($p->active) <span class="pill">{{ __('checks.builder.off') }}</span>@endunless</td>
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
        <p class="muted small">{{ __('checks.builder.new_hint') }}</p>
        <form method="post" action="{{ route('checks.store') }}" class="inline-form">@csrf
            <select data-pick name="item_id" required aria-label="{{ __('checks.builder.dryhire_item') }}"><option value="">{{ __('checks.builder.dryhire_item') }}</option>@foreach ($dryHire as $it)<option value="{{ $it->id }}">{{ $it->name }} · {{ $it->number }}</option>@endforeach</select>
            <button class="primary" type="submit">{{ __('checks.builder.create') }}</button>
        </form>
    </section>
</x-layouts.app>
