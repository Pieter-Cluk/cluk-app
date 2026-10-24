<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Credit\NbbAccounts;
use App\Services\Credit\RegistryImport;
use Illuminate\Http\RedirectResponse;

/** Customer card › Credit check › "Check now": a live lookup in the company register (KBO) and the annual accounts (NBB). */
class CreditController extends Controller
{
    public function refresh(Company $company): RedirectResponse
    {
        $ok = RegistryImport::refresh($company);
        $nbb = NbbAccounts::available() ? NbbAccounts::refresh($company) : null;

        $msg = $ok ? __('credit.flash.refreshed') : __('credit.flash.not_reached');
        if (NbbAccounts::available()) {
            $msg .= ' '.($nbb === null ? __('credit.flash.nbb_not_reached') : trans_choice('credit.flash.nbb_years', $nbb, ['n' => $nbb]));
        }

        return redirect()->to(route('companies.show', $company).'#credit')->with('status', $msg);
    }
}
