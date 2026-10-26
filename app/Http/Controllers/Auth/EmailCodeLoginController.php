<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StaffLoginCode;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign in without Microsoft 365 (freelancers): a 6-digit code by email to a
 * company address (@cluk.live), for people an admin allowed it for under
 * Settings › Users. No password is stored; the code works once, for 10 minutes.
 */
class EmailCodeLoginController extends Controller
{
    public static function domain(): string
    {
        return (string) config('cluk.staff_email_domain', 'cluk.live');
    }

    public function form(Request $request): View
    {
        return view('auth.staff-code', ['email' => $request->session()->get('code_email'), 'sent' => $request->session()->has('code_email')]);
    }

    public function send(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => ['required', 'email', 'max:190']])['email']));
        if (! str_ends_with($email, '@'.self::domain())) {
            throw ValidationException::withMessages(['email' => __('login.code.wrong_domain', ['domain' => self::domain()])]);
        }
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($user && $user->isActive() && $user->email_login && $user->code_email) {
            StaffLoginCode::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
            $code = (string) random_int(100000, 999999);
            StaffLoginCode::create(['user_id' => $user->id, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(StaffLoginCode::MINUTES), 'ip' => $request->ip()]);
            $lang = $user->locale ?: config('app.locale');
            // cluk.live mail goes to Microsoft 365: the code goes to the address on the user card.
            SafeMail::notify(Notification::route('mail', $user->code_email ?: $user->email), (new Confirmation(
                subject: __('login.code.mail_subject', ['code' => $code], $lang),
                greeting: __('mails.hello', ['name' => $user->first_name], $lang),
                intro: [__('login.code.mail_intro', ['minutes' => StaffLoginCode::MINUTES], $lang)],
                details: [__('login.code.code', [], $lang) => $code],
                next: [__('login.code.mail_next', [], $lang)],
            ))->locale($lang)->private());
            AuditLog::record('auth.code_sent', $user, ['ip' => $request->ip()], $user);
        } else {
            AuditLog::record('auth.code_refused', $user, ['email' => $email]);
        }
        // Same answer either way, so nobody can find out who has an account.
        $request->session()->put('code_email', $email);

        return redirect()->route('login.code')->with('status', __('login.code.sent', ['email' => $email]));
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = (string) $request->session()->get('code_email');
        abort_if($email === '', 419);
        $code = preg_replace('/\D/', '', (string) $request->validate(['code' => ['required', 'string', 'max:12']])['code']);
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        $row = $user ? StaffLoginCode::where('user_id', $user->id)->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first() : null;
        if (! $row || ! $user->isActive() || ! $user->email_login) {
            throw ValidationException::withMessages(['code' => __('login.code.expired')]);
        }
        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('tries');
            if ($row->tries >= StaffLoginCode::MAX_TRIES) {
                $row->update(['used_at' => now()]);
            }
            throw ValidationException::withMessages(['code' => __('login.code.wrong')]);
        }
        $row->update(['used_at' => now()]);
        $user->forceFill(['last_seen_at' => now()])->save();
        Auth::login($user, remember: true);
        $request->session()->forget('code_email');
        $request->session()->regenerate();
        AuditLog::record('auth.login', $user, ['with' => 'email code'], $user);

        return redirect()->intended($user->homeUrl());
    }
}
