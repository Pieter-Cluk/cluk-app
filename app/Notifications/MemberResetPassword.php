<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberResetPassword extends Notification
{
    public function __construct(public string $token) {}

    public function via(Member $member): array
    {
        return ['mail'];
    }

    public function toMail(Member $member): MailMessage
    {
        $url = route('afrit6.password.reset', ['token' => $this->token, 'email' => $member->email]);

        return (new MailMessage)
            ->subject(__('afrit6.mail.reset_subject'))
            ->greeting(__('afrit6.mail.hello', ['name' => $member->first_name]))
            ->line(__('afrit6.mail.reset_line'))
            ->action(__('afrit6.mail.reset_button'), $url)
            ->line(__('afrit6.mail.reset_after'))
            ->salutation(__('afrit6.mail.salutation'));
    }
}
