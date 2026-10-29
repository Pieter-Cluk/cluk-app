<?php

namespace App\Services\Mail;

use App\Models\Sales\IncomingMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The shared mailbox requests@cluk.live: every mail that arrives there (from a customer, or forwarded by the team)
 * becomes an item in Sales › Incoming, with its attachments. Read every 5 minutes through Microsoft Graph
 * (application permission Mail.Read, the same as for bounces). A forwarded mail is filed under the original sender.
 * Auto-replies, bounces and newsletters are skipped. Nothing in the mailbox is changed.
 */
class RequestsMailbox
{
    public const MAX_ATTACHMENTS = 10;

    public const MAX_BYTES = 15 * 1024 * 1024;

    private const SKIP_SUBJECT = '/^\s*(automatic reply|automatisch antwoord|réponse automatique|respuesta automática|abwesenheitsnotiz|out of office|afwezig|absent|undeliverable|onbestelbaar|non remis|delivery status notification|mail delivery failed)/i';

    private const FWD = '/^\s*((fw|fwd|tr|wg|doorst|rv|re)\s*:\s*)+/i';

    public static function box(): string
    {
        return (string) config('cluk.requests_mailbox');
    }

    /** ok, missing (no such mailbox yet), no_access, off, or null (not read yet). */
    public static function status(): ?string
    {
        if (config('mail.default') !== 'microsoft-graph' || self::box() === '') {
            return 'off';
        }

        return Cache::get('requests_mailbox.status');
    }

    public static function scan(): int
    {
        if (self::status() === 'off' || Cache::get('requests_mailbox.pause')) {
            return 0;
        }
        $box = rawurlencode(self::box());
        $since = Carbon::parse(Cache::get('requests_mailbox.since', now()->subDay()->toIso8601String()));
        try {
            $token = Bounces::token();
            $url = "https://graph.microsoft.com/v1.0/users/{$box}/mailFolders/inbox/messages";
            $query = ['$filter' => 'receivedDateTime ge '.$since->copy()->utc()->format('Y-m-d\TH:i:s\Z'), '$orderby' => 'receivedDateTime asc', '$top' => 25,
                '$select' => 'id,internetMessageId,subject,from,receivedDateTime,hasAttachments'];
            $n = 0;
            $last = $since;
            for ($page = 0; $page < 4 && $url; $page++) {
                $r = Http::withToken($token)->timeout(20)->get($url, $page === 0 ? $query : []);
                if ($r->status() === 404 || in_array($r->status(), [401, 403], true)) {
                    // No such mailbox yet, or no permission: try again in an hour.
                    Cache::put('requests_mailbox.status', $r->status() === 404 ? 'missing' : 'no_access', 86400);
                    Cache::put('requests_mailbox.pause', true, 3600);

                    return 0;
                }
                if (! $r->successful()) {
                    Log::warning('Reading the requests mailbox failed', ['status' => $r->status(), 'error' => $r->json('error.code')]);

                    return $n;
                }
                foreach ($r->json('value', []) as $m) {
                    $last = Carbon::parse($m['receivedDateTime']);
                    $n += self::take($token, $box, $m) ? 1 : 0;
                }
                $url = $r->json('@odata.nextLink');
            }
            Cache::forever('requests_mailbox.since', $last->copy()->addSecond()->toIso8601String());
            Cache::put('requests_mailbox.status', 'ok', 86400);
            Cache::put('requests_mailbox.checked', now()->toIso8601String(), 86400);

            return $n;
        } catch (Throwable $e) {
            Log::warning('Reading the requests mailbox failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return 0;
        }
    }

    /** One mail: an Incoming item (once). */
    private static function take(string $token, string $box, array $m): bool
    {
        $mailId = (string) ($m['internetMessageId'] ?? $m['id']);
        if (IncomingMessage::where('channel', 'email')->where('payload->mail_id', $mailId)->exists()) {
            return false;
        }
        $subject = (string) ($m['subject'] ?? '');
        $from = Str::lower((string) ($m['from']['emailAddress']['address'] ?? ''));
        if (preg_match(self::SKIP_SUBJECT, $subject) || preg_match('/^(no-?reply|mailer-daemon|postmaster)@/i', $from) || $from === Str::lower(self::box())) {
            return false;
        }
        $full = Http::withToken($token)->withHeaders(['Prefer' => 'outlook.body-content-type="text"'])->timeout(20)
            ->get("https://graph.microsoft.com/v1.0/users/{$box}/messages/".rawurlencode($m['id']), ['$select' => 'body,internetMessageHeaders,from']);
        $headers = collect($full->json('internetMessageHeaders', []))->mapWithKeys(fn ($h) => [Str::lower($h['name']) => $h['value']]);
        if ($headers->has('list-unsubscribe') || in_array(Str::lower((string) $headers->get('auto-submitted', 'no')), ['auto-replied', 'auto-generated'], true)
            || Str::lower((string) $headers->get('precedence', '')) === 'bulk') {
            return false;
        }

        return (bool) self::make($subject, $from, (string) ($m['from']['emailAddress']['name'] ?? ''), (string) $full->json('body.content', ''),
            Carbon::parse($m['receivedDateTime']), $mailId, ($m['hasAttachments'] ?? false) ? fn () => self::attachments($token, $box, $m['id']) : null);
    }

    /**
     * Build the item. A mail from our own domain is a forward: the original sender (the first "From:/Van:/De:" line
     * with an address in the text) becomes the sender, and the colleague who forwarded it is kept.
     */
    public static function make(string $subject, string $from, string $fromName, string $body, Carbon $at, string $mailId, ?callable $files = null): ?IncomingMessage
    {
        $forwardedBy = null;
        if (Str::endsWith($from, '@'.Str::after(Str::lower(self::box()), '@'))) {
            if (preg_match('/^\s*(?:\*\*)?(?:from|van|de|von|da)\s*:\s*(?:\*\*)?\s*(.*?)\s*[<\[]?(?:mailto:)?([A-Z0-9._%+\'-]+@[A-Z0-9.-]+\.[A-Z]{2,})[>\]]?/im', $body, $mm)
                && ! Str::endsWith(Str::lower($mm[2]), '@'.Str::after(Str::lower(self::box()), '@'))) {
                $forwardedBy = $from;
                $from = Str::lower($mm[2]);
                $fromName = trim(str_replace(['"', '*'], '', $mm[1]));
            }
        }
        $item = new IncomingMessage([
            'channel' => 'email', 'subject' => Str::limit(trim(preg_replace(self::FWD, '', $subject)) ?: '(no subject)', 195, '…'),
            'body' => Str::limit(trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $body))), 20000, '…'),
            'from_name' => Str::limit($fromName ?: Str::before($from, '@'), 155, ''), 'from_email' => $from ?: null, 'received_at' => $at,
            'payload' => array_filter(['type' => 'mail', 'mail_id' => $mailId, 'forwarded_by' => $forwardedBy]),
        ]);
        $item->matchKnown();
        $item->save();
        if ($files) {
            $saved = [];
            foreach ($files() as $f) {
                $path = 'incoming/'.$item->id.'/'.Str::random(8).'_'.Str::limit(preg_replace('/[^A-Za-z0-9._-]+/', '_', $f['name']), 80, '');
                Storage::disk('local')->put($path, $f['bytes']);
                $saved[] = ['name' => $f['name'], 'path' => $path, 'size' => strlen($f['bytes'])];
            }
            if ($saved) {
                $item->forceFill(['payload' => $item->payload + ['attachments' => $saved]])->save();
            }
        }

        return $item;
    }

    /** @return list<array{name: string, bytes: string}> real files only (no inline pictures), within the limits */
    private static function attachments(string $token, string $box, string $id): array
    {
        $r = Http::withToken($token)->timeout(60)->get("https://graph.microsoft.com/v1.0/users/{$box}/messages/".rawurlencode($id).'/attachments');
        $out = [];
        foreach ($r->json('value', []) as $a) {
            if (($a['@odata.type'] ?? '') !== '#microsoft.graph.fileAttachment' || ($a['isInline'] ?? false) || ($a['size'] ?? 0) > self::MAX_BYTES || empty($a['contentBytes'])) {
                continue;
            }
            $out[] = ['name' => (string) $a['name'], 'bytes' => base64_decode($a['contentBytes'])];
            if (count($out) >= self::MAX_ATTACHMENTS) {
                break;
            }
        }

        return $out;
    }
}
