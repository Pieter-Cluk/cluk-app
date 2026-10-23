<?php

namespace App\Http\Controllers;

use App\Services\Website\WebActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Management › Website: what visitors do on cluk.live (only those who accepted statistics cookies). Read-only. */
class WebStatsController extends Controller
{
    public const PERIODS = [7, 30, 90];

    public function __invoke(Request $request): View
    {
        $days = in_array((int) $request->query('d'), self::PERIODS, true) ? (int) $request->query('d') : 30;

        return view('management.website', ['days' => $days, 's' => WebActivity::overview($days)]);
    }
}
