<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A friendly "this is what you just did" email in the CLUK style:
 * a few lines, the details in a green panel, optional lines table,
 * what happens next, a button and a personal signature.
 * Texts must already be translated in the receiver's language.
 */
class Confirmation extends Notification
{
    /**
     * @param  array<int, string>  $intro
     * @param  array<string, string>  $details
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows
     * @param  array<string, string>  $totals
     * @param  array<int, string>  $next
     * @param  array{0: string, 1: string}|null  $button
     */
    public function __construct(
        public string $subject,
        public string $greeting,
        public array $intro,
        public array $details = [],
        public array $next = [],
        public ?array $button = null,
        public ?User $signer = null,
        public array $rows = [],
        public array $totals = [],
        public ?string $team = null,
        public ?string $replyTo = null,
    ) {}

    /** Files to attach: [['path' => …, 'name' => …]]. */
    public array $attachFiles = [];

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->markdown('mail.confirmation', [
            'greeting' => $this->greeting, 'intro' => $this->intro, 'details' => $this->details, 'next' => $this->next,
            'button' => $this->button, 'signer' => $this->signer, 'rows' => $this->rows, 'totals' => $this->totals, 'team' => $this->team,
        ]);
        foreach ($this->attachFiles as $f) {
            $mail->attach($f['path'], ['as' => $f['name'], 'mime' => $f['mime'] ?? 'application/pdf']);
        }
        if ($reply = $this->replyTo ?? $this->signer?->email) {
            $mail->replyTo($reply, $this->signer?->name);
        }

        return $mail;
    }
}
