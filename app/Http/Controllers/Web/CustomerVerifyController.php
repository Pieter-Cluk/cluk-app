<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\CustomerAccount;
use App\Services\Web\CustomerAuth;
use Illuminate\Http\RedirectResponse;

/** The "confirm my email" link from the registration email (signed, 48 hours). */
class CustomerVerifyController extends Controller
{
    public function __invoke(CustomerAccount $account, string $hash): RedirectResponse
    {
        abort_unless(CustomerAuth::verify($account, $hash), 403);

        return redirect()->to(CustomerAuth::siteUrl('site.account.login', ['verified' => 1], '/account/login'))
            ->with('status', __('web.api.verified'));
    }
}
