<?php

namespace App\Notifications;

use App\Models\Sales\Quote;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the salesperson that the customer answered a quote. */
class QuoteAnswered extends Notification
{
    public function __construct(public Quote $quote, public string $what) {}

    public function via(User $user): array
    {
        return ['mail'];
    }

    public function toMail(User $user): MailMessage
    {
        $q = $this->quote;

        $mail = (new MailMessage)
            ->subject(__('quotes.answered.subject_'.$this->what, ['code' => $q->code(), 'customer' => $q->customerName()]))
            ->line(__('quotes.answered.line_'.$this->what, ['title' => $q->title, 'name' => $q->answered_name ?: '—', 'po' => $q->po_number ?: '—']));
        if ($q->answer_note) {
            $mail->line('“'.$q->answer_note.'”');
        }

        return $mail->action(__('quotes.answered.button'), route('quotes.show', $q));
    }
}
