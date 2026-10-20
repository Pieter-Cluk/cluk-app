<x-layouts.app :title="__('nav.items.afrit6')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('nav.groups.operations')) }}</div>
            <h1>{{ __('nav.items.afrit6') }}</h1>
            <p class="muted">{{ __('afrit6.staff.intro') }}</p>
        </div>
        <a class="btn" href="{{ route('afrit6.login') }}" target="_blank" rel="noopener">{{ __('afrit6.staff.open_portal') }}</a>
    </div>
    @include('partials.flash')

    <nav class="tabs" aria-label="{{ __('nav.items.afrit6') }}">
        @foreach (\App\Http\Controllers\Afrit6\MemberAdminController::TABS as $t)
            <a href="{{ route('afrit6.staff.index', $t === 'waiting' ? [] : ['tab' => $t]) }}" @class(['tab', 'tab-on' => $tab === $t]) @if ($tab === $t) aria-current="page" @endif>{{ __('afrit6.staff.tabs.'.$t) }} <span class="count">{{ $counts[$t] }}</span></a>
        @endforeach
    </nav>

    <section class="card card-flush">
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th>{{ __('afrit6.staff.member') }}</th><th>{{ __('afrit6.staff.company') }}</th><th>{{ __('afrit6.fields.vat_number') }}</th>
                    <th>{{ __('afrit6.staff.email_confirmed') }}</th><th>{{ __('afrit6.staff.registered') }}</th>
                </tr></thead>
                <tbody>
                    @forelse ($members as $m)
                        <tr>
                            <td><a class="person" href="{{ route('afrit6.staff.show', $m) }}"><span class="av">{{ $m->initials }}</span><span><b>{{ $m->name }}</b><small>{{ $m->email }}</small></span></a></td>
                            <td>@if ($m->company)<a href="{{ route('companies.show', $m->company) }}">{{ $m->company->name }}</a>@else {{ $m->reg_company_name ?? '—' }} @endif</td>
                            <td class="nowrap">{{ $m->formattedRegVat() ?: '—' }}@if ($m->vies_status)<br><small @class(['vies', 'vies-'.$m->vies_status])>{{ __('companies.vies.short_'.$m->vies_status) }}</small>@endif</td>
                            <td>{{ $m->email_verified_at ? __('companies.yes') : __('companies.no') }}</td>
                            <td class="muted nowrap">{{ $m->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">{{ __('afrit6.staff.empty_'.$tab) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <x-pager :items="$members"/>
</x-layouts.app>
