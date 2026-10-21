<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a notification without breaking the page when the mail service
 * fails: the error is logged and the caller can tell the person.
 */
class SafeMail
{
    public static function notify(object $notifiable, Notification $notification): bool
    {
        try {
            $notifiable->notify($notification);

            return true;
        } catch (Throwable $e) {
            Log::error('Email could not be sent', ['notification' => class_basename($notification), 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }
}
