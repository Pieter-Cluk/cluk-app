@php($eur = fn ($c) => \App\Services\Afrit6\Pricing::eur((int) $c))
@php($old = fn ($k, $d = null) => old($k, $d))
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cluk Factory · {{ __('factory.book.title') }}</title>
<meta name="description" content="{{ __('factory.book.tagline') }}">
<link rel="stylesheet" href="{{ asset('css/factory.css') }}">
@include('partials.favicon')
</head>
<body class="fb">
<header class="hero">
    <div class="fb-top">
    @include('partials.site-switch', ['current' => 'factory'])
    <nav class="fb-langs" aria-label="{{ __('login.language') }}">
        @foreach (config('cluk.locales') as $code => $name)
            <a href="?lang={{ $code }}" @class(['on' => app()->getLocale() === $code]) lang="{{ $code }}" title="{{ $name }}">{{ strtoupper($code) }}</a>
        @endforeach
    </nav>
    </div>
    <div class="wm">cluk</div>
    <div class="wm2">FACTORY</div>
    <p class="claim">{{ mb_strtoupper(__('factory.book.tagline')) }}</p>
    <div class="stats">
        <div><b>720 m²</b><span>{{ __('factory.book.stat_space') }}</span></div>
        <div><b>8 m</b><span>{{ __('factory.book.stat_height') }}</span></div>
        <div><b>24/7</b><span>{{ __('factory.book.stat_secure') }}</span></div>
        <div><b>{{ __('factory.book.stat_bus_v') }}</b><span>{{ __('factory.book.stat_bus') }}</span></div>
    </div>
</header>

@if ($sent)
    <main class="wrap wrap-one">
        <section class="step done" role="status">
            <div class="sn">{{ $sent->number }}</div>
            <h1 class="st">{{ __('factory.book.thanks_title') }}</h1>
            <p>{{ $sent->status === 'option' ? __('factory.book.thanks_option', ['date' => $sent->option_until->locale(app()->getLocale())->translatedFormat('l j F, H:i'), 'email' => $sent->req_email]) : __('factory.book.thanks_request', ['email' => $sent->req_email]) }}</p>
            <p><a href="{{ route('factory.book') }}">{{ __('factory.book.another') }}</a></p>
        </section>
    </main>
@else
<form method="post" action="{{ route('factory.book.store') }}" class="wrap" id="fb-form">
    @csrf
    <div class="steps">
        @if ($errors->any())<div class="err" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

        <section class="step">
            <div class="sn">{{ __('factory.book.step', ['n' => 1]) }}</div>
            <h2 class="st">{{ __('factory.book.s1') }}</h2>
            <div class="chips">
                @foreach (config('factory.event_types') as $t)
                    <label class="chip"><input type="radio" name="event_type" value="{{ $t }}" @checked($old('event_type', 'launch') === $t)><span>{{ __('factory.types.'.$t) }}</span></label>
                @endforeach
            </div>
            <div class="row">
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.guests')) }}</span><input class="inp" type="number" name="guests" min="1" max="{{ config('factory.max_guests') }}" value="{{ $old('guests', 150) }}" required></label>
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.days')) }}</span><select class="inp" name="days">@for ($i = 1; $i <= config('factory.max_days'); $i++)<option value="{{ $i }}" @selected((int) $old('days', 1) === $i)>{{ trans_choice('factory.n_days', $i, ['n' => $i]) }}</option>@endfor</select></label>
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.buildup')) }}</span><select class="inp" name="buildup_days">@for ($i = 0; $i <= config('factory.max_buildup'); $i++)<option value="{{ $i }}" @selected((int) $old('buildup_days', 0) === $i)>{{ $i ? trans_choice('factory.n_days', $i, ['n' => $i]) : __('factory.book.same_day') }}</option>@endfor</select></label>
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.breakdown')) }}</span><select class="inp" name="breakdown_days">@for ($i = 0; $i <= config('factory.max_breakdown'); $i++)<option value="{{ $i }}" @selected((int) $old('breakdown_days', 0) === $i)>{{ $i ? __('factory.book.next_day') : __('factory.book.same_night') }}</option>@endfor</select></label>
            </div>
            <div class="cal-head">
                <button type="button" class="chip js-prev" aria-label="{{ __('factory.book.prev') }}">‹</button>
                <b class="js-month" aria-live="polite"></b>
                <button type="button" class="chip js-next" aria-label="{{ __('factory.book.next') }}">›</button>
            </div>
            <div class="cal js-cal" role="grid"></div>
            <div class="legend"><span><i class="lg-sel"></i>{{ __('factory.book.lg_event') }}</span><span><i class="lg-bu"></i>{{ __('factory.book.lg_bu') }}</span><span><i class="lg-opt"></i>{{ __('factory.book.lg_option') }}</span><span><i class="lg-booked"></i>{{ __('factory.book.lg_booked') }}</span></div>
            <label class="fld nojs"><span class="lbl">{{ mb_strtoupper(__('factory.book.date')) }}</span><input class="inp" type="date" name="start_date" value="{{ $old('start_date') }}" min="{{ today()->addDay()->toDateString() }}" required></label>
        </section>

        <section class="step">
            <div class="sn">{{ __('factory.book.step', ['n' => 2]) }}</div>
            <h2 class="st">{{ __('factory.book.s2') }}</h2>
            <p class="muted">{{ __('factory.book.s2_hint') }}</p>
            <div class="sizes">
                @foreach ($sizes as $z)
                    <label class="sz">
                        <input type="radio" name="size" value="{{ $z['code'] }}" data-price="{{ $z['price'] }}" @checked($old('size', 'L') === $z['code'])>
                        <b class="big">{{ $z['code'] }}</b>
                        <span class="blocks" aria-hidden="true">@for ($i = 0; $i < 12; $i++)<span @class(['on' => $i < round($z['floor'] / 50)])></span>@endfor</span>
                        <small>{{ __('factory.book.floor', ['floor' => $z['floor'], 'ent' => $z['entrance']]) }}</small>
                        <b>{{ __('factory.from', ['amount' => $eur($z['price'])]) }}</b>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="step">
            <div class="sn">{{ __('factory.book.step', ['n' => 3]) }}</div>
            <h2 class="st">{{ __('factory.book.s3') }}</h2>
            <div class="lv">
                @foreach (array_keys(config('factory.levels')) as $lv)
                    <label class="lvc">
                        <input type="radio" name="level" value="{{ $lv }}" data-price="{{ $lv === 'advanced' ? $advanced : 0 }}" @checked($old('level', 'advanced') === $lv)>
                        <b>{{ __('factory.levels.'.$lv) }}</b>
                        <small class="lbl">{{ mb_strtoupper(__('factory.level_sub.'.$lv)) }}</small>
                        <ul>@foreach (explode('|', __('factory.level_inc.'.$lv)) as $inc)<li>{{ $inc }}</li>@endforeach</ul>
                        <b class="price">{{ $lv === 'basic' ? __('factory.book.included') : ($lv === 'advanced' ? __('factory.from', ['amount' => $eur($advanced)]) : __('factory.custom')) }}</b>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="step">
            <div class="sn">{{ __('factory.book.step', ['n' => 4]) }}</div>
            <h2 class="st">{{ __('factory.book.s4') }}</h2>
            <div class="chips">
                @foreach (config('factory.extras') as $x)
                    <label class="chip"><input type="checkbox" name="extras[]" value="{{ $x }}" @checked(in_array($x, $old('extras', []), true))><span>{{ __('factory.extras.'.$x) }}</span></label>
                @endforeach
            </div>
            <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.tell')) }}</span><textarea class="inp" name="note" rows="3" maxlength="3000">{{ $old('note') }}</textarea></label>
        </section>

        <section class="step">
            <div class="sn">{{ __('factory.book.step', ['n' => 5]) }}</div>
            <h2 class="st">{{ __('factory.book.s5') }}</h2>
            <div class="row">
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.company')) }}</span><input class="inp" name="req_company" maxlength="160" required value="{{ $old('req_company') }}" autocomplete="organization"></label>
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.vat')) }}</span><input class="inp" name="req_vat" maxlength="30" value="{{ $old('req_vat') }}"></label>
            </div>
            <div class="row">
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.name')) }}</span><input class="inp" name="req_name" maxlength="160" required value="{{ $old('req_name') }}" autocomplete="name"></label>
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.email')) }}</span><input class="inp" type="email" name="req_email" maxlength="190" required value="{{ $old('req_email') }}" autocomplete="email"></label>
                <label class="fld"><span class="lbl">{{ mb_strtoupper(__('factory.book.phone')) }}</span><input class="inp" name="req_phone" maxlength="40" value="{{ $old('req_phone') }}" autocomplete="tel"></label>
            </div>
            <label class="hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        </section>
    </div>

    <aside class="sum">
        <div class="lbl">{{ mb_strtoupper(__('factory.book.your_request')) }}</div>
        <dl>
            <dt>{{ __('factory.book.event') }}</dt><dd class="js-s-type">—</dd>
            <dt>{{ __('factory.book.date') }}</dt><dd class="js-s-date">{{ __('factory.book.pick_date') }}</dd>
            <dt>{{ __('factory.book.on_site') }}</dt><dd class="js-s-site">—</dd>
            <dt>{{ __('factory.book.guests') }}</dt><dd class="js-s-guests">—</dd>
            <dt>{{ __('factory.book.size') }}</dt><dd class="js-s-size">—</dd>
            <dt>{{ __('factory.book.level') }}</dt><dd class="js-s-level">—</dd>
        </dl>
        <div class="est"><span class="lbl">{{ mb_strtoupper(__('factory.book.estimate')) }}</span><b class="js-est">—</b><small class="js-est-sub"></small></div>
        <p class="clash js-clash" hidden>{{ __('factory.errors.taken') }}</p>
        <button class="btn-p" type="submit" name="action" value="request">{{ __('factory.book.request') }}</button>
        <button class="btn-s" type="submit" name="action" value="hold">{{ __('factory.book.hold', ['n' => config('factory.option_days')]) }}</button>
        <small class="muted">{{ __('factory.book.hold_hint', ['n' => config('factory.option_days')]) }}</small>
        <small class="muted">{!! __('factory.book.terms', ['link' => '<a href="'.e(route('terms', app()->getLocale())).'" target="_blank" rel="noopener">'.e(__('mails.terms')).'</a>']) !!}</small>
    </aside>
</form>
@php($jsTexts = ['months' => array_map(fn ($m) => \Illuminate\Support\Carbon::create(2026, $m, 1)->locale(app()->getLocale())->translatedFormat('F'), range(1, 12)), 'dows' => array_map(fn ($d) => \Illuminate\Support\Carbon::create(2026, 10, 19)->addDays($d)->locale(app()->getLocale())->translatedFormat('D'), range(0, 6)), 'event' => __('factory.book.tag_event'), 'bu' => __('factory.book.tag_bu'), 'bd' => __('factory.book.tag_bd'), 'booked' => __('factory.book.tag_booked'), 'option' => __('factory.book.tag_option'), 'from' => __('factory.from', ['amount' => '#']), 'custom' => __('factory.custom'), 'days' => __('factory.book.x_days'), 'adv' => __('factory.book.plus_adv'), 'same' => __('factory.book.same_day'), 'locale' => app()->getLocale()])
<script>
(() => {
    const taken = {!! json_encode((object) $taken) !!};
    const T = {!! json_encode($jsTexts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};
    const form = document.getElementById('fb-form');
    if (!form) return;
    document.body.classList.add('js');
    const $ = s => form.querySelector(s);
    const dateIn = $('[name=start_date]');
    const pad = n => String(n).padStart(2, '0');
    const key = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const add = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
    const today = new Date(); today.setHours(12, 0, 0, 0);
    let view = dateIn.value ? new Date(dateIn.value + 'T12:00:00') : new Date(today.getFullYear(), today.getMonth(), 1, 12);
    view = new Date(view.getFullYear(), view.getMonth(), 1, 12);
    const max = new Date(today.getFullYear(), today.getMonth() + {{ (int) config('factory.months_ahead') }}, 1, 12);
    const eur = c => '€ ' + (c / 100).toLocaleString('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); // same as the prices on the cards
    const val = n => { const el = form.querySelector('[name=' + n + ']:checked') || form.querySelector('[name=' + n + ']'); return el ? el.value : ''; };
    const range = () => {
        if (!dateIn.value) return { days: {}, clash: false };
        const st = new Date(dateIn.value + 'T12:00:00'), days = +val('days'), bu = +val('buildup_days'), bd = +val('breakdown_days');
        const out = {}; let clash = false;
        for (let i = -bu; i < days + bd; i++) { const k = key(add(st, i)); out[k] = i < 0 ? 'bu' : i >= days ? 'bd' : 'event'; if (taken[k]) clash = true; }
        return { days: out, clash, st, days_n: days, bu, bd };
    };
    const fmt = d => d.toLocaleDateString(T.locale, { day: 'numeric', month: 'short', year: 'numeric' });
    function render() {
        const r = range();
        $('.js-month').textContent = T.months[view.getMonth()] + ' ' + view.getFullYear();
        const cal = $('.js-cal'); cal.innerHTML = '';
        T.dows.forEach(d => { const h = document.createElement('div'); h.className = 'dow'; h.textContent = d; cal.appendChild(h); });
        const lead = (view.getDay() + 6) % 7;
        for (let i = 0; i < lead; i++) { const e = document.createElement('span'); e.className = 'd d-empty'; cal.appendChild(e); }
        const nd = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
        for (let n = 1; n <= nd; n++) {
            const d = new Date(view.getFullYear(), view.getMonth(), n, 12), k = key(d), t = taken[k], p = r.days[k], past = d <= today;
            const b = document.createElement('button'); b.type = 'button'; b.className = 'd';
            if (past) b.classList.add('d-past'); else if (p && t === 'b') b.classList.add('d-clash'); else if (p === 'event') b.classList.add('d-sel'); else if (p) b.classList.add('d-bu'); else if (t === 'b') b.classList.add('d-booked'); else if (t === 'o') b.classList.add('d-option');
            b.disabled = past || (t === 'b' && !p);
            b.innerHTML = '<span>' + n + '</span><small>' + (p ? (p === 'event' ? T.event : p === 'bu' ? T.bu : T.bd) : t === 'b' ? T.booked : t === 'o' ? T.option : '') + '</small>';
            b.setAttribute('aria-pressed', p === 'event' ? 'true' : 'false');
            b.addEventListener('click', () => { dateIn.value = k; render(); });
            cal.appendChild(b);
        }
        $('.js-prev').disabled = view <= new Date(today.getFullYear(), today.getMonth(), 1, 12);
        $('.js-next').disabled = view >= max;
        // summary
        const lab = n => { const el = form.querySelector('[name=' + n + ']:checked'); return el ? el.closest('label').querySelector('span,b').textContent : '—'; };
        $('.js-s-type').textContent = lab('event_type');
        $('.js-s-guests').textContent = val('guests') || '—';
        $('.js-s-size').textContent = val('size');
        $('.js-s-level').textContent = lab('level');
        if (r.st) {
            $('.js-s-date').textContent = fmt(r.st) + (r.days_n > 1 ? ' – ' + fmt(add(r.st, r.days_n - 1)) : '');
            $('.js-s-site').textContent = (r.bu ? fmt(add(r.st, -r.bu)) : T.same) + ' → ' + fmt(add(r.st, r.days_n - 1 + r.bd));
        }
        const sz = form.querySelector('[name=size]:checked'), lv = form.querySelector('[name=level]:checked');
        const space = (+sz.dataset.price) * (+val('days'));
        if (lv.value === 'allin') { $('.js-est').textContent = T.custom; $('.js-est-sub').textContent = T.from.replace('#', eur(space)); }
        else { $('.js-est').textContent = T.from.replace('#', eur(space + (+lv.dataset.price))); $('.js-est-sub').textContent = val('size') + ' ' + eur(+sz.dataset.price) + (+val('days') > 1 ? ' × ' + val('days') + ' ' + T.days : '') + (lv.value === 'advanced' ? ' + ' + T.adv : ''); }
        $('.js-clash').hidden = !r.clash;
        form.querySelectorAll('button[type=submit]').forEach(b => b.disabled = r.clash || !dateIn.value);
    }
    $('.js-prev').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() - 1, 1, 12); render(); });
    $('.js-next').addEventListener('click', () => { view = new Date(view.getFullYear(), view.getMonth() + 1, 1, 12); render(); });
    form.addEventListener('change', render);
    form.addEventListener('input', e => { if (e.target.name === 'guests') render(); });
    render();
})();
</script>
@endif
@include('site._feed-strip', ['posts' => rescue(fn () => \App\Services\Website\SocialFeed::for('factory', 4), collect(), false), 'title' => __('site.news.latest_factory')])
<footer class="fb-foot">Cluk Factory · Karreweg 17B, 9870 Zulte, Belgium · <a href="https://cluk.live">cluk.live</a> · {{ __('factory.book.footer') }}</footer>
</body>
</html>
