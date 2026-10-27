@php($editing = $m->exists)
@php($mailed = in_array($m->kind, \App\Models\Media\Message::MAILED, true))
@php($locked = $editing && ! $m->isEditable())
@php($names = ['en' => 'English', 'nl' => 'Nederlands', 'fr' => 'Français', 'es' => 'Español'])
<x-layouts.app :title="$editing ? $m->title : __('messages.new.'.$m->kind)">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('messages.index', ['kind' => $m->kind]) }}">{{ mb_strtoupper(__('nav.items.messages')) }} · {{ mb_strtoupper(__('messages.kinds.'.$m->kind)) }}</a></div>
            <h1>{{ $editing ? $m->title : __('messages.new.'.$m->kind) }}</h1>
            <p class="muted">{{ __('messages.hints.'.$m->kind) }}</p>
        </div>
        @if ($editing)@include('media.messages._status', ['m' => $m])@endif
    </div>
    @include('partials.flash')
    @error('send')<div class="flash flash-err">{{ $message }}</div>@enderror

    <div class="split">
        <form class="card stack-sm" method="post" action="{{ $editing ? route('messages.update', $m) : route('messages.store') }}" enctype="multipart/form-data">
            @csrf @if ($editing) @method('PUT') @else <input type="hidden" name="kind" value="{{ $m->kind }}"> @endif
            <fieldset @disabled($locked) class="stack-sm plain-fieldset">
                <label class="field"><span>{{ __('messages.fields.title') }}</span><input name="title" required maxlength="160" value="{{ old('title', $m->title) }}" placeholder="{{ __('messages.placeholders.'.$m->kind) }}"></label>

                <div class="field"><span>{{ __('messages.fields.image') }}</span>
                    @if ($m->image_path)<img class="msg-preview" src="{{ URL::signedRoute('messages.public.image', $m) }}" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"> {{ __('messages.remove_image') }}</label>@endif
                    <input type="file" name="image" accept="image/*"><small class="muted">{{ __($m->kind === 'mail_banner' ? 'messages.image_hint_mail_banner' : 'messages.image_hint') }}</small></div>

                @if ($m->kind === 'newsletter')
                    <div class="field"><span>{{ __('messages.fields.pdf') }}</span>
                        @if ($m->pdf_path)<a href="{{ URL::signedRoute('messages.public.pdf', $m) }}" target="_blank" rel="noopener">📄 {{ $m->pdf_name }}</a><label class="check"><input type="checkbox" name="remove_pdf" value="1"> {{ __('messages.remove_pdf') }}</label>@endif
                        <input type="file" name="pdf" accept="application/pdf"><small class="muted">{{ __('messages.pdf_hint') }}</small></div>
                @endif

                <label class="field"><span>{{ __('messages.fields.subject') }} · English</span><input name="subject[en]" maxlength="200" value="{{ old('subject.en', $m->subject['en'] ?? '') }}" @required(! in_array($m->kind, \App\Models\Media\Message::PERIOD, true))></label>
                <label class="field"><span>{{ __('messages.fields.body') }} · English</span><textarea name="body[en]" rows="7" maxlength="10000">{{ old('body.en', $m->body['en'] ?? '') }}</textarea>
                    <small class="muted">{{ $m->kind === 'birthday' ? __('messages.name_hint') : __('messages.body_hint') }}</small></label>

                @if ($m->kind === 'mail_banner')
                    <label class="field"><span>{{ __('messages.fields.link') }}</span><input type="url" name="link_url" maxlength="500" value="{{ old('link_url', $m->link_url) }}" placeholder="https://cluk.live/sales"><small class="muted">{{ __('messages.link_hint') }}</small></label>
                    @error('link_url')<small class="txt-danger">{{ $message }}</small>@enderror
                @endif
                @if (in_array($m->kind, \App\Models\Media\Message::PERIOD, true))
                    <div class="form-grid">
                        <label class="field"><span>{{ __('messages.fields.from') }}</span><input type="date" name="active_from" value="{{ old('active_from', $m->active_from?->format('Y-m-d')) }}"></label>
                        <label class="field"><span>{{ __('messages.fields.until') }}</span><input type="date" name="active_until" value="{{ old('active_until', $m->active_until?->format('Y-m-d')) }}"></label>
                    </div>
                @endif

                @if ($editing)
                    <details @if ($m->translationsOutdated()) open @endif>
                        <summary><b>{{ __('messages.translations') }}</b> @if ($m->translationsOutdated())<span class="pill pill-warn">{{ __('messages.outdated') }}</span>@else<span class="pill pill-ok">{{ __('messages.up_to_date') }}</span>@endif</summary>
                        <p class="muted small">{{ $translatorOn ? __('messages.translations_hint') : __('messages.no_translator') }}</p>
                        @foreach (['nl', 'fr', 'es'] as $l)
                            <label class="field"><span>{{ __('messages.fields.subject') }} · {{ $names[$l] }}</span><input name="subject[{{ $l }}]" maxlength="200" value="{{ old('subject.'.$l, $m->subject[$l] ?? '') }}"></label>
                            <label class="field"><span>{{ __('messages.fields.body') }} · {{ $names[$l] }}</span><textarea name="body[{{ $l }}]" rows="5" maxlength="10000">{{ old('body.'.$l, $m->body[$l] ?? '') }}</textarea></label>
                        @endforeach
                    </details>
                @endif
                <div class="form-actions"><button class="primary" type="submit">{{ $editing ? __('messages.save') : __('messages.save_translate') }}</button></div>
            </fieldset>
        </form>

        @if ($editing)
            <aside class="stack">
                @if (! $locked)
                    <section class="card stack-sm">
                        <h2>{{ __('messages.translate_title') }}</h2>
                        <form method="post" action="{{ route('messages.translate', $m) }}">@csrf<button class="btn" type="submit" @disabled(! $translatorOn)>{{ __('messages.translate_again') }}</button></form>
                    </section>
                @endif
                <section class="card stack-sm">
                    <h2>{{ __('messages.test_title') }}</h2>
                    <form method="post" action="{{ route('messages.test', $m) }}" class="inline-form">@csrf
                        <select name="locale" aria-label="{{ __('messages.language') }}">@foreach ($names as $l => $n)<option value="{{ $l }}" @selected($l === app()->getLocale())>{{ $n }}</option>@endforeach</select>
                        <button class="btn" type="submit">{{ __('messages.test_go') }}</button>
                    </form>
                    <small class="muted">{{ __('messages.test_hint', ['email' => auth()->user()->email]) }}</small>
                </section>

                @if ($m->kind === 'invitation')
                    @php($cname = fn ($c) => $c === '' || $c === null ? __('messages.invite.no_country') : (class_exists('Locale') ? (\Locale::getDisplayRegion('-'.$c, app()->getLocale()) ?: $c) : $c))
                    <section class="card stack-sm">
                        <h2>{{ __('messages.invite.plan_title') }}</h2>
                        @if (in_array($m->status, ['draft', 'scheduled'], true))
                            @php($plan = $m->plan ?? [])
                            @php($people = \App\Services\Media\Messages::recipients($m))
                            @php($startDay = \Illuminate\Support\Carbon::parse($plan['start'] ?? now()->addDay()->toDateString()))
                            @php($preview = \App\Services\Media\Messages::plan($people, $startDay, (int) ($plan['per_day'] ?? 100), $plan['countries'] ?? \App\Services\Media\Messages::FIRST_COUNTRIES))
                            <p>{{ trans_choice('messages.will_go', count($people), ['n' => count($people)]) }} {{ __('messages.invite.who') }}</p>
                            @if ($m->status === 'scheduled')<p class="flash flash-ok">{{ __('messages.invite.planned', ['date' => $m->send_at->format('d/m/Y')]) }}</p>@endif
                            @if ($preview['countries'])
                                <div class="table-wrap"><table class="table">
                                    <thead><tr><th>{{ __('messages.invite.country') }}</th><th class="num">{{ __('messages.invite.people') }}</th><th>{{ __('messages.invite.days') }}</th></tr></thead>
                                    <tbody>@foreach ($preview['countries'] as $c => $row)<tr><td>{{ $cname($c) }}</td><td class="num">{{ $row['n'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($row['from'])->format('d/m') }}@if ($row['until'] !== $row['from']) – {{ \Illuminate\Support\Carbon::parse($row['until'])->format('d/m') }}@endif</td></tr>@endforeach</tbody>
                                </table></div>
                                <small class="muted">{{ trans_choice('messages.invite.last_day', count($preview['days']), ['n' => count($preview['days']), 'date' => \Illuminate\Support\Carbon::parse(end($preview['days'])['date'])->format('d/m/Y')]) }}</small>
                            @endif
                            <form method="post" action="{{ route('messages.plan', $m) }}" class="stack-sm">@csrf
                                <label class="field"><span>{{ __('messages.invite.start') }}</span><input type="date" name="start" required min="{{ now()->toDateString() }}" value="{{ old('start', $plan['start'] ?? '') }}"></label>
                                <label class="field"><span>{{ __('messages.invite.per_day') }}</span><input type="number" name="per_day" min="10" max="500" required value="{{ old('per_day', $plan['per_day'] ?? 100) }}"></label>
                                <label class="field"><span>{{ __('messages.invite.order') }}</span><input name="countries" maxlength="200" value="{{ old('countries', implode(', ', $plan['countries'] ?? \App\Services\Media\Messages::FIRST_COUNTRIES)) }}"><small class="muted">{{ __('messages.invite.order_hint') }}</small></label>
                                @foreach (['start', 'per_day', 'send'] as $f)@error($f)<small class="txt-danger">{{ $message }}</small>@enderror @endforeach
                                <div><button class="primary" type="submit">{{ __($m->status === 'scheduled' ? 'messages.invite.replan' : 'messages.invite.plan_go') }}</button></div>
                            </form>
                            @if ($m->status === 'scheduled')<form method="post" action="{{ route('messages.unschedule', $m) }}">@csrf<button class="btn" type="submit">{{ __('messages.unschedule') }}</button></form>@endif
                            <small class="muted">{{ __('messages.invite.rules') }}</small>
                        @else
                            @php($prog = $m->sends()->selectRaw("coalesce(country, '') as c, count(*) as n, sum(case when status = 'sent' then 1 else 0 end) as sent, sum(case when status = 'skipped' then 1 else 0 end) as skipped, sum(case when status = 'failed' then 1 else 0 end) as failed, min(planned_on) as d1, max(planned_on) as d2")->groupBy('c')->orderBy('d1')->get())
                            <p>{{ $m->status === 'sent' ? trans_choice('messages.was_sent', $m->sent_count, ['n' => $m->sent_count, 'date' => $m->sent_at?->format('d/m/Y H:i'), 'name' => $m->sentBy?->name ?? '—']) : __('messages.is_sending', ['done' => $prog->sum('sent'), 'total' => $prog->sum('n')]) }}@if ($m->status === 'paused') <span class="pill pill-warn">{{ __('messages.statuses.paused') }}</span>@endif</p>
                            <div class="table-wrap"><table class="table">
                                <thead><tr><th>{{ __('messages.invite.country') }}</th><th class="num">{{ __('messages.invite.sent') }}</th><th>{{ __('messages.invite.days') }}</th></tr></thead>
                                <tbody>@foreach ($prog as $row)<tr><td>{{ $cname($row->c) }}</td><td class="num">{{ $row->sent }} / {{ $row->n }}@if ($row->skipped) <small class="muted">· {{ trans_choice('messages.invite.skipped', $row->skipped, ['n' => $row->skipped]) }}</small>@endif @if ($row->failed) <small class="txt-danger">· {{ trans_choice('messages.failed_n', $row->failed, ['n' => $row->failed]) }}</small>@endif</td><td>{{ $row->d1 ? \Illuminate\Support\Carbon::parse($row->d1)->format('d/m') : '—' }}@if ($row->d2 && $row->d2 !== $row->d1) – {{ \Illuminate\Support\Carbon::parse($row->d2)->format('d/m') }}@endif</td></tr>@endforeach</tbody>
                            </table></div>
                            @if (in_array($m->status, ['sending', 'paused'], true))
                                <form method="post" action="{{ route('messages.pause', $m) }}">@csrf<button class="btn" type="submit">{{ __($m->status === 'sending' ? 'messages.invite.pause' : 'messages.invite.resume') }}</button></form>
                            @endif
                        @endif
                    </section>
                @elseif ($mailed)
                    <section class="card stack-sm">
                        <h2>{{ __('messages.send_title') }}</h2>
                        @if (in_array($m->status, ['draft', 'scheduled'], true))
                            @php($n = count(\App\Services\Media\Messages::recipients($m)))
                            <p>{{ trans_choice('messages.will_go', $n, ['n' => $n]) }}</p>
                            <form method="post" action="{{ route('messages.send', $m) }}" onsubmit="return confirm(@js(trans_choice('messages.confirm_send', $n, ['n' => $n])))">@csrf<button class="primary" type="submit" @disabled($n === 0)>{{ __('messages.send_now') }}</button></form>
                            @if ($m->status === 'scheduled')
                                <p class="flash flash-ok">{{ __('messages.scheduled', ['at' => $m->send_at->format('d/m/Y H:i')]) }}</p>
                                <form method="post" action="{{ route('messages.unschedule', $m) }}">@csrf<button class="btn" type="submit">{{ __('messages.unschedule') }}</button></form>
                            @else
                                <form method="post" action="{{ route('messages.schedule', $m) }}" class="stack-sm">@csrf
                                    <label class="field"><span>{{ __('messages.schedule_at') }}</span><input type="datetime-local" name="send_at" required min="{{ now()->format('Y-m-d\TH:i') }}"></label>
                                    @error('send_at')<small class="txt-danger">{{ $message }}</small>@enderror
                                    <div><button class="btn" type="submit">{{ __('messages.schedule_go') }}</button></div>
                                </form>
                            @endif
                            <small class="muted">{{ __('messages.once_rule') }}</small>
                        @else
                            <p>{{ $m->status === 'sending' ? __('messages.is_sending', ['done' => $m->sends()->where('status', 'sent')->count(), 'total' => $m->sends()->count()]) : trans_choice('messages.was_sent', $m->sent_count, ['n' => $m->sent_count, 'date' => $m->sent_at?->format('d/m/Y H:i'), 'name' => $m->sentBy?->name ?? '—']) }}</p>
                            @if ($f = $m->sends()->where('status', 'failed')->count())<p class="txt-danger small">{{ trans_choice('messages.failed_n', $f, ['n' => $f]) }}</p>@endif
                        @endif
                    </section>
                @else
                    <section class="card stack-sm">
                        <h2>{{ __('messages.use_title') }}</h2>
                        <p>{{ __($m->active ? 'messages.is_on.'.$m->kind : 'messages.is_off.'.$m->kind) }}</p>
                        <form method="post" action="{{ route('messages.toggle', $m) }}">@csrf<button @class(['btn', 'primary' => ! $m->active]) type="submit">{{ __($m->active ? 'messages.switch_off' : 'messages.switch_on') }}</button></form>
                    </section>
                @endif

                <section class="card stack-sm">
                    <h2>{{ __('messages.preview') }}</h2>
                    @foreach ($names as $l => $n)
                        <details @if ($l === app()->getLocale()) open @endif><summary>{{ $n }}</summary>
                            <div class="msg-mail">@if ($m->image_path)<img src="{{ URL::signedRoute('messages.public.image', $m) }}" alt="">@endif<b>{{ $m->text('subject', $l) }}</b>@if ($m->link_url)<small class="muted">→ {{ $m->link_url }}</small>@endif<p class="prewrap">{{ $m->text('body', $l) }}</p></div>
                        </details>
                    @endforeach
                </section>

                @if (! in_array($m->status, ['sending', 'sent'], true))
                    <details class="card"><summary>{{ __('messages.delete') }}</summary><form method="post" action="{{ route('messages.destroy', $m) }}" class="mt">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">{{ __('messages.delete_go') }}</button></form></details>
                @endif
            </aside>
        @endif
    </div>
</x-layouts.app>
