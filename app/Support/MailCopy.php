<?php

namespace App\Support;

use App\Services\Media\MailBanners;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A blind copy of every mail the app sends to customers and other outside people goes to
 * CLUK's own mailbox (info@cluk.live), so the team can see what went out and when.
 * Not for: mails only to colleagues, newsletters and promotions (one per person, thousands),
 * and private mails such as login codes and password links (marked X-Cluk-No-Copy).
 */
class MailCopy
{
    public const NO_COPY = 'X-Cluk-No-Copy';

    public static function apply(Email $mail): void
    {
        $headers = $mail->getHeaders();
        if ($headers->has(self::NO_COPY)) {
            $headers->remove(self::NO_COPY);

            return;
        }
        $copy = mb_strtolower(trim((string) config('cluk.mail_copy')));
        if ($copy === '' || ! str_contains($copy, '@') || $headers->has('List-Unsubscribe')) {
            return;
        }
        $to = array_map(fn (Address $a) => mb_strtolower($a->getAddress()), [...$mail->getTo(), ...$mail->getCc()]);
        $all = [...$to, ...array_map(fn (Address $a) => mb_strtolower($a->getAddress()), $mail->getBcc())];
        if ($to === [] || in_array($copy, $all, true)) {
            return;
        }
        // Only colleagues: no copy needed.
        if (collect($to)->every(fn ($e) => MailBanners::isStaff($e))) {
            return;
        }
        $mail->addBcc($copy);
    }
}
