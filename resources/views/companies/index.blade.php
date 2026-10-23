@php($canEditContacts = auth()->user()->canUseModule('companies', 2))
@php($tabs = ['all' => '', 'customers' => 'customer', 'suppliers' => 'supplier', 'both' => 'both', 'contacts' => ''])
<x-layouts.app :title="__('nav.items.companies')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.commercial')) }}</div>
            <h1>{{ __('nav.items.companies') }}</h1>
        </div>
        <div class="inline-form">
            <a class="btn" href="{{ route('web-accounts.index') }}">{{ __('web.staff.title') }}@php($pend = \App\Models\Web\CustomerAccount::where('status', 'pending')->whereNotNull('email_verified_at')->count())@if ($pend) <span class="pill pill-warn">{{ $pend }}</span>@endif</a>
            @if ($canEdit)
                <a class="primary" href="{{ route('companies.create', $view === 'suppliers' ? ['type' => 'supplier'] : []) }}">{{ __($view === 'suppliers' ? 'companies.index.new_supplier' : 'companies.index.new_company') }}</a>
            @endif
        </div>
    </div>
    @include('partials.flash')

    <nav class="tabs" aria-label="{{ __('nav.items.companies') }}">
        @foreach ($tabs as $key => $dot)
            <a href="{{ route('companies.index', $key === 'all' ? [] : ['view' => $key]) }}" @class(['tab', 'tab-on' => $view === $key]) @if ($view === $key) aria-current="page" @endif>
                @if ($dot)<span class="rel-dot rel-{{ $dot }}" aria-hidden="true"></span>@endif
                {{ __('companies.tabs.'.$key) }} <span class="count">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <form class="filters" method="get" action="{{ route('companies.index') }}">
        @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
        <label class="search field-search">
            <x-icon name="search"/>
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __($view === 'contacts' ? 'companies.index.search_contacts' : 'companies.index.search') }}" aria-label="{{ __('companies.index.search') }}">
        </label>
        @if ($view === 'all')
            <select name="rel" aria-label="{{ __('companies.index.relationship') }}">
                <option value="">{{ __('companies.index.all_relationships') }}</option>
                @foreach (['customer', 'supplier', 'both'] as $r)
                    <option value="{{ $r }}" @selected($filters['rel'] === $r)>{{ __('companies.relationship.'.$r) }}</option>
                @endforeach
            </select>
        @endif
        <select name="country" aria-label="{{ __('companies.fields.country') }}">
            <option value="">{{ __('companies.index.all_countries') }}</option>
            @foreach ($countries as $c)<option value="{{ $c }}" @selected($filters['country'] === $c)>{{ $c }}</option>@endforeach
        </select>
        @if ($view !== 'contacts')
            <select name="owner" aria-label="{{ __('companies.fields.account_owner_id') }}">
                <option value="">{{ __('companies.index.all_owners') }}</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected($filters['owner'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
        @endif
        @if ($view === 'suppliers')
            <select name="supplies" aria-label="{{ __('companies.fields.supplies') }}">
                <option value="">{{ __('companies.index.all_supplies') }}</option>
                @foreach (\App\Models\Company::SUPPLIES as $s)<option value="{{ $s }}" @selected($filters['supplies'] === $s)>{{ __('companies.supplies.'.$s) }}</option>@endforeach
            </select>
        @endif
        <button class="btn" type="submit">{{ __('users.index.filter') }}</button>
        @if ($q !== '' || array_filter($filters))
            <a class="linkbtn" href="{{ route('companies.index', $view === 'all' ? [] : ['view' => $view]) }}">{{ __('companies.index.clear') }}</a>
        @endif
    </form>

    @if ($view === 'contacts')
        <section class="card card-flush">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('companies.contacts.name') }}</th><th>{{ __('companies.contacts.job_title') }}</th><th>{{ __('companies.contacts.company') }}</th>
                        <th>{{ __('companies.contacts.phone') }}</th><th>{{ __('companies.contacts.email') }}</th><th>{{ __('companies.contacts.newsletter') }}</th>@if ($canEditContacts)<th><span class="sr-only">{{ __('settings.edit') }}</span></th>@endif
                    </tr></thead>
                    <tbody>
                        @forelse ($contacts as $p)
                            @php($first = $p->companies->first())
                            <tr>
                                <td><span class="person"><x-avatar :who="$p"/><b>{{ $p->name }}</b></span></td>
                                <td>{{ $first?->pivot->job_title ?? '—' }}</td>
                                <td>
                                    @foreach ($p->companies as $c)
                                        <a class="rel-link" href="{{ route('companies.show', $c) }}"><span class="rel-dot rel-{{ $c->relationshipKey() }}" aria-hidden="true"></span>{{ $c->name }}</a>@if (! $loop->last)<br>@endif
                                    @endforeach
                                </td>
                                <td class="nowrap">{{ $p->mobile ?? $p->phone ?? '—' }}</td>
                                <td>@if ($p->email)<a href="mailto:{{ $p->email }}">{{ $p->email }}</a>@else — @endif</td>
                                <td><span @class(['pill', 'pill-ok' => $p->newsletter, 'pill-off' => ! $p->newsletter])>{{ __($p->newsletter ? 'companies.yes' : 'companies.no') }}</span></td>
                                @if ($canEditContacts)<td class="num">@if ($first)<a class="btn btn-sm" href="{{ route('companies.contacts.edit', [$first, $p, 'back' => request()->fullUrl()]) }}">{{ __('settings.edit') }}</a>@endif</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">{{ __('companies.index.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <x-pager :items="$contacts"/>
    @else
        <section class="card card-flush">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('companies.index.company') }}</th><th>{{ __('companies.fields.vat_number') }}</th>
                        @if ($view === 'suppliers')
                            <th>{{ __('companies.fields.supplies') }}</th><th>{{ __('companies.fields.supplier_payment_days') }}</th>
                        @else
                            <th>{{ __('companies.index.relationship') }}</th>
                        @endif
                        <th>{{ __('companies.fields.account_owner_id') }}</th><th>{{ __('companies.index.contacts') }}</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($companies as $c)
                            @php($rel = $c->relationshipKey())
                            <tr class="rel-row rel-row-{{ $rel }}">
                                <td>
                                    <a class="person" href="{{ route('companies.show', $c) }}">
                                        <x-logo :company="$c" :rel="$rel"/>
                                        <span><b>{{ $c->name }}</b><small>{{ trim(($c->city ? $c->city.', ' : '').$c->country) }}</small></span>
                                    </a>
                                </td>
                                <td class="nowrap">
                                    {{ $c->formattedVat() ?: '—' }}
                                    @if ($c->vies_status)<br><small @class(['vies', 'vies-'.$c->vies_status])>{{ __('companies.vies.short_'.$c->vies_status) }}</small>@endif
                                </td>
                                @if ($view === 'suppliers')
                                    <td>{{ collect($c->supplies ?? [])->map(fn ($s) => __('companies.supplies.'.$s))->implode(', ') ?: '—' }}</td>
                                    <td>{{ $c->supplier_payment_days !== null ? trans_choice('companies.days', (int) ($c->supplier_payment_days), ['days' => $c->supplier_payment_days]) : '—' }}</td>
                                @else
                                    <td><span class="badge badge-{{ $rel }}">{{ __('companies.relationship.'.$rel) }}</span></td>
                                @endif
                                <td>{{ $c->accountOwner?->name ?? '—' }}</td>
                                <td>{{ $c->contacts_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">{{ __('companies.index.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <x-pager :items="$companies"/>
    @endif
</x-layouts.app>
