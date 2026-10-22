<?php

namespace App\Services\Crm;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Media\Message;
use App\Notifications\Confirmation;
use App\Services\Media\Messages;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Birthday cards: contacts with a birth date and the "send birthday card" tick get
 * a short card by email on their birthday (29 February: on the 28th in other years),
 * in their own language, once a year.
 */
class Birthdays
{
    /** @return int how many cards went out */
    public static function sendToday(?Carbon $today = null): int
    {
        $today ??= today();
        $sent = 0;
        $contacts = Contact::with('companies')->where('birthday_card', true)->whereNotNull('birth_date')->whereNotNull('email')
            ->where(fn ($q) => $q->whereNull('birthday_sent_year')->orWhere('birthday_sent_year', '<', $today->year))->get();
        foreach ($contacts as $c) {
            if (! self::isBirthday($c->birth_date, $today)) {
                continue;
            }
            $lang = $c->language ?: ($c->companies->first()?->language ?: 'en');
            $lang = array_key_exists($lang, config('cluk.locales')) ? $lang : 'en';
            // The birthday card made in Media › Messages (picture + text), else the standard text.
            $mail = ($card = Message::inUse('birthday', $today))
                ? Messages::mail($card, $lang, null, ['name' => $c->first_name])
                : InLocale::run($lang, fn () => new Confirmation(
                    subject: __('companies.birthday.subject', ['name' => $c->first_name]),
                    greeting: __('companies.birthday.greeting', ['name' => $c->first_name]),
                    intro: [__('companies.birthday.text')],
                    next: [__('companies.birthday.sign')],
                ))->locale($lang);
            if (SafeMail::notify(Notification::route('mail', $c->email), $mail)) {
                $c->forceFill(['birthday_sent_year' => $today->year])->save();
                AuditLog::record('crm.birthday_sent', $c, ['to' => $c->email]);
                $sent++;
            }
        }

        return $sent;
    }

    public static function isBirthday(Carbon $born, Carbon $day): bool
    {
        if ($born->month === 2 && $born->day === 29 && ! $day->isLeapYear()) {
            return $day->month === 2 && $day->day === 28;
        }

        return $born->month === $day->month && $born->day === $day->day;
    }
}
