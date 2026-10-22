<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Emails to a colleague about their work (a quote answered, an order handed
 * over, a proforma sent back). Respects "My preferences": each kind can be
 * switched off, and while someone is away the person taking over gets a copy.
 */
class StaffMail
{
    public static function send(?User $to, string $event, Notification $mail): bool
    {
        if (! $to) {
            return false;
        }
        $ok = $to->wantsMail($event) ? SafeMail::notify($to, $mail) : true;
        if ($to->awayNow() && ($stand = $to->awayDelegate) && $stand->isActive() && $stand->isNot($to)) {
            SafeMail::notify($stand, clone $mail);
        }

        return $ok;
    }
}
