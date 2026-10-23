@php($when = fn ($p) => $p->scheduled_at?->timezone('Europe/Brussels')->format('d/m/Y H:i'))
<x-layouts.app :title="__('social.posts')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('social.index') }}">{{ mb_strtoupper(__('nav.groups.media')) }} / {{ mb_strtoupper(__('social.title')) }}</a></div>
            <h1>{{ __('social.posts') }}</h1>
            <p class="muted">{{ __('social.plans.intro') }}</p>
        </div>
        @if ($canPost)<a class="primary" href="{{ route('social.plans.create') }}">+ {{ __('social.new_post') }}</a>@endif
    </div>
    @include('partials.flash')
    <nav class="tabs">
        @foreach (['planned', 'drafts', 'published', 'site'] as $t)
            <a @class(['tab', 'tab-on' => $tab === $t]) href="{{ route('social.plans', ['s' => $t]) }}">{{ __('social.plans.tabs.'.$t) }}@isset($counts[$t]) <small class="muted">{{ $counts[$t] }}</small>@endisset</a>
        @endforeach
    </nav>
    @if ($tab === 'site')
        <p class="muted mt">{{ __('social.plans.site_intro') }}</p>
        <section class="card card-flush mt">
            <table class="table">
                <thead><tr><th>{{ __('social.plans.cols.post') }}</th><th>{{ __('social.plans.cols.when') }}</th><th>{{ __('social.plans.site_col') }}</th></tr></thead>
                <tbody>
                    @forelse ($posts as $post)
                        <tr @class(['is-off' => ! $post->on_website])>
                            <td><span class="sm-site-row">
                                @if ($post->thumb_path || $post->thumb_url)<img class="sm-site-thumb" src="{{ $post->thumb_url }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif
                                <span><a href="{{ $post->permalink }}" target="_blank" rel="noopener"><b>{{ \Illuminate\Support\Str::limit($post->text ?: '—', 110) }}</b></a><br>
                                <small class="muted">{{ __('social.platforms.'.$post->account->platform) }}@if ($post->video_url || $post->video_path) · ▶ {{ __('social.plans.video') }}@endif</small></span></span></td>
                            <td class="nowrap">{{ $post->published_at?->timezone('Europe/Brussels')->format('d/m/Y') }}</td>
                            <td>@if ($canPost)<form method="post" action="{{ route('social.posts.website', $post) }}">@csrf<input type="hidden" name="on" value="{{ $post->on_website ? 0 : 1 }}">
                                <label class="check"><input type="checkbox" @checked($post->on_website) onchange="this.form.submit()"><span>{{ __('social.plans.site_show') }}</span></label></form>
                                @else{{ $post->on_website ? '✓' : '—' }}@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">{{ __('social.no_posts') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <x-pager :items="$posts"/>
    @else
    <section class="card card-flush mt">
        <table class="table">
            <thead><tr><th>{{ __('social.plans.cols.post') }}</th><th>{{ __('social.plans.cols.accounts') }}</th><th>{{ __('social.plans.cols.status') }}</th><th>{{ __('social.plans.cols.when') }}</th></tr></thead>
            <tbody>
                @forelse ($plans as $p)
                    <tr class="db-row">
                        <td><a class="db-row-link" href="{{ $p->status === 'draft' && $canPost ? route('social.plans.edit', $p) : route('social.plans.show', $p) }}"><b>{{ $p->title }}</b></a><br><small class="muted">{{ \Illuminate\Support\Str::limit($p->body, 90) }}</small></td>
                        <td class="small">{{ $p->targets->map(fn ($t) => __('social.platforms.'.$t->account->platform))->unique()->implode(', ') ?: '—' }}</td>
                        <td>@include('social._plan-status', ['p' => $p])</td>
                        <td class="nowrap">{{ $when($p) ?? $p->updated_at->format('d/m/Y') }}<br><small class="muted">{{ $p->createdBy?->name }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('social.plans.empty.'.$tab) }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <x-pager :items="$plans"/>
    @endif
</x-layouts.app>
