<?php

namespace App\Services\Media;

use App\Models\Media\Message;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Support\InLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Email banners: shown under the mails the app sends to customers and other
 * outside people. Several can run in the same period; each person gets them in
 * turn: the first mail banner 1, the next mail (e.g. the reply) banner 2, and so on.
 * Not in mails to staff, login codes, account security mails or the accountant.
 */
class MailBanners
{
    /** Banners running today, in their order. */
    public static function live($on = null): Collection
    {
        $on ??= today();

        return Message::kind('mail_banner')->where('active', true)
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', $on))
            ->where(fn ($q) => $q->whereNull('active_until')->orWhereDate('active_until', '>=', $on))
            ->orderBy('position')->orderBy('id')->get()
            ->filter(fn (Message $m) => $m->image_path || filled($m->subject['en'] ?? null) || filled($m->body['en'] ?? null))->values();
    }

    /** The banner for the next mail to this address (moves the person one turn on), or null. */
    public static function next(?string $email): ?Message
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '' || ! str_contains($email, '@') || self::isStaff($email)) {
            return null;
        }
        $live = self::live();
        if ($live->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($email, $live) {
            $row = DB::table('mail_banner_turns')->where('email', $email)->lockForUpdate()->first();
            $n = (int) ($row->mails ?? 0);
            $row
                ? DB::table('mail_banner_turns')->where('id', $row->id)->update(['mails' => $n + 1, 'updated_at' => now()])
                : DB::table('mail_banner_turns')->insert(['email' => $email, 'mails' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $banner = $live[$n % $live->count()];
            $banner->increment('sent_count');

            return $banner;
        });
    }

    /** A test mail to yourself showing this banner under an example mail. */
    public static function sample(Message $b, string $loc, User $me): Confirmation
    {
        $mail = InLocale::run($loc, fn () => new Confirmation(
            subject: __('messages.banner_test.subject', ['title' => $b->title]),
            greeting: __('mails.hello', ['name' => $me->first_name]),
            intro: [__('messages.banner_test.intro')],
            signer: $me,
        ))->locale($loc);
        $mail->forceBanner = $b;

        return $mail;
    }

    /** The mail address of a notifiable (anonymous routes, customers, contacts). */
    public static function emailOf(object $notifiable): ?string
    {
        if ($notifiable instanceof User) {
            return null;
        }
        $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('mail') : ($notifiable->email ?? null);
        if (is_array($to)) {
            $k = array_key_first($to);
            $to = is_string($k) ? $k : ($to[$k] ?? null);
        }

        return is_string($to) ? $to : null;
    }

    private static function isStaff(string $email): bool
    {
        return User::whereRaw('lower(email) = ?', [$email])->exists()
            || str_ends_with($email, '@'.ltrim((string) config('cluk.staff_email_domain', 'cluk.live'), '@'));
    }
}
