<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Me\DailySummary;
use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The menu under your name: My preferences, My profile, Notifications. Only about yourself. */
class MeController extends Controller
{
    public const THEMES = ['auto', 'light', 'dark'];

    public const DENSITIES = ['comfortable', 'compact'];

    public function preferences(Request $request): View
    {
        $u = $request->user();

        return view('me.preferences', [
            'u' => $u,
            'starts' => array_values(array_filter(array_keys(User::START_PAGES), fn ($k) => Navigation::allows($u, $k))),
            'colleagues' => User::where('status', User::STATUS_ACTIVE)->whereKeyNot($u->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'devices' => $this->devices($request),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $u = $request->user();
        $d = $request->validate([
            'theme' => ['required', Rule::in(self::THEMES)],
            'density' => ['required', Rule::in(self::DENSITIES)],
            'start_page' => ['required', Rule::in(array_keys(User::START_PAGES))],
            'locale' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'mail' => ['nullable', 'array'],
            'daily_at' => ['nullable', Rule::in(DailySummary::TIMES)],
            'away_delegate_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE), Rule::notIn([$u->id])],
            'away_from' => ['nullable', 'date', 'required_with:away_delegate_id'],
            'away_until' => ['nullable', 'date', 'after_or_equal:away_from', 'required_with:away_delegate_id'],
        ]);
        $mail = [];
        foreach (User::MAIL_EVENTS as $e) {
            $mail[$e] = (bool) ($d['mail'][$e] ?? false);
        }
        $u->update([
            'theme' => $d['theme'], 'density' => $d['density'], 'start_page' => $d['start_page'], 'locale' => $d['locale'],
            'notify' => $mail, 'daily_at' => $d['daily_at'] ?? null,
            'away_delegate_id' => $d['away_delegate_id'] ?? null,
            'away_from' => ! empty($d['away_delegate_id']) ? $d['away_from'] : null,
            'away_until' => ! empty($d['away_delegate_id']) ? $d['away_until'] : null,
        ]);
        $request->session()->put('locale', $d['locale']);

        return redirect()->route('me.preferences')->with('status', __('me.saved', [], $d['locale']));
    }

    /** The sun/moon button in the top bar remembers the choice for this person. */
    public function theme(Request $request)
    {
        $d = $request->validate(['theme' => ['required', Rule::in(self::THEMES)]]);
        $request->user()->update(['theme' => $d['theme']]);

        return response()->noContent();
    }

    /** Sign out everywhere except on this device. */
    public function logoutOthers(Request $request): RedirectResponse
    {
        $u = $request->user();
        $n = DB::table('sessions')->where('user_id', $u->id)->where('id', '!=', $request->session()->getId())->delete();
        $u->forceFill(['remember_token' => Str::random(60)])->save();

        return back()->with('status', trans_choice('me.signin.done', $n, ['n' => $n]));
    }

    public function profile(Request $request): View
    {
        $u = $request->user()->load(['role', 'reportsTo', 'moduleOverrides', 'extraRights']);

        return view('me.profile', ['u' => $u, 'levels' => config('cluk.levels')]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $d = $request->validate(['phone' => ['nullable', 'string', 'max:40'], 'mobile' => ['nullable', 'string', 'max:40']]);
        $request->user()->update($d);

        return back()->with('status', __('me.saved'));
    }

    /** @return list<array{name: string, ip: ?string, at: Carbon, current: bool}> */
    private function devices(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->limit(10)->get()
            ->map(fn ($s) => ['name' => self::device((string) $s->user_agent), 'ip' => $s->ip_address, 'at' => Carbon::createFromTimestamp($s->last_activity), 'current' => $s->id === $request->session()->getId()])
            ->all();
    }

    public static function device(string $ua): string
    {
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Linux') => 'Linux', default => '?',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => '',
        };

        return trim($os.($browser ? ' · '.$browser : ''));
    }
}
