<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** CLUK added this member: welcome, and choose a password (the link works for 7 days). */
class MemberInvited extends Notification
{
    public function __construct(public string $token) {}

    public function via(Member $member): array
    {
        return ['mail'];
    }

    public function toMail(Member $member): MailMessage
    {
        $url = route('afrit6.password.reset', ['token' => $this->token, 'email' => $member->email, 'invite' => 1]);

        return (new MailMessage)
            ->subject(__('afrit6.manage.mail_subject'))
            ->greeting(__('afrit6.mail.hello', ['name' => $member->first_name]))
            ->line(__('afrit6.manage.mail_line'))
            ->action(__('afrit6.manage.mail_button'), $url)
            ->line(__('afrit6.manage.mail_after'))
            ->salutation(__('afrit6.mail.salutation'));
    }
}
