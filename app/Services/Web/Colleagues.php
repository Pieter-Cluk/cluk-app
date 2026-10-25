<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Web\CustomerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The main contact of a company manages its website logins: invite a colleague
 * (active at once, they choose their own password through the link), change what
 * a colleague may do, or take a login away. Every step is in the audit log.
 */
class Colleagues
{
    public static function invite(CustomerAccount $main, array $d): CustomerAccount
    {
        abort_unless($main->isMain() && $main->isActive(), 403);
        $email = Str::lower(trim($d['email']));
        if (CustomerAccount::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('web.colleagues.exists')]);
        }
        $a = DB::transaction(function () use ($main, $d, $email) {
            $contact = Contact::where('email', $email)->first()
                ?? Contact::create(['first_name' => $d['first_name'], 'last_name' => $d['last_name'] ?? '', 'email' => $email, 'language' => $main->locale]);
            if (! $main->company->contacts()->whereKey($contact->id)->exists()) {
                $main->company->contacts()->attach($contact->id);
            }

            return CustomerAccount::create([
                'email' => $email, 'password' => Str::random(40), 'first_name' => $d['first_name'], 'last_name' => $d['last_name'] ?? null,
                'company_name' => $main->company->name, 'vat_number' => $main->company->vat_number, 'locale' => $d['locale'] ?? $main->locale,
                'status' => CustomerAccount::ACTIVE, 'contact_id' => $contact->id, 'company_id' => $main->company_id, 'role' => $d['role'],
                'invited_by_id' => $main->id, 'approved_by_id' => $main->approved_by_id, 'approved_at' => now(), 'email_verified_at' => null,
            ]);
        });
        AuditLog::record('web.colleague_invited', $a, ['email' => $a->email, 'role' => $a->role, 'by' => $main->email]);
        CustomerAuth::sendReset($a, 'invite', ['name' => $main->name(), 'company' => $main->company->name]);

        return $a;
    }

    public static function role(CustomerAccount $main, CustomerAccount $a, string $role): void
    {
        self::own($main, $a);
        $old = $a->role;
        $a->forceFill(['role' => $role])->save();
        AuditLog::record('web.colleague_role', $a, ['email' => $a->email, 'role' => $old.' → '.$role, 'by' => $main->email]);
    }

    /** Take the login away (blocked: the history stays) or give it back. */
    public static function access(CustomerAccount $main, CustomerAccount $a, bool $on): void
    {
        self::own($main, $a);
        $a->forceFill(['status' => $on ? CustomerAccount::ACTIVE : CustomerAccount::BLOCKED, 'blocked_reason' => $on ? null : __('web.colleagues.removed_by', ['name' => $main->name()])])->save();
        if (! $on) {
            $a->setRememberToken(Str::random(60));
            $a->save();
        }
        AuditLog::record($on ? 'web.colleague_back' : 'web.colleague_removed', $a, ['email' => $a->email, 'by' => $main->email]);
    }

    private static function own(CustomerAccount $main, CustomerAccount $a): void
    {
        abort_unless($main->isMain() && $main->isActive() && $a->company_id === $main->company_id && $a->id !== $main->id, 403);
    }
}
