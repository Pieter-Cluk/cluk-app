@php($fmt = fn ($d) => $d?->timezone('Europe/Brussels')->format('d/m/Y H:i'))
@php($canPublish = auth()->user()->hasRight('social.publish'))
<x-layouts.app :title="$plan->title">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('social.plans') }}">{{ mb_strtoupper(__('social.title')) }} / {{ mb_strtoupper(__('social.posts')) }}</a></div>
            <h1>{{ $plan->title }}</h1>
            <p class="muted">{{ __('social.plans.made_by', ['name' => $plan->createdBy?->name ?? '—', 'date' => $fmt($plan->created_at)]) }}@if ($plan->scheduled_at) · {{ __('social.plans.planned_for', ['when' => $fmt($plan->scheduled_at)]) }}@endif</p>
        </div>
        <div class="inline-form">
            @include('social._plan-status', ['p' => $plan])
            @if ($plan->editable())<a class="btn" href="{{ route('social.plans.edit', $plan) }}">{{ __('social.plans.edit') }}</a>@endif
        </div>
    </div>
    @include('partials.flash')

    <div class="split">
        <section class="card card-flush">
            <table class="table">
                <thead><tr><th>{{ __('social.plans.cols.account') }}</th><th>{{ __('social.plans.cols.status') }}</th><th>{{ __('social.plans.cols.result') }}</th></tr></thead>
                <tbody>
                    @foreach ($plan->targets as $t)
                        <tr>
                            <td><b>{{ $t->account->label() }}</b>@if ($t->body)<br><small class="muted">{{ __('social.plans.own_text') }}</small>@endif</td>
                            <td><span @class(['pill', 'pill-ok' => $t->status === 'published', 'pill-warn' => $t->status === 'failed'])>{{ __('social.plans.target.'.$t->status) }}</span></td>
                            <td class="small">
                                @if ($t->permalink)<a href="{{ $t->permalink }}" target="_blank" rel="noopener">{{ __('social.open') }} ↗</a> <span class="muted">{{ $fmt($t->published_at) }}</span>@endif
                                @if ($t->error)<span class="txt-danger">{{ \Illuminate\Support\Str::limit($t->error, 200) }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <aside class="stack">
            <section class="card stack-sm">
                <h2>{{ __('social.plans.fields.body') }}</h2>
                @foreach ($plan->images() as $m)<img class="sm-preview" src="{{ \App\Services\Social\Media::url($plan, $m) }}" alt="">@endforeach
                @if ($v = $plan->video())<p class="sm-video">▶ {{ $v['name'] }}</p>@endif
                <p class="prewrap">{{ $plan->body }}</p>
                @if ($plan->link_url)<p class="small"><a href="{{ $plan->link_url }}" target="_blank" rel="noopener">{{ $plan->link_url }}</a></p>@endif
            </section>
            @if ($canPublish && in_array($plan->status, ['partly', 'failed'], true))
                <form method="post" action="{{ route('social.plans.retry', $plan) }}">@csrf<button class="primary" type="submit">{{ __('social.plans.retry') }}</button></form>
            @endif
            @if ($canPublish && $plan->status === 'scheduled')
                <form method="post" action="{{ route('social.plans.unschedule', $plan) }}">@csrf<button class="btn" type="submit">{{ __('social.plans.unschedule') }}</button></form>
            @endif
        </aside>
    </div>
</x-layouts.app>
