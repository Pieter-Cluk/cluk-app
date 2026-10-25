<x-layouts.app :title="__('website.downloads.who_title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('website.index', ['tab' => 'downloads']) }}">{{ mb_strtoupper(__('nav.items.website')) }} · {{ mb_strtoupper(__('website.downloads.title')) }}</a></div>
            <h1>{{ $doc ? $doc->label(null, 'title') : __('website.downloads.who_title') }}</h1>
            <p class="muted">{{ __('website.downloads.who_hint') }}</p>
        </div>
    </div>
    <form method="get" class="filters inline-form">
        <select name="doc" aria-label="{{ __('website.downloads.title') }}" onchange="this.form.submit()"><option value="">{{ __('website.downloads.all_docs') }}</option>@foreach ($docs as $x)<option value="{{ $x->id }}" @selected($doc?->id === $x->id)>{{ $x->label(null, 'title') }}</option>@endforeach</select>
        <label class="check"><input type="checkbox" name="who" value="known" @checked($known) onchange="this.form.submit()"> {{ __('website.downloads.only_known') }}</label>
    </form>
    <section class="card card-flush mt">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('website.downloads.cols.when') }}</th><th>{{ __('website.downloads.cols.who') }}</th><th>{{ __('website.downloads.cols.company') }}</th>@unless ($doc)<th>{{ __('website.downloads.cols.doc') }}</th>@endunless</tr></thead>
            <tbody>
                @forelse ($logs as $l)
                    <tr>
                        <td class="nowrap">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($l->name || $l->email)<b>{{ $l->name ?: $l->email }}</b>@if ($l->name && $l->email)<br><a href="mailto:{{ $l->email }}">{{ $l->email }}</a>@endif
                            @else<span class="muted">{{ __('website.downloads.anonymous') }}</span>@endif
                            @if ($l->account)<br><span class="pill pill-ok">{{ __('website.downloads.has_login') }}</span>@elseif ($l->contact)<br><span class="pill pill-info">{{ __('website.downloads.known_contact') }}</span>@endif
                        </td>
                        <td>@if ($l->company)<a href="{{ route('companies.show', $l->company).($l->contact_id ? '#contact-'.$l->contact_id : '') }}">{{ $l->company->name }}</a>@elseif ($l->company_name){{ $l->company_name }} <a class="small" href="{{ route('companies.create', ['name' => $l->company_name]) }}">+ {{ __('website.downloads.make_company') }}</a>@else—@endif</td>
                        @unless ($doc)<td>{{ $l->download?->label(null, 'title') }}</td>@endunless
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('website.downloads.nobody') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    <x-pager :items="$logs"/>
</x-layouts.app>
