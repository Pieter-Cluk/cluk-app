<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Web\CustomerAccount;
use App\Services\Web\ApprovalCheck;
use App\Services\Web\CustomerAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'suggest' => $tab === 'pending' ? $accounts->getCollection()->mapWithKeys(fn ($a) => [$a->id => ApprovalCheck::for($a)]) : collect(),
            'canEdit' => $request->user()->canUseModule('companies', 2),
        ]);
    }

    public function approve(Request $request, CustomerAccount $account): RedirectResponse
    {
        abort_unless($account->status === CustomerAccount::PENDING, 409);
        $d = $request->validate(['company' => ['required', 'string', 'regex:/^(new|none|\d+)$/'], 'contact_id' => ['nullable', 'integer', 'exists:contacts,id']]);
        $company = ctype_digit($d['company']) ? Company::findOrFail((int) $d['company']) : null;
        CustomerAuth::approve($account, $request->user(), $company, isset($d['contact_id']) ? Contact::find($d['contact_id']) : null, $d['company'] === 'new');

        return back()->with('status', __('web.staff.approved', ['email' => $account->email]));
    }

    /** Confirm every registration whose checks are all fine (checked again now), with the proposed company and contact. */
    public function approveReady(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']])['ids'];
        $n = 0;
        foreach (CustomerAccount::whereIn('id', $ids)->where('status', CustomerAccount::PENDING)->get() as $a) {
            $c = ApprovalCheck::for($a);
            if ($c['ready']) {
                CustomerAuth::approve($a, $request->user(), $c['company'], $c['contact']);
                $n++;
            }
        }

        return back()->with('status', trans_choice('web.staff.approved_n', $n, ['n' => $n]));
    }

    /** What this login may do for its company: main contact, order, or look only. */
    public function role(Request $request, CustomerAccount $account): RedirectResponse
    {
        $role = $request->validate(['role' => ['required', Rule::in(CustomerAccount::ROLES)]])['role'];
        abort_unless($account->company_id, 409);
        $old = $account->role;
        $account->forceFill(['role' => $role])->save();
        AuditLog::record('web.colleague_role', $account, ['email' => $account->email, 'role' => $old.' → '.$role], $request->user());

        return back()->with('status', __('web.colleagues.saved'));
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
