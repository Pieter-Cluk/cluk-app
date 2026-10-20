<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Member;
use App\Notifications\MemberApproved;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff side of Afrit 6 members: new registrations to approve, members,
 * declined and blocked accounts. Passwords are never visible.
 */
class MemberAdminController extends Controller
{
    public const TABS = ['waiting', 'active', 'declined', 'blocked'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'waiting';
        $status = $tab === 'waiting' ? Member::PENDING : ($tab === 'active' ? Member::ACTIVE : ($tab === 'declined' ? Member::DECLINED : Member::BLOCKED));

        $counts = [];
        foreach (self::TABS as $t) {
            $counts[$t] = Member::where('status', $t === 'waiting' ? Member::PENDING : ($t === 'active' ? Member::ACTIVE : ($t === 'declined' ? Member::DECLINED : Member::BLOCKED)))->count();
        }

        return view('afrit6.staff.index', [
            'tab' => $tab,
            'counts' => $counts,
            'members' => Member::with('company')->where('status', $status)->latest()->paginate(50)->withQueryString(),
        ]);
    }

    public function show(Request $request, Member $member): View
    {
        $member->load(['company', 'decidedBy']);

        return view('afrit6.staff.show', [
            'member' => $member,
            'match' => $member->company ?? ($member->reg_vat_number ? Company::where('vat_number', $member->reg_vat_number)->first() : null),
            'canDecide' => $request->user()->hasRight('afrit6.approve_members'),
            'activity' => AuditLog::with('actor')->where('subject_type', $member->getMorphClass())->where('subject_id', $member->id)->latest('id')->limit(15)->get(),
        ]);
    }

    /** Approve: link (or create) the company and a contact, then the member can use the portal. */
    public function approve(Request $request, Member $member): RedirectResponse
    {
        if (! $member->hasVerifiedEmail() && ! $request->boolean('email_checked')) {
            throw ValidationException::withMessages(['email_checked' => __('afrit6.staff.confirm_email_first')]);
        }

        DB::transaction(function () use ($member) {
            $company = $member->company
                ?? ($member->reg_vat_number ? Company::where('vat_number', $member->reg_vat_number)->first() : null)
                ?? Company::create([
                    'name' => $member->reg_company_name ?: $member->name,
                    'vat_number' => $member->reg_vat_number,
                    'country' => $member->reg_country === 'XX' ? 'BE' : $member->reg_country,
                    'street' => $member->reg_address,
                    'email' => $member->email,
                    'phone' => $member->mobile,
                    'language' => $member->locale,
                    'is_customer' => true,
                    'is_supplier' => false,
                    'vat_regime' => 'auto',
                ]);
            if ($member->vies_status && $company->vies_status === null && $company->vat_number === $member->reg_vat_number) {
                $company->forceFill(['vies_status' => $member->vies_status, 'vies_checked_at' => now()])->save();
            }
            if (! $company->is_customer) {
                $company->update(['is_customer' => true]);
            }

            $contact = Contact::where('email', $member->email)->first() ?? Contact::create([
                'first_name' => $member->first_name, 'last_name' => $member->last_name, 'email' => $member->email,
                'mobile' => $member->mobile, 'language' => $member->locale, 'newsletter' => $member->newsletter,
            ]);
            $company->contacts()->syncWithoutDetaching([$contact->id => ['job_title' => null]]);

            $member->forceFill([
                'status' => Member::ACTIVE,
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'approved_at' => now(),
                'decided_by_id' => auth()->id(),
                'decline_reason' => null,
                'email_verified_at' => $member->email_verified_at ?? now(),
            ])->save();

            AuditLog::record('afrit6.member_approved', $member, ['company' => $company->name]);
        });

        $member->notify(new MemberApproved);

        return redirect()->route('afrit6.staff.show', $member)->with('status', __('afrit6.staff.approved', ['name' => $member->name]));
    }

    public function decline(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $member->forceFill(['status' => Member::DECLINED, 'decline_reason' => $data['reason'], 'decided_by_id' => $request->user()->id])->save();
        AuditLog::record('afrit6.member_declined', $member, ['reason' => $data['reason']]);

        return redirect()->route('afrit6.staff.show', $member)->with('status', __('afrit6.staff.declined', ['name' => $member->name]));
    }

    public function block(Member $member): RedirectResponse
    {
        $member->forceFill(['status' => Member::BLOCKED, 'remember_token' => null])->save();
        AuditLog::record('afrit6.member_blocked', $member);

        return redirect()->route('afrit6.staff.show', $member)->with('status', __('afrit6.staff.blocked', ['name' => $member->name]));
    }

    public function unblock(Member $member): RedirectResponse
    {
        $member->forceFill(['status' => $member->approved_at ? Member::ACTIVE : Member::PENDING])->save();
        AuditLog::record('afrit6.member_unblocked', $member);

        return redirect()->route('afrit6.staff.show', $member)->with('status', __('afrit6.staff.unblocked', ['name' => $member->name]));
    }

    /** The member gets an email to choose a new password; staff never see or set one. */
    public function sendReset(Member $member): RedirectResponse
    {
        Password::broker('members')->sendResetLink(['email' => $member->email]);
        AuditLog::record('afrit6.reset_link_sent', $member);

        return redirect()->route('afrit6.staff.show', $member)->with('status', __('afrit6.staff.reset_sent', ['email' => $member->email]));
    }
}
