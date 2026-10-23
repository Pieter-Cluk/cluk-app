<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Web\CustomerAccount;
use App\Services\Web\CustomerAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Companies › Website logins: approve new registrations, block, send a password link. */
class WebAccountController extends Controller
{
    public const TABS = ['pending', 'active', 'blocked'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('s'), self::TABS, true) ? $request->query('s') : 'pending';
        $accounts = CustomerAccount::with(['company', 'contact', 'approvedBy'])->where('status', $tab)
            ->orderByRaw('email_verified_at is null')->latest()->paginate(40)->withQueryString();

        return view('companies.web-accounts', [
            'tab' => $tab, 'accounts' => $accounts,
            'counts' => collect(self::TABS)->mapWithKeys(fn ($t) => [$t => CustomerAccount::where('status', $t)->count()]),
            'suggest' => $tab === 'pending' ? $accounts->getCollection()->mapWithKeys(fn ($a) => [$a->id => $this->suggestions($a)]) : collect(),
            'canEdit' => $request->user()->canUseModule('companies', 2),
        ]);
    }

    /** Likely companies for a registration: same VAT number, else a similar name; and a contact with the same email. */
    private function suggestions(CustomerAccount $a): array
    {
        $companies = collect();
        if ($a->vat_number) {
            $vat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $a->vat_number));
            $companies = Company::where('vat_number', $vat)->limit(3)->get();
        }
        if ($companies->isEmpty() && $a->company_name) {
            $companies = Company::where('name', 'like', '%'.Str::limit($a->company_name, 40, '').'%')->limit(5)->get();
        }
        $contact = Contact::with('companies')->where('email', $a->email)->first();
        if ($contact) {
            $companies = $companies->merge($contact->companies)->unique('id');
        }

        return ['companies' => $companies->values(), 'contact' => $contact];
    }

    public function approve(Request $request, CustomerAccount $account): RedirectResponse
    {
        abort_unless($account->status === CustomerAccount::PENDING, 409);
        $d = $request->validate(['company' => ['required', 'string', 'regex:/^(new|none|\d+)$/'], 'contact_id' => ['nullable', 'integer', 'exists:contacts,id']]);
        $company = ctype_digit($d['company']) ? Company::findOrFail((int) $d['company']) : null;
        CustomerAuth::approve($account, $request->user(), $company, isset($d['contact_id']) ? Contact::find($d['contact_id']) : null, $d['company'] === 'new');

        return back()->with('status', __('web.staff.approved', ['email' => $account->email]));
    }

    public function block(Request $request, CustomerAccount $account): RedirectResponse
    {
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        CustomerAuth::block($account, $request->user(), $d['reason'] ?? null);

        return back()->with('status', __('web.staff.blocked', ['email' => $account->email]));
    }

    public function unblock(Request $request, CustomerAccount $account): RedirectResponse
    {
        CustomerAuth::unblock($account, $request->user());

        return back()->with('status', __('web.staff.unblocked', ['email' => $account->email]));
    }

    public function sendReset(Request $request, CustomerAccount $account): RedirectResponse
    {
        abort_if($account->status === CustomerAccount::BLOCKED, 409);
        $ok = CustomerAuth::sendReset($account);

        return back()->with($ok ? 'status' : 'error', $ok ? __('web.staff.reset_sent', ['email' => $account->email]) : __('web.staff.mail_failed'));
    }
}
