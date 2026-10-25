<?php

namespace App\Services\Web;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Notifications\Confirmation;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Website logins: register → confirm email → staff approve (link to a contact
 * and company) → sign in on the website (session guard "customer"). Blocking
 * signs the customer out on the next click. Passwords are only stored hashed.
 */
class CustomerAuth
{
    public static function register(array $d): CustomerAccount
    {
        $a = CustomerAccount::create([
            'email' => Str::lower(trim($d['email'])), 'password' => $d['password'], 'first_name' => $d['first_name'], 'last_name' => $d['last_name'] ?? null,
            'company_name' => $d['company_name'] ?? null, 'vat_number' => $d['vat_number'] ?? null, 'phone' => $d['phone'] ?? null,
            'locale' => array_key_exists($d['locale'] ?? '', config('cluk.locales')) ? $d['locale'] : 'en', 'status' => CustomerAccount::PENDING,
        ]);
        self::sendVerify($a);
        AuditLog::record('web.account_registered', $a, ['email' => $a->email]);

        return $a;
    }

    public static function sendVerify(CustomerAccount $a): bool
    {
        $link = URL::temporarySignedRoute('customer.verify', now()->addHours(48), ['account' => $a->id, 'hash' => sha1($a->email)]);

        return self::mail($a, 'verify', ['button' => [__('web.mail.verify.button', [], $a->locale), $link]]);
    }

    /**
     * Sign in on the website (session guard "customer"). Returns null when signed
     * in, otherwise why not: wrong, unverified, pending or blocked.
     */
    public static function attempt(string $email, string $password, bool $remember = false): ?string
    {
        $a = CustomerAccount::where('email', Str::lower(trim($email)))->first();
        if (! $a || ! Hash::check($password, $a->password)) {
            return 'wrong';
        }
        if (! $a->email_verified_at) {
            return 'unverified';
        }
        if ($a->status !== CustomerAccount::ACTIVE) {
            return $a->status; // pending or blocked
        }
        Auth::guard('customer')->login($a, $remember);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        $a->forceFill(['last_login_at' => now()])->save();

        return null;
    }

    /** Sign the customer out (only the customer: a staff session in the same browser stays). */
    public static function logout(): void
    {
        Auth::guard('customer')->logout();
        if (request()->hasSession()) {
            request()->session()->regenerateToken();
        }
    }

    /** The signed-in customer, if the account is still active. */
    public static function current(): ?CustomerAccount
    {
        $a = Auth::guard('customer')->user();

        return $a?->isActive() ? $a : null;
    }

    /** The link in the confirmation email (valid 48 hours). */
    public static function verify(CustomerAccount $a, string $hash): bool
    {
        if (! hash_equals(sha1($a->email), $hash)) {
            return false;
        }
        if (! $a->email_verified_at) {
            $a->forceFill(['email_verified_at' => now()])->save();
            AuditLog::record('web.email_verified', $a, ['email' => $a->email]);
        }

        return true;
    }

    /**
     * A page of the website, by route name, once the website session has built it;
     * until then the fallback path on the website.
     */
    public static function siteUrl(string $route, array $params = [], string $fallback = '/'): string
    {
        if (Route::has($route)) {
            return route($route, $params);
        }
        $base = Route::has('site.home') ? rtrim(route('site.home'), '/') : rtrim(url('/site'), '/');

        return $base.$fallback.($params ? '?'.http_build_query($params) : '');
    }

    /** Staff approve: link to a contact (existing or new from the registration) and optionally a company. */
    public static function approve(CustomerAccount $a, User $by, ?Company $company = null, ?Contact $contact = null, bool $newCompany = false): CustomerAccount
    {
        DB::transaction(function () use ($a, $by, &$company, &$contact, $newCompany) {
            if (! $company && $newCompany && $a->company_name) {
                $company = Company::create(['name' => $a->company_name, 'vat_number' => $a->vat_number, 'is_customer' => true, 'is_supplier' => false,
                    'vat_regime' => 'auto', 'language' => $a->locale, 'phone' => $a->phone]);
            }
            $contact ??= Contact::where('email', $a->email)->first();
            $contact ??= Contact::create(['first_name' => $a->first_name, 'last_name' => $a->last_name ?? '', 'email' => $a->email, 'phone' => $a->phone, 'language' => $a->locale]);
            if ($company && ! $company->contacts()->whereKey($contact->id)->exists()) {
                $company->contacts()->attach($contact->id);
            }
            // The first login of a company becomes its main contact (manages colleagues).
            $main = $company && ! CustomerAccount::where('company_id', $company->id)->where('role', 'main')->where('status', CustomerAccount::ACTIVE)->whereKeyNot($a->id)->exists();
            $a->forceFill(['status' => CustomerAccount::ACTIVE, 'contact_id' => $contact->id, 'company_id' => $company?->id, 'approved_by_id' => $by->id, 'approved_at' => now(), 'blocked_reason' => null]
                + ($main ? ['role' => 'main'] : []))->save();
        });
        AuditLog::record('web.account_approved', $a, ['email' => $a->email, 'company' => $company?->name, 'contact' => $contact?->id], $by);
        self::mail($a, 'approved', ['button' => [__('web.mail.approved.button', [], $a->locale), self::siteUrl('site.account.login', [], '/account/login')]]);

        return $a;
    }

    public static function block(CustomerAccount $a, User $by, ?string $reason): void
    {
        $a->forceFill(['status' => CustomerAccount::BLOCKED, 'blocked_reason' => $reason])->save();
        $a->setRememberToken(Str::random(60)); // "remember me" stops on every device; open sessions end on the next click
        $a->save();
        AuditLog::record('web.account_blocked', $a, ['email' => $a->email, 'reason' => $reason], $by);
    }

    public static function unblock(CustomerAccount $a, User $by): void
    {
        $a->forceFill(['status' => $a->contact_id ? CustomerAccount::ACTIVE : CustomerAccount::PENDING, 'blocked_reason' => null])->save();
        AuditLog::record('web.account_unblocked', $a, ['email' => $a->email], $by);
    }

    /** A password link to the website's reset page (valid 60 minutes, used once). */
    public static function sendReset(CustomerAccount $a, string $kind = 'reset', array $params = []): bool
    {
        $plain = Str::random(48);
        DB::table('customer_password_resets')->where('account_id', $a->id)->delete();
        // An invitation stays valid for 7 days, a reset for 60 minutes.
        DB::table('customer_password_resets')->insert(['account_id' => $a->id, 'token_hash' => hash('sha256', $plain), 'expires_at' => $kind === 'invite' ? now()->addDays(7) : now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $link = self::siteUrl('site.account.reset', ['email' => $a->email, 'token' => $plain], '/account/reset');
        if ($kind === 'invite') {
            return self::mail($a, 'invite', ['button' => [__('web.mail.invite.button', [], $a->locale), $link], 'params' => $params]);
        }

        return self::mail($a, 'reset', ['button' => [__('web.mail.reset.button', [], $a->locale), $link]]);
    }

    public static function reset(string $email, string $token, string $password): bool
    {
        $a = CustomerAccount::where('email', Str::lower(trim($email)))->first();
        $row = $a ? DB::table('customer_password_resets')->where('account_id', $a->id)->where('token_hash', hash('sha256', $token))->first() : null;
        if (! $row || now()->gt($row->expires_at)) {
            return false;
        }
        $a->forceFill(['password' => $password, 'email_verified_at' => $a->email_verified_at ?? now()])->save();
        DB::table('customer_password_resets')->where('account_id', $a->id)->delete();
        $a->setRememberToken(Str::random(60));
        $a->save();
        AuditLog::record('web.password_reset', $a, ['email' => $a->email]);

        return true;
    }

    /** The customer changes their own details on "My account" (the email address stays: it is the login). */
    public static function saveProfile(CustomerAccount $a, array $d): void
    {
        $a->fill(array_intersect_key($d, array_flip(['first_name', 'last_name', 'company_name', 'vat_number', 'phone'])));
        if (isset($d['locale']) && array_key_exists($d['locale'], config('cluk.locales'))) {
            $a->locale = $d['locale'];
        }
        $a->save();
        AuditLog::record('web.profile_saved', $a, ['email' => $a->email]);
    }

    /** A new password, after checking the current one. "Remember me" on other devices stops. */
    public static function changePassword(CustomerAccount $a, string $current, string $new): bool
    {
        if (! Hash::check($current, $a->password)) {
            return false;
        }
        $a->forceFill(['password' => $new])->setRememberToken(Str::random(60));
        $a->save();
        AuditLog::record('web.password_changed', $a, ['email' => $a->email]);

        return true;
    }

    private static function mail(CustomerAccount $a, string $kind, array $extra = []): bool
    {
        $loc = $a->locale ?: 'en';
        $mail = InLocale::run($loc, fn () => new Confirmation(
            subject: __('web.mail.'.$kind.'.subject', $extra['params'] ?? []),
            greeting: __('mails.hello', ['name' => $a->first_name]),
            intro: [__('web.mail.'.$kind.'.intro', $extra['params'] ?? [])],
            next: [__('web.mail.'.$kind.'.next', $extra['params'] ?? [])],
            button: $extra['button'] ?? null,
        ))->locale($loc);

        return SafeMail::notify(Notification::route('mail', $a->email), $mail);
    }
}
