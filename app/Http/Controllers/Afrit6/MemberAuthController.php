<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Notifications\MemberVerifyEmail;
use App\Rules\MemberPassword;
use App\Services\Vies;
use App\Support\VatNumber;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Afrit 6 member portal: log in, register (company → details → confirm
 * email → CLUK approval), forgotten password.
 */
class MemberAuthController extends Controller
{
    public const COUNTRIES = ['BE', 'NL', 'FR', 'DE', 'LU', 'ES', 'IT', 'PT', 'AT', 'IE', 'DK', 'SE', 'PL', 'GB', 'CH', 'US', 'OTHER'];

    // ---------------------------------------------------------------- login

    public function showLogin(): View
    {
        return view('afrit6.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'member-login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('afrit6.login.throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $member = Member::where('email', $email)->first();
        if ($member === null || ! Auth::guard('member')->validate(['email' => $email, 'password' => $data['password']])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('afrit6.login.failed')]);
        }
        RateLimiter::clear($key);

        if ($member->status === Member::BLOCKED) {
            throw ValidationException::withMessages(['email' => __('afrit6.login.blocked')]);
        }
        if (! $member->hasVerifiedEmail()) {
            $request->session()->put('afrit6.unverified', $member->id);

            return redirect()->route('afrit6.check-inbox');
        }

        Auth::guard('member')->login($member, $request->boolean('remember'));
        $request->session()->regenerate();
        $member->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('afrit6.portal'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('member')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('afrit6.login');
    }

    // ------------------------------------------------------------ register

    /** Step 1: the company, checked in VIES. */
    public function showCompany(Request $request): View
    {
        return view('afrit6.register-company', [
            'reg' => $request->session()->get('afrit6.reg', ['country' => 'BE']),
        ]);
    }

    public function storeCompany(Request $request, Vies $vies): RedirectResponse
    {
        $data = $request->validate([
            'country' => ['required', Rule::in(self::COUNTRIES)],
            'vat_number' => ['nullable', 'string', 'max:24'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $country = $data['country'] === 'OTHER' ? 'XX' : $data['country'];
        $vat = VatNumber::normalize($data['vat_number'] ?? null, $country);
        $previous = $request->session()->get('afrit6.reg', []);

        $reg = [
            'country' => $data['country'],
            'vat_number' => $vat,
            'vat_input' => $data['vat_number'] ?? null,
            'company_name' => trim((string) ($data['company_name'] ?? '')) ?: null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'vies_status' => null,
            'vies_name' => null,
            'vies_address' => null,
        ];

        if ($vat !== null && ! VatNumber::looksValid($vat)) {
            $request->session()->put('afrit6.reg', $reg);
            throw ValidationException::withMessages(['vat_number' => __('afrit6.register.vat_wrong')]);
        }

        if ($vat !== null && Vies::appliesTo($vat)) {
            // Only ask VIES again when the number changed.
            if (($previous['vat_number'] ?? null) === $vat && isset($previous['vies_status'])) {
                $result = ['status' => $previous['vies_status'], 'name' => $previous['vies_name'], 'address' => $previous['vies_address']];
            } else {
                $result = $vies->check($vat);
            }
            $reg['vies_status'] = $result['status'];
            $reg['vies_name'] = $result['name'];
            $reg['vies_address'] = $result['address'];
            if ($result['status'] === 'valid') {
                $reg['company_name'] ??= $result['name'];
                $reg['address'] ??= $result['address'];
            }
        }

        $request->session()->put('afrit6.reg', $reg);

        // Not valid in VIES (or no VAT number): name and address are needed, a CLUK manager checks them.
        if ($reg['vies_status'] !== 'valid' && ($reg['company_name'] === null || $reg['address'] === null)) {
            return redirect()->route('afrit6.register')->with('need_manual', true)->withInput();
        }

        return redirect()->route('afrit6.register.details');
    }

    /** Step 2: the person and the password. */
    public function showDetails(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('afrit6.reg')) {
            return redirect()->route('afrit6.register');
        }

        return view('afrit6.register-details', ['reg' => $request->session()->get('afrit6.reg')]);
    }

    public function storeDetails(Request $request): RedirectResponse
    {
        $reg = $request->session()->get('afrit6.reg');
        if ($reg === null) {
            return redirect()->route('afrit6.register');
        }

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:members,email'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:200', new MemberPassword($request->input('first_name'), $request->input('last_name'), $request->input('email'))],
            'terms' => ['accepted'],
        ]);

        $member = new Member([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => $data['password'],
            'locale' => app()->getLocale(),
            'newsletter' => $request->boolean('newsletter'),
            'reg_country' => $reg['country'] === 'OTHER' ? 'XX' : $reg['country'],
            'reg_vat_number' => $reg['vat_number'],
            'reg_company_name' => $reg['company_name'] ?? $reg['vies_name'],
            'reg_address' => $reg['address'] ?? $reg['vies_address'],
            'vies_status' => $reg['vies_status'],
        ]);
        $member->terms_accepted_at = now();
        $member->save();

        $member->notify(new MemberVerifyEmail);
        $request->session()->forget('afrit6.reg');
        $request->session()->put('afrit6.unverified', $member->id);

        return redirect()->route('afrit6.check-inbox');
    }

    public function checkInbox(Request $request): View|RedirectResponse
    {
        $member = Member::find($request->session()->get('afrit6.unverified'));
        if ($member === null) {
            return redirect()->route('afrit6.login');
        }

        return view('afrit6.check-inbox', ['member' => $member]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $member = Member::find($request->session()->get('afrit6.unverified'));
        if ($member !== null && ! $member->hasVerifiedEmail()) {
            $key = 'member-verify:'.$member->id;
            if (! RateLimiter::tooManyAttempts($key, 3)) {
                RateLimiter::hit($key, 600);
                $member->notify(new MemberVerifyEmail);
            }
        }

        return redirect()->route('afrit6.check-inbox')->with('status', __('afrit6.register.resent'));
    }

    /** The link in the email (signed, 48 hours). */
    public function verify(Request $request, Member $member, string $hash): RedirectResponse
    {
        abort_unless(hash_equals(sha1($member->email), $hash), 403);

        if (! $member->hasVerifiedEmail()) {
            $member->forceFill(['email_verified_at' => now()])->save();
        }
        $request->session()->forget('afrit6.unverified');

        if ($member->status !== Member::BLOCKED) {
            Auth::guard('member')->login($member);
            $request->session()->regenerate();
        }

        return redirect()->route('afrit6.portal')->with('status', __('afrit6.register.email_confirmed'));
    }

    // ------------------------------------------------------------ password

    public function showForgot(): View
    {
        return view('afrit6.forgot');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('members')->sendResetLink(['email' => mb_strtolower(trim($request->input('email')))]);

        // Same answer whether the address exists or not.
        return back()->with('status', __('afrit6.forgot.sent'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('afrit6.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $member = Member::where('email', $request->input('email'))->first();

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:200', new MemberPassword($member?->first_name, $member?->last_name, $request->input('email'))],
        ]);

        $status = Password::broker('members')->reset(
            $request->only('email', 'password', 'token'),
            function (Member $member, string $password) {
                $member->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
                // Following a link from the inbox also proves the email address.
                $member->email_verified_at ??= now();
                $member->save();
                event(new PasswordReset($member));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('afrit6.forgot.invalid')]);
        }

        return redirect()->route('afrit6.login')->with('status', __('afrit6.forgot.done'));
    }
}
