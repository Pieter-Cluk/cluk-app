{{-- Contact fields; $c is the contact (or a new one), $pivot its link to this company. --}}
<div class="form-grid">
    <label class="field"><span>{{ __('companies.contacts.first_name') }}</span><input name="first_name" value="{{ $c->first_name }}" required maxlength="80"></label>
    <label class="field"><span>{{ __('companies.contacts.last_name') }}</span><input name="last_name" value="{{ $c->last_name }}" maxlength="80"></label>
    <label class="field"><span>{{ __('companies.contacts.job_title') }}</span><input name="job_title" value="{{ $pivot?->job_title }}" maxlength="120"></label>
    <label class="field"><span>{{ __('companies.contacts.email') }}</span><input type="email" name="email" value="{{ $c->email }}" maxlength="190"></label>
    <label class="field"><span>{{ __('companies.contacts.phone') }}</span><input name="phone" value="{{ $c->phone }}" maxlength="40" inputmode="tel"></label>
    <label class="field"><span>{{ __('companies.contacts.mobile') }}</span><input name="mobile" value="{{ $c->mobile }}" maxlength="40" inputmode="tel"></label>
    <label class="field"><span>{{ __('companies.fields.language') }}</span>
        <select name="language"><option value="">{{ __('companies.contacts.same_as_company') }}</option>
            @foreach (config('cluk.locales') as $code => $name)<option value="{{ $code }}" @selected($c->language === $code)>{{ $name }}</option>@endforeach
        </select></label>
    <div class="field">
        <label class="check"><input type="checkbox" name="is_main" value="1" @checked($pivot?->is_main)><span>{{ __('companies.contacts.is_main') }}</span></label>
        <label class="check"><input type="checkbox" name="is_billing" value="1" @checked($pivot?->is_billing)><span>{{ __('companies.contacts.is_billing') }}</span></label>
        <label class="check"><input type="checkbox" name="newsletter" value="1" @checked($c->newsletter)><span>{{ __('companies.contacts.newsletter_consent') }}</span></label>
    </div>
</div>
