<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\CompanyDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Settings › Company details: CLUK's details on every document. */
class CompanyDetailsController extends Controller
{
    public function edit(): View
    {
        return view('settings.company', [
            'company' => CompanyDetail::current(),
            'banks' => BankAccount::orderByDesc('is_main')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = CompanyDetail::current();

        $request->merge(['vat_number' => strtoupper(preg_replace('/[\s.\-]/', '', (string) $request->input('vat_number')))]);
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:120'],
            'legal_form' => ['nullable', Rule::in(CompanyDetail::LEGAL_FORMS)],
            'vat_number' => ['nullable', 'regex:/^[A-Z]{2}[0-9A-Z]{2,13}$/'],
            'rpr' => ['nullable', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:160'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'size:2'],
            'warehouse_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'invoice_email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'string', 'max:190'],
            'payment_term_days' => ['required', 'integer', 'between:0,365'],
            'quote_valid_days' => ['required', 'integer', 'between:1,365'],
            'option_days' => ['required', 'integer', 'between:0,60'],
            'discount_limit' => ['nullable', 'numeric', 'between:0,90'],
            'footer_line' => ['nullable', 'string', 'max:255'],
            'banks' => ['array'],
            'banks.*.bank' => ['nullable', 'string', 'max:80'],
            'banks.*.iban' => ['nullable', 'string', 'max:40'],
            'banks.*.bic' => ['nullable', 'string', 'max:11'],
            'main_bank' => ['nullable', 'string'],
        ]);
        $data['country'] = strtoupper($data['country']);
        if (isset($data['discount_limit'])) {
            $data['discount_limit_bp'] = (int) round($data['discount_limit'] * 100);
        }
        unset($data['discount_limit']);

        // Bank rows: existing ids, plus "new" for the empty row. An emptied IBAN removes the row.
        $banks = collect($request->input('banks', []))->map(fn ($b, $key) => [
            'key' => (string) $key,
            'bank' => trim((string) ($b['bank'] ?? '')) ?: null,
            'iban' => strtoupper(preg_replace('/\s+/', '', (string) ($b['iban'] ?? ''))),
            'bic' => strtoupper(trim((string) ($b['bic'] ?? ''))) ?: null,
            'on_documents' => (bool) ($b['on_documents'] ?? false),
        ]);
        foreach ($banks as $b) {
            if ($b['iban'] !== '' && ! BankAccount::validIban($b['iban'])) {
                throw ValidationException::withMessages(["banks.{$b['key']}.iban" => __('settings.errors.iban', ['iban' => $b['iban']])]);
            }
        }

        DB::transaction(function () use ($company, $data, $banks, $request) {
            $fields = collect($data)->except(['banks', 'main_bank'])->all();
            $company->fill($fields);
            $changes = $company->getDirty();
            unset($changes['updated_at']);
            $company->save();
            if ($changes !== []) {
                AuditLog::record('company.updated', $company, ['changes' => $changes]);
            }

            $mainKey = (string) $request->input('main_bank', '');
            foreach ($banks as $b) {
                $existing = ctype_digit($b['key']) ? BankAccount::find((int) $b['key']) : null;
                if ($b['iban'] === '') {
                    if ($existing) {
                        AuditLog::record('company.bank_removed', $company, ['iban' => $existing->iban]);
                        $existing->delete();
                    }

                    continue;
                }
                $account = $existing ?? new BankAccount;
                $account->fill([
                    'bank' => $b['bank'], 'iban' => $b['iban'], 'bic' => $b['bic'],
                    'on_documents' => $b['on_documents'], 'is_main' => $mainKey === $b['key'],
                ]);
                if (! $account->exists || $account->isDirty(['iban', 'bic', 'bank', 'on_documents'])) {
                    AuditLog::record($account->exists ? 'company.bank_changed' : 'company.bank_added', $company, ['iban' => $account->iban]);
                }
                $account->save();
            }

            // Always exactly one main account when there is any.
            if (! BankAccount::where('is_main', true)->exists() && ($first = BankAccount::orderBy('id')->first())) {
                $first->update(['is_main' => true]);
            }
        });

        return redirect()->route('settings.company.edit')->with('status', __('settings.flash.company_saved'));
    }
}
