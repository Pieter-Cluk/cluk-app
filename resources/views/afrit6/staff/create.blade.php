<x-layouts.app :title="__('afrit6.manage.add')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('afrit6.staff.index') }}">{{ mb_strtoupper(__('nav.items.afrit6')) }}</a></div>
            <h1>{{ __('afrit6.manage.add') }}</h1>
            <p class="muted">{{ __('afrit6.manage.add_intro') }}</p>
        </div>
    </div>
    @include('partials.flash')
    <section class="card">
        <form method="post" action="{{ route('afrit6.staff.manage.store') }}">
            @csrf
            @include('afrit6.staff._member-form', ['member' => null])
            <label class="check mt"><input type="hidden" name="send_mail" value="0"><input type="checkbox" name="send_mail" value="1" checked><span>{{ __('afrit6.manage.send_mail') }}</span></label>
            <div class="form-actions"><button class="primary" type="submit">{{ __('afrit6.manage.add_go') }}</button></div>
        </form>
    </section>
</x-layouts.app>
