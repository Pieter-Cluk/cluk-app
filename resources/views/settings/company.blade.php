<x-layouts.app :title="__('nav.items.company')">
    <div class="pagehead">
        <div>
            <div class="kicker">{{ mb_strtoupper(__('users.kicker')) }}</div>
            <h1>{{ __('nav.items.company') }}</h1>
            <p class="muted">{{ __('settings.company.intro') }}</p>
        </div>
    </div>
    @include('partials.flash')

    <form method="post" action="{{ route('settings.company.update') }}" class="split">
        @csrf @method('put')
        <div class="stack">
            <section class="card">
                <h2>{{ __('settings.company.legal') }}</h2>
                <div class="form-grid">
                    <label class="field"><span>{{ __('settings.company.legal_name') }}</span>
                        <input name="legal_name" value="{{ old('legal_name', $company->legal_name) }}" required maxlength="120"></label>
                    <label class="field"><span>{{ __('settings.company.legal_form') }}</span>
                        <select name="legal_form">
                            <option value="">—</option>
                            @foreach (\App\Models\CompanyDetail::LEGAL_FORMS as $form)
                                <option value="{{ $form }}" @selected(old('legal_form', $company->legal_form) === $form)>{{ $form }}</option>
                            @endforeach
                        </select></label>
                    <label class="field"><span>{{ __('settings.company.vat_number') }}</span>
                        <input name="vat_number" value="{{ old('vat_number', $company->vat_number) }}" maxlength="20" placeholder="BE0123456789"></label>
                    <div class="field"><span>{{ __('settings.company.enterprise_number') }}</span>
                        <div class="readonly">{{ $company->enterpriseNumber() ?? '—' }}</div>
                        <small>{{ __('settings.company.follows_vat') }}</small></div>
                    <label class="field"><span>{{ __('settings.company.rpr') }}</span>
                        <input name="rpr" value="{{ old('rpr', $company->rpr) }}" maxlength="120" placeholder="{{ __('settings.company.rpr_hint') }}"></label>
                    <div class="field"><span>{{ __('settings.company.peppol') }}</span>
                        <div class="readonly">{{ $company->peppolId() ?? '—' }}</div></div>
                </div>
            </section>

            <section class="card">
                <h2>{{ __('settings.company.address_contact') }}</h2>
                <div class="form-grid">
                    <label class="field span-2"><span>{{ __('settings.company.street') }}</span>
                        <input name="street" value="{{ old('street', $company->street) }}" maxlength="160"></label>
                    <label class="field"><span>{{ __('settings.company.postcode') }}</span>
                        <input name="postcode" value="{{ old('postcode', $company->postcode) }}" maxlength="20"></label>
                    <label class="field"><span>{{ __('settings.company.city') }}</span>
                        <input name="city" value="{{ old('city', $company->city) }}" maxlength="80"></label>
                    <label class="field"><span>{{ __('settings.company.country') }}</span>
                        <input name="country" value="{{ old('country', $company->country) }}" maxlength="2" required></label>
                    <label class="field"><span>{{ __('settings.company.warehouse') }}</span>
                        <input name="warehouse_address" value="{{ old('warehouse_address', $company->warehouse_address) }}" maxlength="255">
                        <small>{{ __('settings.company.warehouse_hint') }}</small></label>
                    <label class="field"><span>{{ __('settings.company.phone') }}</span>
                        <input name="phone" value="{{ old('phone', $company->phone) }}" maxlength="40" inputmode="tel"></label>
                    <label class="field"><span>{{ __('settings.company.email') }}</span>
                        <input type="email" name="email" value="{{ old('email', $company->email) }}" maxlength="190"></label>
                    <label class="field"><span>{{ __('settings.company.invoice_email') }}</span>
                        <input type="email" name="invoice_email" value="{{ old('invoice_email', $company->invoice_email) }}" maxlength="190"></label>
                    <label class="field"><span>{{ __('settings.company.website') }}</span>
                        <input name="website" value="{{ old('website', $company->website) }}" maxlength="190"></label>
                </div>
            </section>

            <section class="card">
                <h2>{{ __('settings.company.banks') }}</h2>
                <p class="muted">{{ __('settings.company.banks_intro') }}</p>
                <div class="table-wrap">
                    <table class="table table-tight">
                        <thead><tr>
                            <th>{{ __('settings.company.bank') }}</th><th>IBAN</th><th>BIC</th>
                            <th>{{ __('settings.company.main') }}</th><th>{{ __('settings.company.on_documents') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($banks as $b)
                                <tr>
                                    <td><input name="banks[{{ $b->id }}][bank]" value="{{ old("banks.{$b->id}.bank", $b->bank) }}" maxlength="80" aria-label="{{ __('settings.company.bank') }}"></td>
                                    <td><input name="banks[{{ $b->id }}][iban]" value="{{ old("banks.{$b->id}.iban", $b->formattedIban()) }}" maxlength="40" aria-label="IBAN"></td>
                                    <td><input name="banks[{{ $b->id }}][bic]" value="{{ old("banks.{$b->id}.bic", $b->bic) }}" maxlength="11" aria-label="BIC" size="11"></td>
                                    <td class="center"><input type="radio" name="main_bank" value="{{ $b->id }}" @checked($b->is_main) aria-label="{{ __('settings.company.main') }}"></td>
                                    <td class="center"><input type="checkbox" name="banks[{{ $b->id }}][on_documents]" value="1" @checked($b->on_documents) aria-label="{{ __('settings.company.on_documents') }}"></td>
                                </tr>
                            @endforeach
                            <tr>
                                <td><input name="banks[new][bank]" value="{{ old('banks.new.bank') }}" maxlength="80" placeholder="{{ __('settings.company.add_bank') }}" aria-label="{{ __('settings.company.bank') }}"></td>
                                <td><input name="banks[new][iban]" value="{{ old('banks.new.iban') }}" maxlength="40" placeholder="BE.. .... .... ...." aria-label="IBAN"></td>
                                <td><input name="banks[new][bic]" value="{{ old('banks.new.bic') }}" maxlength="11" aria-label="BIC" size="11"></td>
                                <td class="center"><input type="radio" name="main_bank" value="new" @checked($banks->isEmpty()) aria-label="{{ __('settings.company.main') }}"></td>
                                <td class="center"><input type="checkbox" name="banks[new][on_documents]" value="1" checked aria-label="{{ __('settings.company.on_documents') }}"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="muted small">{{ __('settings.company.remove_bank') }}</p>
            </section>

            <section class="card">
                <h2>{{ __('settings.company.defaults') }}</h2>
                <div class="form-grid form-grid-3">
                    <label class="field"><span>{{ __('settings.company.payment_term') }}</span>
                        <input type="number" name="payment_term_days" value="{{ old('payment_term_days', $company->payment_term_days) }}" min="0" max="365" required></label>
                    <label class="field"><span>{{ __('settings.company.quote_valid') }}</span>
                        <input type="number" name="quote_valid_days" value="{{ old('quote_valid_days', $company->quote_valid_days) }}" min="1" max="365" required></label>
                    <label class="field"><span>{{ __('settings.company.option_days') }}</span>
                        <input type="number" name="option_days" value="{{ old('option_days', $company->option_days) }}" min="0" max="60" required></label>
                    <label class="field span-3"><span>{{ __('settings.company.footer') }}</span>
                        <input name="footer_line" value="{{ old('footer_line', $company->footer_line) }}" maxlength="255"></label>
                </div>
                <p class="muted small">{{ __('settings.company.later') }}</p>
            </section>

            <div class="form-actions"><button class="primary" type="submit">{{ __('settings.save') }}</button></div>
        </div>

        <aside class="stack">
            <section class="card doc-preview" aria-label="{{ __('settings.company.preview') }}">
                <div class="kicker">{{ mb_strtoupper(__('settings.company.preview')) }}</div>
                <img src="{{ asset('images/cluk-logo.png') }}" alt="CLUK" height="30">
                <p><b>{{ $company->legal_name }} {{ $company->legal_form }}</b><br>
                    {{ $company->address() }}<br>
                    {{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}<br>
                    {{ $company->website }}</p>
                <hr>
                <p class="small">{{ __('settings.company.preview_terms', ['pay' => $company->payment_term_days, 'valid' => $company->quote_valid_days, 'option' => $company->option_days]) }}</p>
                <hr>
                <p class="small muted">{{ $company->legal_name }} {{ $company->legal_form }} · {{ __('settings.company.vat_short') }} {{ $company->vat_number ?? '—' }}@if ($company->rpr) · RPR {{ $company->rpr }}@endif
                    @if ($main = $banks->firstWhere('is_main', true)) · {{ $main->formattedIban() }}@endif
                    <br>{{ $company->footer_line }}</p>
            </section>
        </aside>
    </form>
</x-layouts.app>
