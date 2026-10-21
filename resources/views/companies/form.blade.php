@php($editing = $company->exists)
<x-layouts.app :title="$editing ? $company->name : __('companies.form.new_title')">
    <div class="pagehead">
        <div>
            <div class="kicker"><a href="{{ route('companies.index') }}">{{ mb_strtoupper(__('nav.items.companies')) }}</a></div>
            <h1>{{ $editing ? $company->name : __('companies.form.new_title') }}</h1>
            @unless ($editing)<p class="muted">{{ __('companies.form.new_intro') }}</p>@endunless
        </div>
    </div>
    @include('partials.flash')

    <form class="card" method="post" action="{{ $editing ? route('companies.update', $company) : route('companies.store') }}">
        @csrf @if ($editing) @method('put') @endif

        <fieldset class="rel-pick">
            <legend>{{ __('companies.form.relationship') }}</legend>
            <label class="check"><input type="checkbox" name="is_customer" value="1" @checked(old('is_customer', $company->is_customer))><span><span class="rel-dot rel-customer" aria-hidden="true"></span>{{ __('companies.relationship.customer') }}</span></label>
            <label class="check"><input type="checkbox" name="is_supplier" value="1" @checked(old('is_supplier', $company->is_supplier))><span><span class="rel-dot rel-supplier" aria-hidden="true"></span>{{ __('companies.relationship.supplier') }}</span></label>
        </fieldset>

        <div class="form-grid">
            <label class="field"><span>{{ __('companies.fields.name') }}</span>
                <input name="name" value="{{ old('name', $company->name) }}" required maxlength="160" autofocus></label>
            <label class="field"><span>{{ __('companies.fields.legal_form') }}</span>
                <input name="legal_form" value="{{ old('legal_form', $company->legal_form) }}" maxlength="20" list="legal-forms">
                <datalist id="legal-forms">@foreach (\App\Models\Company::LEGAL_FORMS as $f)<option value="{{ $f }}">@endforeach</datalist></label>
            <label class="field"><span>{{ __('companies.fields.country') }}</span>
                <input name="country" value="{{ old('country', $company->country) }}" required maxlength="2" placeholder="BE">
                <small>{{ __('companies.form.country_hint') }}</small></label>
            <label class="field"><span>{{ __('companies.fields.vat_number') }}</span>
                <input name="vat_number" value="{{ old('vat_number', $company->formattedVat()) }}" maxlength="24" placeholder="BE 0123.456.789">
                <small>{{ __('companies.form.vat_hint') }}</small></label>
            <label class="field span-2"><span>{{ __('companies.fields.street') }}</span>
                <input name="street" value="{{ old('street', $company->street) }}" maxlength="160"></label>
            <label class="field"><span>{{ __('companies.fields.postcode') }}</span>
                <input name="postcode" value="{{ old('postcode', $company->postcode) }}" maxlength="20"></label>
            <label class="field"><span>{{ __('companies.fields.city') }}</span>
                <input name="city" value="{{ old('city', $company->city) }}" maxlength="80"></label>
            <label class="field"><span>{{ __('companies.fields.phone') }}</span>
                <input name="phone" value="{{ old('phone', $company->phone) }}" maxlength="40" inputmode="tel"></label>
            <label class="field"><span>{{ __('companies.fields.email') }}</span>
                <input type="email" name="email" value="{{ old('email', $company->email) }}" maxlength="190"></label>
            <label class="field"><span>{{ __('companies.fields.website') }}</span>
                <input name="website" value="{{ old('website', $company->website) }}" maxlength="190"></label>
            <label class="field"><span>{{ __('companies.fields.language') }}</span>
                <select name="language">
                    @foreach (config('cluk.locales') as $code => $name)<option value="{{ $code }}" @selected(old('language', $company->language) === $code)>{{ $name }}</option>@endforeach
                </select>
                <small>{{ __('companies.form.language_hint') }}</small></label>
            <label class="field"><span>{{ __('companies.fields.account_owner_id') }}</span>
                <select name="account_owner_id">
                    <option value="">{{ __('users.fields.nobody') }}</option>
                    @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('account_owner_id', $company->account_owner_id) === (string) $o->id)>{{ $o->name }}</option>@endforeach
                </select></label>
            <label class="field"><span>{{ __('companies.fields.customer_payment_days') }}</span>
                <input type="number" name="customer_payment_days" value="{{ old('customer_payment_days', $company->customer_payment_days) }}" min="0" max="365" placeholder="{{ \App\Models\CompanyDetail::current()->payment_term_days }}"></label>
            <label class="check"><input type="hidden" name="po_required" value="0"><input type="checkbox" name="po_required" value="1" @checked(old('po_required', $company->po_required))> {{ __('companies.fields.po_required') }}</label>
            <label class="field"><span>{{ __('companies.fields.supplier_payment_days') }}</span>
                <input type="number" name="supplier_payment_days" value="{{ old('supplier_payment_days', $company->supplier_payment_days) }}" min="0" max="365"></label>
            <fieldset class="field span-2 plain">
                <legend>{{ __('companies.fields.supplies') }}</legend>
                <div class="checks">
                    @foreach (\App\Models\Company::SUPPLIES as $s)
                        <label class="check"><input type="checkbox" name="supplies[]" value="{{ $s }}" @checked(in_array($s, old('supplies', $company->supplies ?? []), true))><span>{{ __('companies.supplies.'.$s) }}</span></label>
                    @endforeach
                </div>
                <small>{{ __('companies.form.supplies_hint') }}</small>
            </fieldset>
            <label class="field span-2"><span>{{ __('companies.fields.notes') }}</span>
                <textarea name="notes" rows="3" maxlength="5000">{{ old('notes', $company->notes) }}</textarea></label>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ $editing ? route('companies.show', $company) : route('companies.index') }}">{{ __('settings.cancel') }}</a>
            <button class="primary" type="submit">{{ $editing ? __('settings.save') : __('companies.form.create') }}</button>
        </div>
    </form>
</x-layouts.app>
