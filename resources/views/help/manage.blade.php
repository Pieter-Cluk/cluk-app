<x-layouts.app :title="__('help.manage')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('help.index') }}">{{ mb_strtoupper(__('nav.items.help')) }}</a></div>
            <h1>{{ __('help.manage') }}</h1>
            <p class="muted">{{ __('help.manage_intro') }}</p>
        </div>
        <div class="inline-form"><a class="primary" href="{{ route('help.create') }}">{{ __('help.new_article') }}</a></div>
    </div>
    @include('partials.flash')
    @if ($errors->any())<div class="flash flash-err">{{ $errors->first() }}</div>@endif

    <section class="card">
        <h2>{{ __('help.questions') }}</h2>
        @forelse ($questions as $q)
            <div @class(['help-q', 'is-off' => $q->status !== 'open'])>
                <div><b>{{ $q->user?->name }}</b> <small class="muted">· {{ $q->created_at->format('d/m/Y H:i') }} · {{ __('help.statuses.'.$q->status) }}</small>
                    <p>{!! nl2br(e($q->question)) !!}</p>
                    @if ($q->page)<small class="muted">{{ __('help.page') }}: {{ $q->page }}</small>@endif
                </div>
                @if ($q->status === 'open')
                    <form method="post" action="{{ route('help.answer', $q) }}" class="mt">@csrf
                        <label class="field"><span>{{ __('help.answer') }}</span><textarea name="answer" rows="3" required maxlength="5000"></textarea></label>
                        <label class="field"><span>{{ __('help.link_article') }}</span><select name="article_id"><option value="">—</option>@foreach ($articles as $a)<option value="{{ $a->id }}">{{ $a->t('title') }}</option>@endforeach</select></label>
                        <div class="inline-form"><button class="primary" type="submit">{{ __('help.send_answer') }}</button></div>
                    </form>
                    <form method="post" action="{{ route('help.close', $q) }}" class="inline-form">@csrf<button class="linkbtn" type="submit">{{ __('help.close') }}</button></form>
                @else
                    @if ($q->answer)<p class="help-answer">{!! nl2br(e($q->answer)) !!}<br><small class="muted">{{ $q->answeredBy?->name }} · {{ $q->answered_at?->format('d/m/Y H:i') }}</small></p>@endif
                    @if (! $q->article_id && $q->answer)<a class="btn" href="{{ route('help.create', ['from' => $q->id]) }}">{{ __('help.make_article') }}</a>@endif
                @endif
            </div>
        @empty
            <p class="muted">{{ __('help.no_questions') }}</p>
        @endforelse
    </section>

    <section class="card mt">
        <h2>{{ __('help.articles') }}</h2>
        <div class="table-wrap"><table class="table table-tight">
            <thead><tr><th>{{ __('help.fields.title') }}</th><th>{{ __('help.fields.topic') }}</th><th>{{ __('help.fields.languages') }}</th><th class="num">{{ __('help.views') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($articles as $a)
                    <tr @class(['is-off' => ! $a->active])><td><a href="{{ route('help.show', $a) }}">{{ $a->t('title') }}</a>@if (! $a->active) <span class="pill pill-off">{{ __('help.hidden_pill') }}</span>@endif</td><td>{{ __('help.topics.'.$a->topic) }}</td>
                        <td>@foreach (array_keys(config('cluk.locales')) as $l)<span @class(['pill', 'pill-ok' => filled($a->title[$l] ?? null) && filled($a->body[$l] ?? null), 'pill-off' => ! filled($a->body[$l] ?? null)])>{{ strtoupper($l) }}</span> @endforeach</td>
                        <td class="num">{{ $a->views }}</td><td class="num"><a href="{{ route('help.edit', $a) }}">{{ __('help.edit') }}</a></td></tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="card mt">
        <h2>{{ __('help.downloads') }}</h2>
        <p class="muted">{{ __('help.files_intro') }}</p>
        <ul class="help-files">
            @foreach ($files as $f)
                <li><a href="{{ route('help.file', $f) }}"><span class="ext">{{ $f->extension() }}</span><span><b>{{ $f->title }}</b><small class="muted">{{ $f->original_name }} · {{ number_format($f->size / 1024, 0, ',', '.') }} KB · {{ $f->uploadedBy?->name }}</small></span></a>
                    <form method="post" action="{{ route('help.file.remove', $f) }}" onsubmit="return confirm(@js(__('help.remove_confirm')))">@csrf @method('delete')<button class="linkbtn" type="submit">{{ __('help.remove') }}</button></form></li>
            @endforeach
        </ul>
        <form method="post" action="{{ route('help.upload') }}" enctype="multipart/form-data" class="inline-form mt">@csrf
            <label class="field"><span>{{ __('help.fields.title') }}</span><input name="title" required maxlength="160"></label>
            <label class="field"><span>{{ __('help.fields.note') }}</span><input name="note" maxlength="255"></label>
            <label class="field"><span>{{ __('help.fields.file') }}</span><input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.zip,.txt,.csv"></label>
            <div><button class="primary" type="submit">{{ __('help.upload') }}</button></div>
        </form>
    </section>
</x-layouts.app>
