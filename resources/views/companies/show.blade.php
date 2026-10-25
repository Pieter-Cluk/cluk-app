@php($rel = $company->relationshipKey())
@php($fmt = fn ($d) => $d?->timezone(config('app.timezone'))->format('d/m/Y'))
<x-layouts.app :title="$company->name">
    <div class="company-head rel-row-{{ $rel }}">
        <div class="logo-box">
            <x-logo :company="$company" size="lg" :rel="$rel"/>
            @if ($canEdit)
                <details class="logo-edit">
                    <summary @class(['linkbtn' => $company->logo_path, 'btn btn-sm' => ! $company->logo_path])>{{ $company->logo_path ? __('pictures.change') : '＋ '.__('pictures.add_logo') }}</summary>
                    <form method="post" action="{{ route('companies.logo', $company) }}" enctype="multipart/form-data" class="stack-sm">
                        @csrf
                        <input type="file" name="logo" accept="image/*" required aria-label="{{ __('pictures.add_logo') }}">
                        <button class="btn" type="submit">{{ __('pictures.upload') }}</button>
                    </form>
                    @if ($company->logo_path)<form method="post" action="{{ route('companies.logo.destroy', $company) }}">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('pictures.remove') }}</button></form>@endif
                </details>
            @endif
        </div>
        <div class="company-id">
            <div class="kicker"><a href="{{ route('companies.index') }}">{{ mb_strtoupper(__('nav.items.companies')) }}</a></div>
            <h1>{{ $company->name }}@if ($company->legal_form) <span class="muted">{{ $company->legal_form }}</span>@endif</h1>
            <div class="badges">
                @if ($company->is_customer)<span class="badge badge-customer">{{ __('companies.relationship.customer') }}</span>@endif
                @if ($company->is_supplier)<span class="badge badge-supplier">{{ __('companies.relationship.supplier') }}</span>@endif
                @if ($company->archived_at)<span class="pill pill-off">{{ __('companies.show.archived') }}</span>@endif
            </div>
            <p class="muted">
                @if ($company->vat_number)
                    {{ $company->formattedVat() }} ·
                    @if ($company->vies_status)
                        <span @class(['vies', 'vies-'.$company->vies_status])>{{ __('companies.vies.status_'.$company->vies_status, ['date' => $fmt($company->vies_checked_at)]) }}</span>
                        @include('partials.vies-reason', ['reason' => $company->vies_reason, 'viesName' => $company->vies_name, 'given' => $company->name, 'checked' => null])
                    @elseif (\App\Services\Vies::appliesTo($company->vat_number))
                        {{ __('companies.vies.not_checked') }}
                    @else
                        {{ __('companies.vies.outside_eu') }}
                    @endif
                @else
                    {{ __('companies.show.no_vat') }}
                @endif
                · {{ __('companies.show.documents_in', ['language' => config('cluk.locales')[$company->language] ?? $company->language]) }}
                · {{ __('companies.fields.account_owner_id') }}: {{ $company->accountOwner?->name ?? '—' }}
            </p>
        </div>
        <div class="head-actions">
            @if ($company->phone)<a class="btn" href="tel:{{ \App\Support\Phone::dial($company->phone, $company->country) }}">{{ __('companies.show.call') }}</a>@if (\App\Support\Phone::maybeMobile($company->phone, $company->country))<a class="btn" href="{{ \App\Support\Phone::whatsapp($company->phone, $company->country) }}" target="_blank" rel="noopener">{{ __('companies.show.whatsapp') }}</a>@endif @endif
            @if ($company->email)<a class="btn" href="mailto:{{ $company->email }}">{{ __('companies.show.email') }}</a>@endif
            @if ($canEdit)<a class="primary" href="{{ route('companies.edit', $company) }}">{{ __('settings.edit') }}</a>@endif
        </div>
    </div>
    @include('partials.flash')

    <div class="split">
        <div class="stack">
            <section class="card">
                <div class="card-head">
                    <h2>{{ __('companies.contacts.title') }}</h2>
                </div>
                @forelse ($company->contacts as $c)
                    <div class="contact" id="contact-{{ $c->id }}">
                        <x-avatar :who="$c"/>
                        <div class="contact-main">
                            <b>{{ $c->name }}</b>
                            <small class="muted">{{ $c->pivot->job_title }}</small>
                            <div class="contact-lines">
                                @if ($c->mobile)@include('partials.phone', ['number' => $c->mobile, 'country' => $company->country, 'whatsapp' => true])@endif
                                @if ($c->phone)@include('partials.phone', ['number' => $c->phone, 'country' => $company->country])@endif
                                @if ($c->email)<a href="mailto:{{ $c->email }}">{{ $c->email }}</a>@endif
                            </div>
                            <div class="badges">
                                @if ($c->pivot->is_main)<span class="pill pill-ok">★ {{ __('companies.contacts.main') }}</span>@endif
                                @if ($c->pivot->is_billing)<span class="pill pill-ok">€ {{ __('companies.contacts.billing') }}</span>@endif
                                <span @class(['pill', 'pill-ok' => $c->newsletter, 'pill-off' => ! $c->newsletter])>{{ __('companies.contacts.newsletter') }}: {{ __($c->newsletter ? 'companies.yes' : 'companies.no') }}</span>
                                <span @class(['pill', 'pill-ok' => $c->birthday_card && $c->birth_date, 'pill-off' => ! ($c->birthday_card && $c->birth_date)]) title="{{ __('companies.contacts.birthday_card') }}">🎂 {{ $c->birth_date ? $c->birth_date->locale(app()->getLocale())->translatedFormat('j F') : __('companies.contacts.no_birth_date') }}@if ($c->birth_date) · {{ __('companies.contacts.card') }}: {{ __($c->birthday_card ? 'companies.yes' : 'companies.no') }}@endif</span>
                                @foreach ($c->companies->reject(fn ($o) => $o->id === $company->id) as $other)
                                    <a class="pill pill-info" href="{{ route('companies.show', $other) }}">{{ __('companies.contacts.also_at', ['name' => $other->name]) }}</a>
                                @endforeach
                            </div>
                        </div>
                        @if ($canEdit)
                            <details class="contact-edit">
                                <summary class="linkbtn">{{ __('settings.edit') }}</summary>
                                <form method="post" action="{{ route('companies.contacts.update', [$company, $c]) }}">
                                    @csrf @method('put')
                                    @include('companies._contact-fields', ['c' => $c, 'pivot' => $c->pivot])
                                    <div class="form-actions"><button class="primary" type="submit">{{ __('settings.save') }}</button></div>
                                </form>
                                <form method="post" action="{{ route('companies.contacts.photo', [$company, $c]) }}" enctype="multipart/form-data" class="inline-form mt">
                                    @csrf
                                    <span class="muted small">{{ __('pictures.contact_photo') }}</span>
                                    <input type="file" name="photo" accept="image/*" required aria-label="{{ __('pictures.contact_photo') }}">
                                    <button class="btn" type="submit">{{ __('pictures.upload') }}</button>
                                </form>
                                @if ($c->photo_path)<form method="post" action="{{ route('companies.contacts.photo.destroy', [$company, $c]) }}">@csrf @method('DELETE')<button class="linkbtn danger" type="submit">{{ __('pictures.remove_photo') }}</button></form>@endif
                                <form method="post" action="{{ route('companies.contacts.destroy', [$company, $c]) }}" onsubmit="return confirm(@js(__('companies.contacts.remove_confirm', ['name' => $c->name, 'company' => $company->name])))">
                                    @csrf @method('delete')
                                    <button class="linkbtn danger" type="submit">{{ __('companies.contacts.remove') }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="muted">{{ __('companies.contacts.none') }}</p>
                @endforelse

                @if ($canEdit)
                    <details class="add-block" @if ($errors->any() && old('first_name')) open @endif>
                        <summary class="btn">{{ __('companies.contacts.add') }}</summary>
                        <form method="post" action="{{ route('companies.contacts.store', $company) }}">
                            @csrf
                            @include('companies._contact-fields', ['c' => new \App\Models\Contact(collect(old())->only(['first_name', 'last_name', 'email', 'phone', 'mobile', 'language'])->all()), 'pivot' => null])
                            <p class="muted small">{{ __('companies.contacts.add_hint') }}</p>
                            <div class="form-actions"><button class="primary" type="submit">{{ __('companies.contacts.add_save') }}</button></div>
                        </form>
                    </details>
                @endif
            </section>

            <section class="card">
                <h2>{{ __('companies.addresses.title') }}</h2>
                <dl class="facts">
                    <dt>{{ __('companies.addresses.main') }}</dt><dd>{{ $company->address() ?: '—' }}</dd>
                    @foreach ($company->addresses as $a)
                        <dt>{{ $a->label }}</dt>
                        <dd>{{ $a->line() }}
                            @if ($canEdit)
                                <form method="post" action="{{ route('companies.addresses.destroy', [$company, $a]) }}" class="inline-form">@csrf @method('delete')<button class="linkbtn danger" type="submit">{{ __('companies.addresses.remove') }}</button></form>
                            @endif
                        </dd>
                    @endforeach
                </dl>
                @if ($canEdit)
                    <details class="add-block">
                        <summary class="btn">{{ __('companies.addresses.add') }}</summary>
                        <form method="post" action="{{ route('companies.addresses.store', $company) }}">
                            @csrf
                            <div class="form-grid">
                                <label class="field"><span>{{ __('companies.addresses.label') }}</span><input name="label" required maxlength="80" placeholder="{{ __('companies.addresses.label_hint') }}"></label>
                                <label class="field"><span>{{ __('companies.fields.street') }}</span><input name="street" required maxlength="160"></label>
                                <label class="field"><span>{{ __('companies.fields.postcode') }}</span><input name="postcode" maxlength="20"></label>
                                <label class="field"><span>{{ __('companies.fields.city') }}</span><input name="city" required maxlength="80"></label>
                                <label class="field"><span>{{ __('companies.fields.country') }}</span><input name="country" value="{{ $company->country }}" required maxlength="2"></label>
                            </div>
                            <div class="form-actions"><button class="primary" type="submit">{{ __('companies.addresses.add') }}</button></div>
                        </form>
                    </details>
                @endif
            </section>

            @php($logins = \App\Models\Web\CustomerAccount::where('company_id', $company->id)->orWhereIn('contact_id', $company->contacts->pluck('id'))->orderBy('first_name')->get())
            @include('companies._sales-conditions')
            @include('web._activity', ['act' => \App\Services\Website\WebActivity::summary(\App\Services\Website\WebActivity::visitorsOfCompany($company))])
            @php($dls = \App\Models\Website\DownloadLog::with(['download', 'contact'])->where('company_id', $company->id)->latest('id')->limit(10)->get())
            @if ($dls->isNotEmpty())
                <section class="card" id="downloads">
                    <div class="card-head"><h2>{{ __('website.downloads.on_card') }}</h2></div>
                    <ul class="plain-list">@foreach ($dls as $l)<li>{{ $l->download?->label(null, 'title') }} <small class="muted">· {{ $l->contact?->name ?? $l->name ?? $l->email }} · {{ $l->created_at->format('d/m/Y H:i') }}</small></li>@endforeach</ul>
                </section>
            @endif

            <section class="card">
                <div class="card-head"><h2>{{ __('web.staff.card_title') }}</h2><a href="{{ route('web-accounts.index') }}">{{ __('web.staff.all') }} →</a></div>
                @forelse ($logins as $l)
                    <div class="contact"><div><b>{{ $l->name() }}</b> · {{ $l->email }} <span @class(['pill', 'pill-ok' => $l->status === 'active', 'pill-warn' => $l->status === 'pending', 'pill-off' => $l->status === 'blocked'])>{{ __('web.statuses.'.$l->status) }}</span>
                        @php($n = \App\Services\Web\Baskets::counts($l))
                        <br><small class="muted">{{ $l->last_login_at ? __('web.staff.last_login', ['date' => $l->last_login_at->format('d/m/Y H:i')]) : __('web.staff.never') }}@if ($n['requests']) · {{ __('web.staff.requests', ['n' => $n['requests']]) }}@endif @if ($n['orders']) · {{ __('web.staff.orders', ['n' => $n['orders']]) }}@endif @if ($n['in_baskets']) · {{ __('web.staff.in_baskets', ['n' => $n['in_baskets']]) }}@endif</small></div>
                        @if ($canEdit && $l->status !== 'blocked')<form method="post" action="{{ route('web-accounts.reset', $l) }}">@csrf<button class="linkbtn" type="submit">{{ __('web.staff.send_reset') }}</button></form>@endif
                    </div>
                @empty
                    <p class="muted">{{ __('web.staff.no_logins') }}</p>
                @endforelse
            </section>

            <section class="card">
                <h2>{{ __('companies.fields.notes') }} <span class="muted small">· {{ __('companies.fields.notes_hint') }}</span></h2>
                @if ($canEdit)
                    <form method="post" action="{{ route('companies.notes', $company) }}" class="stack-sm">@csrf
                        <textarea name="notes" rows="4" maxlength="5000" placeholder="{{ __('companies.fields.notes_placeholder') }}" aria-label="{{ __('companies.fields.notes') }}">{{ old('notes', $company->notes) }}</textarea>
                        <div><button class="btn" type="submit">{{ __('companies.save_notes') }}</button></div>
                    </form>
                @elseif ($company->notes)
                    <p class="prewrap">{{ $company->notes }}</p>
                @else
                    <p class="muted">—</p>
                @endif
            </section>
        </div>

        <aside class="stack">
            @include('companies._credit')
            <section class="card">
                <h2>{{ __('companies.vat.title') }} · {{ $vatRate?->percent() ?? '—' }}</h2>
                <p class="muted small">{{ __('companies.vat.intro') }}</p>
                <dl class="facts">
                    <dt>{{ __('companies.fields.country') }}</dt><dd>{{ $company->country }}</dd>
                    <dt>{{ __('companies.vat.situation') }}</dt><dd>{{ __('companies.vat.situations.'.$company->vatSituation()) }}</dd>
                    <dt>{{ __('companies.vat.applied') }}</dt><dd><b>{{ $vatRate?->name ?? $company->vatCode() }}</b> · {{ __('companies.vat.okioki', ['code' => $company->vatCode()]) }}
                        <br><small class="muted">{{ $company->vat_regime === 'auto' ? __('companies.vat.automatic') : __('companies.vat.by_hand') }}</small></dd>
                </dl>
                @if ($vatRate && $vatRate->rate_bp === 0)<p class="muted small">{{ __('companies.vat.zero_note') }}</p>@endif
                @if ($canVat)
                    <form method="post" action="{{ route('companies.vat', $company) }}" class="vat-form">
                        @csrf @method('put')
                        <label class="field"><span>{{ __('companies.vat.regime') }}</span>
                            <select name="vat_regime">
                                <option value="auto" @selected($company->vat_regime === 'auto')>{{ __('companies.vat.auto_option') }}</option>
                                @foreach ($vatChoices as $choice)<option value="{{ $choice->code }}" @selected($company->vat_regime === $choice->code)>{{ $choice->name }}</option>@endforeach
                            </select></label>
                        <button class="btn" type="submit">{{ __('settings.save') }}</button>
                    </form>
                @else
                    <p class="muted small">{{ __('companies.vat.who_can_change') }}</p>
                @endif
                @if ($canEdit && \App\Services\Vies::appliesTo($company->vat_number))
                    <form method="post" action="{{ route('companies.vies', $company) }}">@csrf<button class="linkbtn" type="submit">{{ __('companies.vies.check_again') }}</button></form>
                @endif
                @if ($company->vies_name || $company->vies_address)
                    <p class="small muted">{{ __('companies.vies.registered_as') }}: {{ $company->vies_name }}@if ($company->vies_address)<br>{{ $company->vies_address }}@endif</p>
                @endif
            </section>

            <section class="card">
                <h2>{{ __('companies.show.relationship') }}</h2>
                <dl class="facts">
                    @if ($company->is_customer)
                        <dt>{{ __('companies.fields.customer_payment_days') }}</dt>
                        <dd>{{ trans_choice('companies.days', (int) ($company->customer_payment_days ?? \App\Models\CompanyDetail::current()->payment_term_days), ['days' => $company->customer_payment_days ?? \App\Models\CompanyDetail::current()->payment_term_days]) }}@if ($company->customer_payment_days === null) <small class="muted">({{ __('companies.show.standard') }})</small>@endif</dd>
                    @endif
                    @if ($company->is_supplier)
                        <dt>{{ __('companies.fields.supplier_payment_days') }}</dt><dd>{{ $company->supplier_payment_days !== null ? trans_choice('companies.days', (int) ($company->supplier_payment_days), ['days' => $company->supplier_payment_days]) : '—' }}</dd>
                        <dt>{{ __('companies.fields.supplies') }}</dt><dd>{{ collect($company->supplies ?? [])->map(fn ($s) => __('companies.supplies.'.$s))->implode(', ') ?: '—' }}</dd>
                    @endif
                    <dt>{{ __('companies.fields.phone') }}</dt><dd>@if ($company->phone)@include('partials.phone', ['number' => $company->phone, 'country' => $company->country])@else—@endif</dd>
                    <dt>{{ __('companies.fields.email') }}</dt><dd>{{ $company->email ?? '—' }}</dd>
                    <dt>{{ __('companies.fields.website') }}</dt><dd>{{ $company->website ?? '—' }}</dd>
                </dl>
            </section>

            @if ($canArchive)
                <section class="card">
                    @if ($company->archived_at)
                        <form method="post" action="{{ route('companies.restore', $company) }}">@csrf<button class="btn" type="submit">{{ __('companies.show.restore') }}</button></form>
                    @else
                        <form method="post" action="{{ route('companies.archive', $company) }}" onsubmit="return confirm(@js(__('companies.show.archive_confirm', ['name' => $company->name])))">@csrf<button class="btn btn-danger" type="submit">{{ __('companies.show.archive') }}</button></form>
                        <p class="muted small">{{ __('companies.show.archive_hint') }}</p>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
