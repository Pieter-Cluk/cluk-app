@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur($c))
<section class="card card-flush">
    <div class="bill-head"><b>{{ __('workspace.bill.period', ['from' => $bill['start']->format('d/m/Y'), 'to' => $bill['end']->format('d/m/Y')]) }}</b></div>
    @if ($bill['lines'])
        <div class="table-wrap">
            <table class="table">
                <tbody>
                    @foreach ($bill['lines'] as $l)
                        <tr><td>{{ $l['text'] }}</td><td class="num nowrap">{{ $eur($l['amount_cents']) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>{{ __('workspace.bill.total') }}</th><th class="num nowrap">{{ $eur($bill['total_cents']) }}</th></tr></tfoot>
            </table>
        </div>
    @else
        <p class="muted pad">{{ __('workspace.bill.empty') }}</p>
    @endif
</section>
