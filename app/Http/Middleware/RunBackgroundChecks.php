<?php

namespace App\Http\Middleware;

use App\Services\Credit\NbbAccounts;
use App\Services\Credit\RegistryImport;
use App\Services\Crm\Birthdays;
use App\Services\Me\DailySummary;
use App\Services\Media\Messages;
use App\Services\Sales\CommercialTasks;
use App\Services\Social\Social;
use App\Services\Vies;
use App\Services\ViesRecheck;
use App\Services\Website\WebActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * one.com may not run a cron for the app, so the small daily jobs also run after
 * a page has been sent: every minute (newsletters and promotions, a batch of
 * mails), at most every 10 minutes (birthday cards, from 9:00) and every hour
 * (VIES re-checks, company register look-ups); daily summaries every 5 minutes. Both jobs are safe to run more than once.
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
            // Newsletters and promotions: scheduled ones start, then a batch of mails goes out.
            if (Cache::add('jobs:messages:'.$now->format('Y-m-d-H-i'), 1, 120)) {
                Messages::work(20);
            }
            if (Cache::add('jobs:daily:'.$now->format('Y-m-d-H').':'.intdiv($now->minute, 5), 1, 400)) {
                DailySummary::run($now);
            }
            if (Cache::add('jobs:vies:'.$now->format('Y-m-d-H'), 1, 3700)) {
                ViesRecheck::run(app(Vies::class), 3, 1); // one try each: never keep a page waiting long
            }
            // Website statistics older than 13 months are deleted (GDPR), once a day.
            if (Cache::add('jobs:webprune:'.$now->toDateString(), 1, 90000)) {
                WebActivity::prune();
            }
            // Company register (KBO web service): the oldest data first, a few every 10 minutes.
            if (Cache::add('jobs:kbo:'.$now->format('Y-m-d-H').':'.intdiv($now->minute, 10), 1, 700)) {
                RegistryImport::refreshStale((int) config('services.kbo_ws.per_run', 5));
            }
            // Annual accounts (National Bank): a few companies every 10 minutes, each once a month.
            if (Cache::add('jobs:nbb:'.$now->format('Y-m-d-H').':'.intdiv($now->minute, 10), 1, 700)) {
                NbbAccounts::refreshStale((int) config('services.nbb.per_run', 3));
            }
            // Commercial tasks: made and closed every 15 minutes.
            if (Cache::add('jobs:salestasks:'.$now->format('Y-m-d-H').':'.intdiv($now->minute, 15), 1, 1000)) {
                CommercialTasks::refresh();
            }
            // Bounced mails (undeliverable): read from the sending mailbox every 15 minutes.
            if (Cache::add('jobs:bounces:'.$now->format('Y-m-d-H').':'.intdiv($now->minute, 15), 1, 1000)) {
                \App\Services\Mail\Bounces::scan();
            }
            // Social media: planned posts go out on time; the figures of one account at a time every 15 minutes.
            if (Cache::add('jobs:social:'.$now->format('Y-m-d-H-i'), 1, 120)) {
                Social::publishDue();
            }
            if (Cache::add('jobs:socialsync:'.$now->format('Y-m-d-H').':'.intdiv($now->minute, 15), 1, 1000)) {
                Social::syncDue(1);
            }
        } catch (Throwable $e) {
            Log::warning('Background checks failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }
}
