<?php

namespace App\Services\Afrit6;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** A member becomes active: the company is linked (or made) with a contact for the member. */
class MemberApproval
{
    public static function approve(Member $member, ?User $by = null): Company
    {
        return DB::transaction(function () use ($member, $by) {
            $company = $member->company
                ?? ($member->reg_vat_number ? Company::where('vat_number', $member->reg_vat_number)->first() : null)
                ?? Company::create([
                    'name' => $member->reg_company_name ?: $member->name,
                    'vat_number' => $member->reg_vat_number,
                    'country' => in_array($member->reg_country, [null, 'XX'], true) ? 'BE' : $member->reg_country,
                    'street' => $member->reg_address,
                    'email' => $member->email,
                    'phone' => $member->mobile,
                    'language' => $member->locale,
                    'is_customer' => true,
                    'is_supplier' => false,
                    'vat_regime' => 'auto',
                ]);
            if ($member->vies_status && $company->vies_status === null && $company->vat_number === $member->reg_vat_number) {
                $company->forceFill(['vies_status' => $member->vies_status, 'vies_reason' => $member->vies_reason, 'vies_name' => $member->vies_name, 'vies_checked_at' => now()])->save();
            }
            if (! $company->is_customer) {
                $company->update(['is_customer' => true]);
            }

            $contact = Contact::where('email', $member->email)->first() ?? Contact::create([
                'first_name' => $member->first_name, 'last_name' => $member->last_name, 'email' => $member->email,
                'mobile' => $member->mobile, 'language' => $member->locale, 'newsletter' => (bool) $member->newsletter,
            ]);
            $company->contacts()->syncWithoutDetaching([$contact->id => ['job_title' => null]]);

            $member->forceFill([
                'status' => Member::ACTIVE,
                'company_id' => $company->id,
                'contact_id' => $contact->id,
                'approved_at' => now(),
                'decided_by_id' => $by?->id ?? auth()->id(),
                'decline_reason' => null,
                'email_verified_at' => $member->email_verified_at ?? now(),
            ])->save();

            AuditLog::record('afrit6.member_approved', $member, ['company' => $company->name], $by);

            return $company;
        });
    }
}
