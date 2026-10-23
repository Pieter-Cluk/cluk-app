@php($editing = $plan->exists)
@php($chosen = old('accounts', $plan->targets->pluck('account_id')->all()))
@php($own = $plan->targets->pluck('body', 'account_id'))
@php($localWhen = $plan->scheduled_at?->timezone('Europe/Brussels')->format('Y-m-d\TH:i'))
<x-layouts.app :title="$editing ? $plan->title : __('social.new_post')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('social.plans') }}">{{ mb_strtoupper(__('social.title')) }} / {{ mb_strtoupper(__('social.posts')) }}</a></div>
            <h1>{{ $editing ? $plan->title : __('social.new_post') }}</h1>
            <p class="muted">{{ __('social.plans.form_intro') }}</p>
        </div>
        @if ($editing)@include('social._plan-status', ['p' => $plan])@endif
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif

    <form class="split" method="post" action="{{ $editing ? route('social.plans.update', $plan) : route('social.plans.store') }}" enctype="multipart/form-data">
        @csrf @if ($editing) @method('PUT') @endif
        <section class="card stack-sm">
            <label class="field"><span>{{ __('social.plans.fields.title') }}</span><input name="title" required maxlength="160" value="{{ old('title', $plan->title) }}" placeholder="{{ __('social.plans.fields.title_hint') }}"></label>
            <label class="field"><span>{{ __('social.plans.fields.body') }}</span><textarea name="body" rows="8" required maxlength="63000" data-count>{{ old('body', $plan->body) }}</textarea>
                <small class="muted"><span data-count-out>0</span> {{ __('social.plans.chars') }} · {{ __('social.plans.limits') }}</small></label>
            <label class="field"><span>{{ __('social.plans.fields.link') }}</span><input type="url" name="link_url" maxlength="500" value="{{ old('link_url', $plan->link_url) }}" placeholder="https://cluk.live/…">
                <small class="muted">{{ __('social.plans.link_hint') }}</small></label>

            <div class="field"><span>{{ __('social.plans.fields.media') }}</span>
                @if ($plan->media)
                    <div class="sm-media">
                        @foreach ($plan->media as $i => $m)
                            <label class="sm-media-item">
                                @if ($m['type'] === 'image')<img src="{{ \App\Services\Social\Media::url($plan, $m) }}" alt="">@else<span class="sm-video">▶ {{ $m['name'] }}</span>@endif
                                <span class="check"><input type="checkbox" name="remove[]" value="{{ $i }}"> {{ __('social.plans.remove') }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime">
                <small class="muted">{{ __('social.plans.media_hint') }}</small></div>

            <div class="field"><span>{{ __('social.plans.fields.accounts') }}</span>
                @forelse ($accounts as $a)
                    <div class="sm-target">
                        <label class="check"><input type="checkbox" name="accounts[]" value="{{ $a->id }}" @checked(in_array($a->id, $chosen)) @disabled(! $a->canPublish())>
                            <b>{{ $a->label() }}</b>
                            <small class="muted">· max {{ number_format(config('social.platforms.'.$a->platform.'.max'), 0, ',', '.') }}@if (config('social.platforms.'.$a->platform.'.media')) · {{ __('social.plans.needs_media') }}@endif
                                @unless ($a->canPublish()) · {{ __('social.plans.read_only') }}@endunless</small></label>
                        @if ($a->canPublish())
                            <details @if (old('own.'.$a->id, $own[$a->id] ?? null)) open @endif><summary class="small">{{ __('social.plans.own_text') }}</summary>
                                <textarea name="own[{{ $a->id }}]" rows="4" maxlength="63000" placeholder="{{ __('social.plans.own_hint') }}">{{ old('own.'.$a->id, $own[$a->id] ?? '') }}</textarea></details>
                        @endif
                    </div>
                @empty
                    <p class="muted">{{ __('social.plans.no_accounts') }} <a href="{{ route('social.index') }}#accounts">{{ __('social.accounts.title') }} →</a></p>
                @endforelse
            </div>
        </section>

        <aside class="stack">
            <section class="card stack-sm">
                <h2>{{ __('social.plans.when_title') }}</h2>
                <button class="btn" type="submit" name="action" value="draft">{{ __('social.plans.save_draft') }}</button>
                @if ($canPublish)
                    <label class="field"><span>{{ __('social.plans.fields.when') }}</span><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $localWhen) }}" min="{{ now('Europe/Brussels')->format('Y-m-d\TH:i') }}"></label>
                    <button class="primary" type="submit" name="action" value="schedule">{{ __('social.plans.schedule') }}</button>
                    <button class="btn" type="submit" name="action" value="now" onclick="return confirm(@js(__('social.plans.confirm_now')))">{{ __('social.plans.now') }}</button>
                    <small class="muted">{{ __('social.plans.when_hint') }}</small>
                @else
                    <small class="muted">{{ __('social.plans.ask_publisher') }}</small>
                @endif
            </section>
            <section class="card stack-sm">
                <h2>{{ __('social.plans.tips_title') }}</h2>
                <ul class="small muted">@foreach (__('social.plans.tips') as $tip)<li>{{ $tip }}</li>@endforeach</ul>
            </section>
        </aside>
    </form>

    @if ($editing && $plan->status === 'draft')
        <details class="card mt"><summary>{{ __('social.plans.delete') }}</summary>
            <form method="post" action="{{ route('social.plans.destroy', $plan) }}" class="mt">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">{{ __('social.plans.delete_go') }}</button></form></details>
    @elseif ($editing && $plan->status === 'scheduled' && $canPublish)
        <form method="post" action="{{ route('social.plans.unschedule', $plan) }}" class="mt">@csrf<button class="btn" type="submit">{{ __('social.plans.unschedule') }}</button></form>
    @endif
    <script>
        document.querySelectorAll('[data-count]').forEach(function (t) {
            var out = t.parentElement.querySelector('[data-count-out]');
            var upd = function () { out.textContent = t.value.length; };
            t.addEventListener('input', upd); upd();
        });
    </script>
</x-layouts.app>
