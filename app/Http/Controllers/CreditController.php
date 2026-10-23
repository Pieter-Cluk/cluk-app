<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Credit\RegistryImport;
use Illuminate\Http\RedirectResponse;

/** Customer card › Credit check › "Check now": a live lookup in the company register (KBO). */
class CreditController extends Controller
{
    public function refresh(Company $company): RedirectResponse
    {
        $ok = RegistryImport::refresh($company);

        return redirect()->to(route('companies.show', $company).'#credit')
            ->with('status', $ok ? __('credit.flash.refreshed') : __('credit.flash.not_reached'));
    }
}
