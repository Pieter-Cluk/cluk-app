<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberApproved extends Notification
{
    public function via(Member $member): array
    {
        return ['mail'];
    }

    public function toMail(Member $member): MailMessage
    {
        return (new MailMessage)
            ->subject(__('afrit6.mail.approved_subject'))
            ->greeting(__('afrit6.mail.hello', ['name' => $member->first_name]))
            ->line(__('afrit6.mail.approved_line'))
            ->action(__('afrit6.mail.approved_button'), route('afrit6.login'))
            ->salutation(__('afrit6.mail.salutation'));
    }
}
