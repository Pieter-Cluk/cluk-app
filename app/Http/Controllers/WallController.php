<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WallScreen;
use App\Services\WallData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The office wall screens: shown with a secret link (no login), set up by staff. */
class WallController extends Controller
{
    public function show(Request $request, string $view): View
    {
        $screen = WallScreen::where('view', $view)->first();
        $key = (string) $request->query('key');
        $staff = $request->user('web');
        abort_unless($screen && (($key !== '' && hash_equals($screen->token, $key)) || $staff), 404);
        if (! $staff) {
            DB::table('wall_screens')->where('id', $screen->id)->update(['last_seen_at' => now()]); // no updated_at noise
        }
        $lang = $request->query('lang', $screen->locale);
        app()->setLocale(array_key_exists($lang, config('cluk.locales')) ? $lang : 'nl');

        if ($view === 'planning') {
            $off = max(-8, min(26, (int) $request->query('off', 0)));
            $start = today()->startOfWeek()->subWeek()->addWeeks($off);

            return view('wall.planning', ['screen' => $screen, 'start' => $start, 'days' => 28, 'off' => $off, 'key' => $key,
                'heat' => WallData::heat($start, 28), 'jobs' => WallData::jobs($start, 28)]);
        }

        $day = in_array($request->query('day'), ['today', 'tomorrow', 'week', 'month'], true) ? $request->query('day') : 'today';
        [$from, $until] = match ($day) {
            'tomorrow' => [today()->addDay(), today()->addDay()],
            'week' => [today()->startOfWeek(), today()->endOfWeek()],
            'month' => [today(), today()->addDays(29)],
            default => [today(), today()],
        };

        return view('wall.today', ['screen' => $screen, 'day' => $day, 'from' => $from, 'until' => $until, 'key' => $key,
            'moves' => WallData::moves($from, $until), 'fleet' => WallData::fleetOut(), 'venues' => WallData::venues(), 'work' => WallData::openWork()]);
    }

    public function index(Request $request): View
    {
        return view('wall.index', ['screens' => WallScreen::orderBy('id')->get(), 'canManage' => $request->user()->canUseModule('warehouse', 2)]);
    }

    public function renew(Request $request, WallScreen $screen): RedirectResponse
    {
        abort_unless($request->user()->canUseModule('warehouse', 2), 403);
        $screen->update(['token' => Str::random(40)]);
        AuditLog::record('wall.renewed', $screen, ['view' => $screen->view]);

        return back()->with('status', __('wall.admin.renewed'));
    }

    public function update(Request $request, WallScreen $screen): RedirectResponse
    {
        abort_unless($request->user()->canUseModule('warehouse', 2), 403);
        $screen->update($request->validate(['locale' => ['required', Rule::in(array_keys(config('cluk.locales')))], 'location' => ['nullable', 'string', 'max:120']]));

        return back()->with('status', __('wall.admin.saved'));
    }
}
