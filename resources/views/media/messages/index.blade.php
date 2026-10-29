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
        @foreach (\App\Models\Media\Message::KINDS as $k)<a @class(['tab', 'tab-on' => $kind === $k]) href="{{ route('messages.index', ['kind' => $k]) }}">{{ __('messages.kinds.'.$k) }} <small class="muted">{{ $counts[$k] }}</small></a>@endforeach<a class="tab" href="{{ route('messages.entrance') }}">{{ __('entrance.tab') }}</a>
    </nav>
    <p class="muted mt">{{ __('messages.hints.'.$kind) }}@if ($audience !== null) <b>{{ trans_choice('messages.audience', $audience, ['n' => $audience]) }}</b>@endif</p>
    @if (in_array($kind, ['birthday', 'banner'], true))
        <p @class(['flash', 'flash-ok' => $inUse, 'flash-err' => ! $inUse])>{{ $inUse ? __('messages.in_use', ['title' => $inUse->title]) : __('messages.none_in_use.'.$kind) }}</p>
    @endif
    @if ($kind === 'mail_banner')
        @if ($live->isEmpty())
            <p class="flash flash-err">{{ __('messages.none_in_use.mail_banner') }}</p>
        @else
            <div class="flash flash-ok"><b>{{ trans_choice('messages.rotation_now', $live->count(), ['n' => $live->count()]) }}</b>
                <ol class="rotation">@foreach ($live as $i => $b)<li>{{ __('messages.rotation_turn', ['n' => $i + 1]) }}: <b>{{ $b->title }}</b></li>@endforeach</ol>
                @if ($live->count() > 1)<small>{{ __('messages.rotation_then', ['n' => $live->count() + 1]) }}</small>@endif
            </div>
        @endif
    @endif
    <section class="card card-flush mt">
        <table class="table">
            <thead><tr><th>{{ __('messages.cols.title') }}</th><th>{{ __('messages.cols.status') }}</th><th>{{ __('messages.cols.when') }}</th><th class="num">{{ __($kind === 'mail_banner' ? 'messages.cols.shown' : 'messages.cols.people') }}</th></tr></thead>
            <tbody>
                @forelse ($messages as $m)
                    <tr class="db-row">
                        <td>@if ($m->image_path)<img class="msg-thumb" src="{{ URL::signedRoute('messages.public.image', $m) }}" alt="">@endif<a class="db-row-link" href="{{ route('messages.edit', $m) }}"><b>{{ $m->title }}</b></a><br><small class="muted">{{ \Illuminate\Support\Str::limit($m->subject['en'] ?? '', 80) }}</small></td>
                        <td>@include('media.messages._status', ['m' => $m])</td>
                        <td class="nowrap">@if ($m->status === 'sent'){{ $fmt($m->sent_at) }}@elseif ($m->status === 'scheduled'){{ $fmt($m->send_at) }}@elseif ($m->active_from || $m->active_until){{ $m->active_from?->format('d/m') ?? '…' }} – {{ $m->active_until?->format('d/m/Y') ?? '…' }}@else{{ $m->updated_at->format('d/m/Y') }}@endif</td>
                        <td class="num">@if (in_array($m->kind, \App\Models\Media\Message::MAILED, true) || $m->kind === 'mail_banner'){{ $m->sent_count }}@else—@endif
                            @if ($kind === 'mail_banner' && $canEdit)<span class="move-btns">@foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow)<form method="post" action="{{ route('messages.move', [$m, $dir]) }}" class="inline">@csrf<button class="btn btn-sm" type="submit" aria-label="{{ __('messages.move_'.$dir) }}" @disabled(($dir === 'up' && $loop->parent->first) || ($dir === 'down' && $loop->parent->last))>{{ $arrow }}</button></form>@endforeach</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('messages.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <x-pager :items="$messages"/>
</x-layouts.app>
