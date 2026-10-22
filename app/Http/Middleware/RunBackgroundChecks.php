<?php

namespace App\Http\Middleware;

use App\Services\Crm\Birthdays;
use App\Services\Vies;
use App\Services\ViesRecheck;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * one.com may not run a cron for the app, so the small daily jobs also run after
 * a page has been sent: at most every 10 minutes (birthday cards, from 9:00) and
 * every hour (VIES re-checks). Both jobs are safe to run more than once.
 */
class RunBackgroundChecks
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (app()->runningUnitTests() || ! config('cluk.web_jobs', true)) {
            return;
        }
        try {
            $now = now()->timezone('Europe/Brussels');
            if ($now->hour >= 9 && Cache::add('jobs:birthdays:'.$now->toDateString().':'.intdiv($now->hour * 60 + $now->minute, 10), 1, 900)) {
                Birthdays::sendToday($now->copy()->startOfDay());
            }
            if (Cache::add('jobs:vies:'.$now->format('Y-m-d-H'), 1, 3700)) {
                ViesRecheck::run(app(Vies::class), 3, 1); // one try each: never keep a page waiting long
            }
        } catch (Throwable $e) {
            Log::warning('Background checks failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }
}
