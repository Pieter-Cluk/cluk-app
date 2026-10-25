<x-layouts.app :title="__('calls.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('calls.kicker')) }}</div>
            <h1>@if ($raw !== ''){{ __('calls.from', ['number' => $shown]) }}@else{{ __('calls.title') }}@endif</h1>
            @if ($name !== '')<p class="muted">{{ __('calls.name_shown', ['name' => $name]) }}</p>@endif
        </div>
        @if ($key)<div class="head-actions"><a class="btn" href="tel:+{{ $key }}">{{ __('calls.call_back') }}</a><a class="btn" href="https://wa.me/{{ $key }}" target="_blank" rel="noopener">WhatsApp</a></div>@endif
    </div>
    @include('partials.flash')

    @if ($raw === '')
        <section class="card stack-sm">
            <p>{{ __('calls.no_number') }}</p>
            <h2>{{ __('calls.setup_title') }}</h2>
            <p class="muted">{{ __('calls.setup_text') }}</p>
            <pre class="code-line">{{ url('/call') }}?number=@{{.CallerNumber}}&amp;name=@{{.CallerDisplayName}}</pre>
        </section>
    @elseif ($contacts->isEmpty() && $companies->isEmpty())
        <section class="card stack-sm">
            <h2>{{ __('calls.unknown') }}</h2>
            <p class="muted">{{ $key ? __('calls.unknown_hint') : __('calls.too_short') }}</p>
            @if ($key)
                <div class="form-actions">
                    <a class="primary" href="{{ route('companies.create', ['phone' => $shown, 'name' => $name]) }}">+ {{ __('calls.new_company') }}</a>
                    <a class="btn" href="{{ route('companies.index', ['q' => $name]) }}">{{ __('calls.add_to_company') }}</a>
                </div>
                <small class="muted">{{ __('calls.add_to_company_hint') }}</small>
            @endif
        </section>
    @else
        <section class="card card-flush">
            <table class="table">
                <thead><tr><th>{{ __('calls.cols.who') }}</th><th>{{ __('calls.cols.company') }}</th><th>{{ __('calls.cols.number') }}</th></tr></thead>
                <tbody>
                    @foreach ($contacts as $c)
                        @php($at = $c->companies->sortByDesc('pivot.is_main')->first())
                        <tr class="db-row">
                            <td><x-avatar :who="$c"/> <a class="db-row-link" href="{{ $at ? route('companies.show', $at).'#contact-'.$c->id : route('companies.index', ['view' => 'contacts', 'q' => $c->email ?: $c->name]) }}"><b>{{ $c->name }}</b></a>@if ($at?->pivot?->job_title)<br><small class="muted">{{ $at->pivot->job_title }}</small>@endif</td>
                            <td>{{ $c->companies->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td class="nowrap">{{ $c->mobile_key === $key ? $c->mobile : $c->phone }}</td>
                        </tr>
                    @endforeach
                    @foreach ($companies as $co)
                        <tr class="db-row">
                            <td><a class="db-row-link" href="{{ route('companies.show', $co) }}"><b>{{ $co->name }}</b></a><br><small class="muted">{{ __('calls.company_line') }}</small></td>
                            <td>{{ $co->city }}</td>
                            <td class="nowrap">{{ $co->phone }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</x-layouts.app>
