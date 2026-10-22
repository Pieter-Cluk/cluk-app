<?php

namespace App\Services\Me;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Carbon;

/**
 * "Daily summary" from My preferences: one email on working days at the
 * chosen time with the person's open tasks (the same tiles as the dashboard).
 * Nothing open, no email. Safe to run more than once a day.
 */
class DailySummary
{
    public const TIMES = ['07:30', '08:00', '09:00'];

    /** Returns how many summaries were sent. */
    public static function run(?Carbon $now = null): int
    {
        $now ??= now()->timezone('Europe/Brussels');
        if ($now->isWeekend()) {
            return 0;
        }
        $sent = 0;
        $due = User::where('status', User::STATUS_ACTIVE)->whereNotNull('daily_at')->where('daily_at', '<=', $now->format('H:i'))
            ->where(fn ($q) => $q->whereNull('daily_sent_on')->orWhere('daily_sent_on', '<', $now->toDateString()))->get();
        foreach ($due as $u) {
            $u->forceFill(['daily_sent_on' => $now->toDateString()])->save();
            if (self::send($u)) {
                $sent++;
            }
        }

        return $sent;
    }

    public static function send(User $u): bool
    {
        $tasks = DashboardController::tasks($u, 'me');
        if (! $tasks) {
            return false;
        }
        $locale = $u->locale ?: 'en';
        $mail = InLocale::run($locale, fn () => new Confirmation(
            subject: __('me.daily.subject', ['date' => now()->locale($locale)->translatedFormat('l j F')]),
            greeting: __('mails.hello', ['name' => $u->first_name]),
            intro: [__('me.daily.intro')],
            details: collect($tasks)->mapWithKeys(fn ($t) => [__('dashboard.tasks.'.$t['key']) => (string) $t['n']])->all(),
            next: [__('me.daily.change')],
            button: [__('me.daily.button'), route('dashboard')],
        ))->locale($locale);

        return SafeMail::notify($u, $mail);
    }
}
