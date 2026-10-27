<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A Media › Messages mail: the picture, the text in the person's language, the PDF, an unsubscribe link. */
class MessageMail extends Notification
{
    /** @param array<int, array{path: string, name: string}> $files */
    public function __construct(
        public string $subject,
        public string $body,
        public ?string $imageUrl = null,
        public ?array $pdfLink = null,
        public ?string $unsubscribeUrl = null,
        public array $files = [],
        public ?array $button = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->markdown('mail.message', [
            'body' => $this->body, 'imageUrl' => $this->imageUrl, 'pdfLink' => $this->pdfLink, 'unsubscribeUrl' => $this->unsubscribeUrl, 'button' => $this->button,
        ]);
        foreach ($this->files as $f) {
            $mail->attach($f['path'], ['as' => $f['name'], 'mime' => 'application/pdf']);
        }
        if ($this->unsubscribeUrl) {
            $mail->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$this->unsubscribeUrl.'>'));
        }

        return $mail;
    }
}
