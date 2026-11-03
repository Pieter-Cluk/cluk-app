{{-- Member details (owner only). $member is null for a new member. --}}
@php($m = $member ?? null)
<datalist id="a6-companies">@foreach ($companies as $c)<option value="{{ $c }}"></option>@endforeach</datalist>
<div class="form-grid">
    <label class="field"><span>{{ __('afrit6.fields.first_name') }}</span><input name="first_name" value="{{ old('first_name', $m?->first_name) }}" required maxlength="80"></label>
    <label class="field"><span>{{ __('afrit6.fields.last_name') }}</span><input name="last_name" value="{{ old('last_name', $m?->last_name) }}" required maxlength="80"></label>
    <label class="field"><span>{{ __('afrit6.fields.email') }}</span><input type="email" name="email" value="{{ old('email', $m?->email) }}" required maxlength="190"></label>
    <label class="field"><span>{{ __('afrit6.fields.mobile') }}</span><input name="mobile" value="{{ old('mobile', $m?->mobile) }}" maxlength="40"></label>
    <label class="field span-2"><span>{{ __('afrit6.manage.company') }}</span><input name="company" list="a6-companies" value="{{ old('company', $m ? ($m->company ? $m->company->name.' · #'.$m->company_id : $m->reg_company_name) : '') }}" required maxlength="190" autocomplete="off"><small>{{ __('afrit6.manage.company_hint') }}</small></label>
    <label class="field"><span>{{ __('afrit6.fields.vat_number') }}</span><input name="reg_vat_number" value="{{ old('reg_vat_number', $m?->reg_vat_number) }}" maxlength="30"></label>
    <label class="field"><span>{{ __('afrit6.fields.country') }}</span><input name="reg_country" value="{{ old('reg_country', $m?->reg_country ?? 'BE') }}" maxlength="2" size="2"></label>
    <label class="field span-2"><span>{{ __('afrit6.fields.address') }}</span><input name="reg_address" value="{{ old('reg_address', $m?->reg_address) }}" maxlength="255"></label>
    <label class="field"><span>{{ __('afrit6.staff.language') }}</span>
        <select name="locale">@foreach (config('cluk.locales') as $code => $name)<option value="{{ $code }}" @selected(old('locale', $m?->locale ?? 'nl') === $code)>{{ $name }}</option>@endforeach</select></label>
    @if ($m)
        <label class="field"><span>{{ __('afrit6.manage.status') }}</span>
            <select name="status">@foreach (['pending', 'active', 'declined', 'blocked'] as $st)<option value="{{ $st }}" @selected(old('status', $m->status) === $st)>{{ __('afrit6.staff.status.'.$st) }}</option>@endforeach</select></label>
    @endif
    <label class="check span-2"><input type="checkbox" name="newsletter" value="1" @checked(old('newsletter', $m?->newsletter))><span>{{ __('afrit6.staff.newsletter') }}</span></label>
</div>
