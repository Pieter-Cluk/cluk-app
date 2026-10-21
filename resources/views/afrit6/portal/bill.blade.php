@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
<x-layouts.portal :title="__('workspace.bill.title')" tab="bill">
    <div class="pagehead">
        <div>
            <h1>{{ __('workspace.bill.title') }}</h1>
            <p class="muted">{{ __('workspace.bill.intro') }}</p>
        </div>
    </div>
    @include('afrit6._bill', ['bill' => $bill])

    @if ($statements->isNotEmpty())
        <section class="card">
            <h2>{{ __('workspace.bill.saved') }}</h2>
            @foreach ($statements as $s)
                <details class="svc">
                    <summary><b>{{ __('workspace.bill.period', ['from' => $s->period_start->format('d/m/Y'), 'to' => $s->period_end->format('d/m/Y')]) }}</b><span>{{ $eur($s->total_cents) }}</span></summary>
                    <table class="table table-tight">
                        <tbody>@foreach ($s->lines as $l)<tr><td>{{ $l['text'] }}</td><td class="num">{{ $eur($l['amount_cents']) }}</td></tr>@endforeach</tbody>
                    </table>
                </details>
            @endforeach
        </section>
    @endif
</x-layouts.portal>
