<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** "Confirm your email" for a new Afrit 6 member. The link works for 48 hours. */
class MemberVerifyEmail extends Notification
{
    public function via(Member $member): array
    {
        return ['mail'];
    }

    public static function url(Member $member): string
    {
        return URL::temporarySignedRoute('afrit6.verify', now()->addHours(48), [
            'member' => $member->id,
            'hash' => sha1($member->email),
        ]);
    }

    public function toMail(Member $member): MailMessage
    {
        return (new MailMessage)
            ->subject(__('afrit6.mail.verify_subject'))
            ->greeting(__('afrit6.mail.hello', ['name' => $member->first_name]))
            ->line(__('afrit6.mail.verify_line'))
            ->action(__('afrit6.mail.verify_button'), self::url($member))
            ->line(__('afrit6.mail.verify_after'))
            ->salutation(__('afrit6.mail.salutation'));
    }
}
