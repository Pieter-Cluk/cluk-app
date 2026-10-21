@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
<x-layouts.app :title="__('nav.items.purchasing')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.commercial')) }} / {{ mb_strtoupper(__('nav.items.purchasing')) }}</div>
            <h1>{{ __('nav.items.purchasing') }}</h1>
            <p class="muted">{{ __('purchasing.intro') }}</p>
        </div>
        @if ($canWork)<a class="primary" href="{{ route('purchasing.create') }}">{{ __('purchasing.new') }}</a>@endif
    </div>
    @include('partials.flash')
    <div class="tiles">
        <a class="tile tile-subhire" href="{{ route('purchasing.index', ['tab' => 'pq', 'f' => 'reply']) }}"><span class="tile-k">{{ mb_strtoupper(__('purchasing.tiles.reply')) }}</span><b class="tile-v">{{ $tiles['reply'] }}</b><small class="muted">{{ __('purchasing.tiles.reply_s') }}</small></a>
        <a class="tile tile-waiting" href="{{ route('purchasing.index', ['tab' => 'pq', 'f' => 'wait']) }}"><span class="tile-k">{{ mb_strtoupper(__('purchasing.tiles.wait')) }}</span><b class="tile-v">{{ $tiles['wait'] }}</b><small class="muted">{{ trans_choice('purchasing.tiles.wait_s', $tiles['late'], ['n' => $tiles['late']]) }}</small></a>
        <a class="tile" href="{{ route('purchasing.index', ['tab' => 'po', 'f' => 'run']) }}"><span class="tile-k">{{ mb_strtoupper(__('purchasing.tiles.run')) }}</span><b class="tile-v">{{ $tiles['run'] }}</b><small class="muted">{{ trans_choice('purchasing.tiles.run_s', $tiles['po_late'], ['n' => $tiles['po_late']]) }}</small></a>
        <a class="tile tile-subhire" href="{{ route('purchasing.index', ['tab' => 'po', 'f' => 'invoice']) }}"><span class="tile-k">{{ mb_strtoupper(__('purchasing.tiles.invoice')) }}</span><b class="tile-v">{{ $tiles['invoice'] }}</b><small class="muted">{{ __('purchasing.tiles.invoice_s') }}</small></a>
    </div>
    <nav class="tabs">
        <a @class(['tab', 'tab-on' => $tab === 'pq']) href="{{ route('purchasing.index', ['tab' => 'pq']) }}">{{ __('purchasing.tab_pq', ['n' => $counts['pq']]) }}</a>
        <a @class(['tab', 'tab-on' => $tab === 'po']) href="{{ route('purchasing.index', ['tab' => 'po']) }}">{{ __('purchasing.tab_po', ['n' => $counts['po']]) }}</a>
    </nav>
    <nav class="chips mt">
        @foreach ($tab === 'pq' ? ['', 'reply', 'wait', 'closed'] : ['', 'run', 'invoice', 'closed'] as $f)<a @class(['chip', 'chip-on' => $filt === $f]) href="{{ route('purchasing.index', array_filter(['tab' => $tab, 'f' => $f])) }}">{{ __('purchasing.filters.'.($f ?: 'all')) }}</a>@endforeach
    </nav>
    <section class="card card-flush mt">
        <table class="table">
            <thead><tr><th>{{ __('purchasing.cols.number') }}</th><th>{{ __('purchasing.cols.supplier') }}</th><th>{{ __('purchasing.cols.for') }}</th><th>{{ __('purchasing.cols.items') }}</th><th>{{ __('purchasing.cols.delivery') }}</th><th class="num">{{ __('purchasing.cols.amount') }}</th><th>{{ __('purchasing.cols.status') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    @if ($tab === 'pq')
                        @php($st = $pqState($r))
                        <tr><td><a href="{{ route('purchasing.pq', $r) }}"><b>{{ $r->number }}</b></a><br><small class="muted">{{ $r->sent_at ? __('purchasing.sent_on', ['date' => $r->sent_at->format('d/m')]) : __('purchasing.pq_status.draft') }}</small></td>
                            <td>{{ trans_choice('purchasing.suppliers_n', $r->suppliers->count(), ['n' => $r->suppliers->count()]) }}<br><small class="muted">{{ __('purchasing.replied', ['n' => $r->repliedCount(), 'of' => $r->suppliers->count()]) }}</small></td>
                            <td>{{ $r->salesQuote?->customerName() ?? __('purchasing.stock') }}<br><small class="muted">{{ $r->salesQuote?->number ?? '' }}</small></td>
                            <td><small>{{ $r->lines->map(fn ($l) => $l->qty.' × '.$l->name)->implode(' · ') }}</small></td>
                            <td><small>{{ $r->needed_by ? __('purchasing.needed', ['date' => $r->needed_by->format('d/m')]) : '—' }}</small></td>
                            <td class="num">—</td>
                            <td><span @class(['pill', 'pill-warn' => $st === 'reply', 'pill-info' => $st === 'wait', 'pill-off' => $st === 'closed', 'pill-danger' => $r->isLate()])>{{ $r->isLate() ? __('purchasing.late') : __('purchasing.pq_states.'.$st) }}</span></td></tr>
                    @else
                        @php($st = $poState($r))
                        <tr><td><a href="{{ route('purchasing.po', $r) }}"><b>{{ $r->number }}</b></a><br><small class="muted">{{ $r->sent_at ? __('purchasing.sent_on', ['date' => $r->sent_at->format('d/m')]) : '' }}</small></td>
                            <td>{{ $r->supplier->name }}<br><small class="muted">{{ $r->their_ref ? __('purchasing.their_ref', ['ref' => $r->their_ref]) : '' }}</small></td>
                            <td>{{ $r->request?->salesQuote?->customerName() ?? __('purchasing.stock') }}</td>
                            <td><small>{{ $r->lines->map(fn ($l) => $l->qty.' × '.$l->name)->implode(' · ') }}</small></td>
                            <td><small>{{ $r->received_at ? __('purchasing.received_on', ['date' => $r->received_at->format('d/m')]) : ($r->dueDate() ? __('purchasing.due', ['date' => $r->dueDate()->format('d/m')]) : '—') }}</small></td>
                            <td class="num nowrap">{{ $eur($r->total_cents) }}@if ($r->invoice_cents !== null && $r->invoice_cents !== $r->total_cents)<br><small class="txt-danger">{{ __('purchasing.invoice_diff', ['amount' => $eur($r->invoice_cents)]) }}</small>@endif</td>
                            <td><span @class(['pill', 'pill-ok' => $r->status === 'confirmed', 'pill-warn' => $r->status === 'sent', 'pill-danger' => $st === 'invoice' || $r->isLate(), 'pill-off' => $st === 'closed'])>{{ $st === 'invoice' ? __('purchasing.po_states.invoice') : ($r->isLate() ? __('purchasing.late') : __('purchasing.po_status.'.$r->status)) }}</span></td></tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="muted">{{ __('purchasing.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <p class="muted small">{{ __('purchasing.rule') }}</p>
</x-layouts.app>
