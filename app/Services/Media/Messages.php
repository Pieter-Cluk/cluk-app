<?php

namespace App\Services\Media;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Media\Message;
use App\Models\Media\MessageSend;
use App\Models\Member;
use App\Models\User;
use App\Notifications\MessageMail;
use App\Support\SafeMail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Sending newsletters and promotions: a message is put on its list once
 * (everyone gets it once, never twice), then mailed in small batches. A
 * scheduled message goes out once at its time; nothing is ever sent again
 * by itself.
 */
class Messages
{
    public const BATCH = 25;

    /** Attach the PDF when it is small enough for Microsoft 365, else a link. */
    public const ATTACH_MAX = 3 * 1024 * 1024;

    /** Translate the English subject and text into nl, fr, es (keeps what was typed by hand when nothing changed). */
    public static function translate(Message $m, bool $force = false): bool
    {
        if (! $force && ! $m->translationsOutdated()) {
            return true;
        }
        $out = app(Translator::class)->translate(['subject' => $m->subject['en'] ?? '', 'body' => $m->body['en'] ?? '']);
        if ($out === null) {
            return false;
        }
        $subject = ['en' => $m->subject['en'] ?? ''];
        $body = ['en' => $m->body['en'] ?? ''];
        foreach (['nl', 'fr', 'es'] as $l) {
            $subject[$l] = $out[$l]['subject'] ?? '';
            $body[$l] = $out[$l]['body'] ?? '';
        }
        $m->update(['subject' => $subject, 'body' => $body, 'source_hash' => $m->sourceHash()]);

        return true;
    }

    /**
     * Who gets it: a newsletter goes to people who said yes to the newsletter;
     * a promotion to every contact and member with an email who did not unsubscribe.
     *
     * @return array<string, array{email: string, contact_id: ?int, member_id: ?int, locale: string}>
     */
    public static function recipients(Message $m): array
    {
        $list = [];
        $contacts = Contact::with('companies:id,language')->whereNotNull('email')
            ->when($m->kind === 'newsletter', fn ($q) => $q->where('newsletter', true), fn ($q) => $q->where('promo_opt_out', false))->get();
        foreach ($contacts as $c) {
            $email = mb_strtolower(trim($c->email));
            $list[$email] ??= ['email' => $email, 'contact_id' => $c->id, 'member_id' => null, 'locale' => self::locale($c->language ?: $c->companies->first()?->language)];
        }
        $members = Member::whereNotNull('email')->where('status', Member::ACTIVE)
            ->when($m->kind === 'newsletter', fn ($q) => $q->where('newsletter', true), fn ($q) => $q->where('promo_opt_out', false))->get();
        foreach ($members as $mb) {
            $email = mb_strtolower(trim($mb->email));
            $list[$email] ??= ['email' => $email, 'contact_id' => null, 'member_id' => $mb->id, 'locale' => self::locale($mb->locale)];
        }

        return $list;
    }

    public static function locale(?string $l): string
    {
        return in_array($l, Message::LOCALES, true) ? $l : 'en';
    }

    /** "Send now": put everyone on the list and start mailing. */
    public static function start(Message $m, User $by, bool $sendFirstBatch = true): int
    {
        abort_unless(in_array($m->kind, Message::MAILED, true) && in_array($m->status, ['draft', 'scheduled'], true), 409);
        foreach (self::recipients($m) as $r) {
            MessageSend::firstOrCreate(['message_id' => $m->id, 'email' => $r['email']], $r + ['status' => 'queued']);
        }
        $m->update(['status' => 'sending', 'sent_by_id' => $by->id, 'send_at' => $m->send_at ?? now()]);
        AuditLog::record('messages.send_started', $m, ['kind' => $m->kind, 'people' => $m->sends()->count()], $by);
        if ($sendFirstBatch) {
            self::work(self::BATCH);
        }

        return $m->sends()->count();
    }

    /** Scheduled messages whose time has come start; then a batch of mails goes out. */
    public static function work(int $limit = self::BATCH): int
    {
        foreach (Message::where('status', 'scheduled')->where('send_at', '<=', now())->get() as $due) {
            self::start($due, $due->sentBy ?? $due->createdBy ?? User::orderBy('id')->firstOrFail(), false);
        }
        $done = 0;
        foreach (Message::where('status', 'sending')->get() as $m) {
            foreach ($m->sends()->where('status', 'queued')->orderBy('id')->limit(max(0, $limit - $done))->get() as $s) {
                $first = $s->contact?->first_name ?? $s->member?->first_name;
                $ok = SafeMail::notify(Notification::route('mail', $s->email), self::mail($m, $s->locale, $s, ['name' => $first]));
                $s->update(['status' => $ok ? 'sent' : 'failed', 'sent_at' => now()]);
                $done++;
            }
            if (! $m->sends()->where('status', 'queued')->exists()) {
                $m->update(['status' => 'sent', 'sent_at' => now(), 'sent_count' => $m->sends()->where('status', 'sent')->count()]);
                AuditLog::record('messages.sent', $m, ['people' => $m->sent_count], $m->sentBy);
            }
            if ($done >= $limit) {
                break;
            }
        }

        return $done;
    }

    /**
     * Fill {name} (any capitals) in a subject or text. Without a first name it
     * disappears neatly: "Hi {name}," becomes "Hi,".
     */
    public static function fill(string $text, array $vars): string
    {
        $out = preg_replace_callback('/\s*\{\s*(\w+)\s*\}/u', function ($m) use ($vars) {
            $v = trim((string) ($vars[strtolower($m[1])] ?? ''));

            return $v === '' ? '' : (preg_match('/^\s/', $m[0]) ? ' ' : '').$v;
        }, $text);
        // Tidy what an empty name leaves behind: "birthday,!" → "birthday!", ", our deals" → "our deals".
        $out = preg_replace('/,\s*([!?.])/u', '$1', $out);
        $out = preg_replace_callback('/^\s*,\s*(.)/mu', fn ($m) => mb_strtoupper($m[1]), $out);

        return trim($out);
    }

    public static function mail(Message $m, string $locale, ?MessageSend $s = null, array $vars = []): MessageMail
    {
        $repl = fn (string $t) => self::fill($t, $vars);
        $files = [];
        $pdfLink = null;
        if ($m->pdf_path && Storage::disk('local')->exists($m->pdf_path)) {
            if (Storage::disk('local')->size($m->pdf_path) <= self::ATTACH_MAX) {
                $files[] = ['path' => Storage::disk('local')->path($m->pdf_path), 'name' => $m->pdf_name ?: 'CLUK.pdf'];
            }
            $pdfLink = [__('messages.read_pdf', [], $locale), URL::signedRoute('messages.public.pdf', $m)];
        }
        $unsub = $s && in_array($m->kind, Message::MAILED, true)
            ? URL::signedRoute('messages.unsubscribe', ['list' => $m->kind === 'newsletter' ? 'newsletter' : 'promo', 'send' => $s->id])
            : null;

        return (new MessageMail(
            subject: $repl($m->text('subject', $locale)),
            body: $repl($m->text('body', $locale)),
            imageUrl: $m->image_path ? URL::signedRoute('messages.public.image', $m) : null,
            pdfLink: $pdfLink,
            unsubscribeUrl: $unsub,
            files: $files,
        ))->locale($locale);
    }
}
