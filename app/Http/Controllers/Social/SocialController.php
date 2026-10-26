<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Social\Account;
use App\Models\Social\Plan;
use App\Services\Social\Dashboard;
use App\Services\Social\Media;
use App\Services\Social\Social;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Media › Social media: the dashboard, and connecting accounts. */
class SocialController extends Controller
{
    public const PERIODS = [7, 30, 90];

    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('d'), self::PERIODS, true) ? (int) $request->query('d') : 30;

        return view('social.index', [
            'd' => Dashboard::build($days),
            'providers' => collect(Social::PROVIDERS)->map(fn ($c, $k) => ['key' => $k, 'configured' => Social::provider($k)->configured()]),
            'canConnect' => $request->user()->hasRight('social.connect'),
            'canPost' => self::canPlan($request),
        ]);
    }

    public static function canPlan(Request $request): bool
    {
        return $request->user()->hasRight('social.publish') || $request->user()->canUseModule('sales', 2);
    }

    /** Start the sign-in on the platform. */
    public function connect(Request $request, string $provider): RedirectResponse
    {
        $p = Social::provider($provider);
        abort_unless($p->configured(), 404);
        $state = Str::random(40);
        $request->session()->put('social_state', [$provider, $state]);

        return redirect()->away($p->authUrl($state));
    }

    /** Back from the platform: store the accounts it gave access to. */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        [$want, $state] = $request->session()->pull('social_state', [null, null]);
        if ($want !== $provider || ! $state || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('social.index')->withErrors(['social' => __('social.connect.state')]);
        }
        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('social.index')->withErrors(['social' => __('social.connect.cancelled')]);
        }
        try {
            $accounts = Social::storeAccounts($provider, Social::provider($provider)->connect((string) $request->query('code')), $request->user());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('social.index')->withErrors(['social' => __('social.connect.failed', ['why' => mb_substr($e->getMessage(), 0, 200)])]);
        }
        foreach ($accounts as $a) {
            config(['social.interactive' => true]); // someone is waiting: no history run here, the background does it
            Social::sync($a, true, 90);
        }

        return redirect()->route('social.index')->with('status', trans_choice('social.connect.done', count($accounts), ['n' => count($accounts)]).(session('social_note') ? ' '.session('social_note') : ''));
    }

    public function disconnect(Request $request, Account $account): RedirectResponse
    {
        Social::disconnect($account, $request->user());

        return back()->with('status', __('social.connect.removed', ['name' => $account->label()]));
    }

    public function sync(Request $request): RedirectResponse
    {
        $n = 0;
        foreach (Account::whereIn('status', ['connected', 'error'])->whereNotNull('token')->get() as $a) {
            $n += (int) Social::sync($a, true, 14);
        }
        AuditLog::record('social.synced', null, ['accounts' => $n], $request->user());

        return back()->with('status', __('social.synced', ['n' => $n]));
    }

    /** A picture or video of a planned post, for the platforms to fetch (signed link, no login). */
    public function media(Plan $plan, int $i): BinaryFileResponse
    {
        $m = ($plan->media ?? [])[$i] ?? null;
        abort_unless($m && Storage::disk('local')->exists($m['path']), 404);

        return response()->file(Storage::disk('local')->path($m['path']), ['Content-Type' => Media::mime($m)]);
    }
}
