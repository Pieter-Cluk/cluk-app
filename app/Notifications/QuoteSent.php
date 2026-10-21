<?php

namespace App\Notifications;

use App\Models\Sales\Quote;
use App\Models\User;
use App\Services\Afrit6\Pricing;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The quote link for the customer, in the quote's language. */
class QuoteSent extends Notification
{
    public function __construct(public Quote $quote, public ?string $message, public User $sender) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $q = $this->quote;
        $mail = (new MailMessage)
            ->subject(__('quotes.mail.subject', ['title' => $q->title, 'code' => $q->code()]))
            ->greeting(__('quotes.mail.hello', ['name' => $q->contact?->first_name ?? '']))
            ->replyTo($this->sender->email, $this->sender->name);
        if ($this->message) {
            foreach (preg_split('/\R{2,}/', trim($this->message)) as $p) {
                $mail->line($p);
            }
        } else {
            $mail->line(__('quotes.mail.line', ['title' => $q->title]));
        }

        return $mail
            ->line(__('quotes.mail.total', ['total' => Pricing::eur($q->net_cents), 'date' => $q->reserved_until?->format('d/m/Y H:i')]))
            ->action(__('quotes.mail.button'), route('quote.public', $q->token))
            ->salutation(__('quotes.mail.salutation', ['name' => $this->sender->name]));
    }
}
