<x-layouts.app :title="__('chats.title')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.items.sales')) }}</div>
            <h1>{{ __('chats.title') }}</h1>
            <p class="muted">{{ __('chats.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    @include('sales._tabs', ['section' => 'chats'])
    <nav class="chips mt">
        @foreach (\App\Http\Controllers\Sales\ChatController::TABS as $t)<a @class(['chip', 'chip-on' => $tab === $t]) href="{{ route('chats.index', ['tab' => $t]) }}">{{ __('chats.tabs.'.$t) }} ({{ $counts[$t] }})</a>@endforeach
    </nav>
    <section class="card card-flush mt">
        <ul class="inbox">
            @forelse ($chats as $c)
                @php($needs = \App\Services\Website\TeamChats::needsAnswer($c))
                <li><a class="inbox-item" href="{{ route('chats.show', $c) }}">
                    <span @class(['pill', 'pill-warn' => $needs, 'pill-info' => ! $needs && $c->status === 'staff', 'pill-off' => in_array($c->status, ['closed', 'bot'], true)])>{{ $needs ? __('chats.needs_answer') : __('chats.statuses.'.$c->status) }}</span>
                    <span class="inbox-main"><b>{{ $c->name ?: ($c->email ?: __('chats.visitor')) }}</b><small class="muted">{{ strtoupper($c->locale) }}@if ($c->email) · {{ $c->email }}@endif @if ($c->page) · {{ \Illuminate\Support\Str::limit($c->page, 60) }}@endif · {{ trans_choice('chats.lines', $c->messages_count, ['n' => $c->messages_count]) }}</small></span>
                    <span class="inbox-side"><small class="muted nowrap">{{ $c->last_message_at?->locale(app()->getLocale())->diffForHumans(short: true) }}</small></span>
                </a></li>
            @empty
                <li class="muted inbox-empty">{{ __('chats.empty') }}</li>
            @endforelse
        </ul>
        <x-pager :items="$chats"/>
    </section>
</x-layouts.app>
