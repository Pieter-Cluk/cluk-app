<?php

namespace App\Notifications;

use App\Models\Media\Message;
use App\Models\User;
use App\Services\Media\MailBanners;
use App\Support\MailCopy;
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

    /** An email banner under the mail (Media › Messages) for outside people; off for codes, security and the accountant. */
    public bool $banner = true;

    /** A set banner (test mail of a banner), instead of the person's next one. */
    public ?Message $forceBanner = null;

    public function withoutBanner(): static
    {
        $this->banner = false;

        return $this;
    }

    /** Private (login codes, password links): no banner and no copy to CLUK's mailbox. */
    public bool $private = false;

    public function private(): static
    {
        $this->private = true;
        $this->banner = false;

        return $this;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $banner = $this->forceBanner ?? ($this->banner ? MailBanners::next(MailBanners::emailOf($notifiable)) : null);
        $mail = (new MailMessage)->subject($this->subject)->markdown('mail.confirmation', [
            'greeting' => $this->greeting, 'intro' => $this->intro, 'details' => $this->details, 'next' => $this->next,
            'button' => $this->button, 'signer' => $this->signer, 'rows' => $this->rows, 'totals' => $this->totals, 'team' => $this->team,
            'banner' => $banner, 'bannerLoc' => $this->locale ?? app()->getLocale(),
        ]);
        foreach ($this->attachFiles as $f) {
            $mail->attach($f['path'], ['as' => $f['name'], 'mime' => $f['mime'] ?? 'application/pdf']);
        }
        if ($this->private) {
            $mail->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader(MailCopy::NO_COPY, '1'));
        }
        if ($reply = $this->replyTo ?? $this->signer?->email) {
            $mail->replyTo($reply, $this->signer?->name);
        }

        return $mail;
    }
}
