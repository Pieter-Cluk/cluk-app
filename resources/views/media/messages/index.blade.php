@php($fmt = fn ($d) => $d?->format('d/m/Y H:i'))
<x-layouts.app :title="__('nav.items.messages')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.media')) }} / {{ mb_strtoupper(__('nav.items.messages')) }}</div>
            <h1>{{ __('nav.items.messages') }}</h1>
            <p class="muted">{{ __('messages.intro') }}</p>
        </div>
        @if ($canEdit)<a class="primary" href="{{ route('messages.create', ['kind' => $kind]) }}">+ {{ __('messages.new.'.$kind) }}</a>@endif
    </div>
    @include('partials.flash')
    <nav class="tabs">
        @foreach (\App\Models\Media\Message::KINDS as $k)<a @class(['tab', 'tab-on' => $kind === $k]) href="{{ route('messages.index', ['kind' => $k]) }}">{{ __('messages.kinds.'.$k) }} <small class="muted">{{ $counts[$k] }}</small></a>@endforeach
    </nav>
    <p class="muted mt">{{ __('messages.hints.'.$kind) }}@if ($audience !== null) <b>{{ trans_choice('messages.audience', $audience, ['n' => $audience]) }}</b>@endif</p>
    @if (in_array($kind, ['birthday', 'banner'], true))
        <p @class(['flash', 'flash-ok' => $inUse, 'flash-err' => ! $inUse])>{{ $inUse ? __('messages.in_use', ['title' => $inUse->title]) : __('messages.none_in_use.'.$kind) }}</p>
    @endif
    <section class="card card-flush mt">
        <table class="table">
            <thead><tr><th>{{ __('messages.cols.title') }}</th><th>{{ __('messages.cols.status') }}</th><th>{{ __('messages.cols.when') }}</th><th class="num">{{ __('messages.cols.people') }}</th></tr></thead>
            <tbody>
                @forelse ($messages as $m)
                    <tr class="db-row">
                        <td>@if ($m->image_path)<img class="msg-thumb" src="{{ URL::signedRoute('messages.public.image', $m) }}" alt="">@endif<a class="db-row-link" href="{{ route('messages.edit', $m) }}"><b>{{ $m->title }}</b></a><br><small class="muted">{{ \Illuminate\Support\Str::limit($m->subject['en'] ?? '', 80) }}</small></td>
                        <td>@include('media.messages._status', ['m' => $m])</td>
                        <td class="nowrap">@if ($m->status === 'sent'){{ $fmt($m->sent_at) }}@elseif ($m->status === 'scheduled'){{ $fmt($m->send_at) }}@elseif ($m->active_from || $m->active_until){{ $m->active_from?->format('d/m') ?? '…' }} – {{ $m->active_until?->format('d/m/Y') ?? '…' }}@else{{ $m->updated_at->format('d/m/Y') }}@endif</td>
                        <td class="num">{{ in_array($m->kind, \App\Models\Media\Message::MAILED, true) ? $m->sent_count : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('messages.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <x-pager :items="$messages"/>
</x-layouts.app>
