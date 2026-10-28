<?php

namespace App\Http\Controllers\Site;

use App\Models\CompanyDetail;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The website's privacy policy (texts in lang/{locale}/privacy.php, company details from the settings). */
class PrivacyController extends SiteController
{
    /** The date of the current text: change it when the policy changes. */
    public const VERSION = '2026-10-28';

    public function show(Request $request): View
    {
        $this->locale($request);
        $co = CompanyDetail::query()->first();
        $vars = [
            'company' => trim(($co?->legal_name ?: 'CLUK').' '.($co?->legal_form ?? 'BV')),
            'address' => $co ? trim($co->street.', '.$co->postcode.' '.$co->city, ' ,') : 'Zulte',
            'vat' => $co?->vat_number ?: '—',
            'email' => $co?->email ?: 'info@cluk.live',
        ];

        return view('site.privacy', ['vars' => $vars, 'date' => \Illuminate\Support\Carbon::parse(self::VERSION)]);
    }
}
