<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Staff sign in with their CLUK Microsoft 365 account.
 * Only people who were added under Settings › Users get in; a disabled
 * person is refused. No password is ever stored for staff.
 */
class MicrosoftLoginController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $account = Socialite::driver('microsoft')->user();
        } catch (Throwable $e) {
            Log::warning('Microsoft login failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('login_error', 'failed');
        }

        $email = mb_strtolower((string) $account->getEmail());

        $user = User::where('microsoft_id', $account->getId())->first()
            ?? ($email !== '' ? User::whereRaw('lower(email) = ?', [$email])->first() : null);

        if ($user === null) {
            AuditLog::record('auth.refused_unknown', null, ['email' => $email]);

            return redirect()->route('login')->with(['login_error' => 'not_listed', 'login_email' => $email]);
        }

        if (! $user->isActive()) {
            AuditLog::record('auth.refused_disabled', $user, [], $user);

            return redirect()->route('login')->with('login_error', 'disabled');
        }

        $user->forceFill([
            'microsoft_id' => $user->microsoft_id ?? $account->getId(),
            'last_seen_at' => now(),
        ])->save();

        self::takeOverPhoto($user, (string) ($account->token ?? ''));

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        AuditLog::record('auth.login', $user, [], $user);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Take over the person's Microsoft 365 profile photo at every login, so a
     * new photo in Microsoft shows up here too. No photo or no access: keep what we have.
     */
    public static function takeOverPhoto(User $user, string $token): void
    {
        if ($token === '') {
            return;
        }
        try {
            $res = Http::withToken($token)->timeout(8)->get('https://graph.microsoft.com/v1.0/me/photos/240x240/$value');
            if (! $res->successful() || strlen($res->body()) < 100) {
                return;
            }
            $old = $user->photo_path;
            $user->forceFill(['photo_path' => Images::store($res->body(), 'users', 240)])->save();
            Images::delete($old);
        } catch (Throwable $e) {
            Log::info('Microsoft photo not taken over', ['user' => $user->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
        }
    }
}
