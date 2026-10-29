<?php

namespace App\Services\Mail;

use App\Models\AuditLog;
use App\Models\EmailBlock;
use App\Models\Media\MessageSend;
use App\Models\User;
use App\Services\Sales\CommercialTasks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Mails that came back undeliverable. The bounce messages (non-delivery reports) arrive in the mailbox the app
 * sends from; every 15 minutes they are read through Microsoft Graph (application permission Mail.Read).
 * The address that bounced is blocked: no more mails go to it (newsletters, promotions, invitations and the
 * app's other mails), and a red commercial task asks the account owner to check the customer card.
 * Changing the address on the card or clicking "Unblock" ends it.
 */
class Bounces
{
    /** Our own domains: never blocked. */
    public const OWN = ['cluk.live'];

    /** Bounce subjects (Microsoft 365 and most other mail servers, in our languages). */
    private const SUBJECT = '/^\s*(undeliverable|undelivered|onbestelbaar|niet bezorgd|non remis|non distribuable|unzustellbar|no se puede entregar|no entregado|delivery status notification \(failure\)|mail delivery failed|returned mail|failure notice|delivery has failed|delivery failure|mail delivery subsystem)/i';

    /** Not a failure: delayed, delivered, read, relayed. */
    private const NOT = '/^\s*(delivery delayed|delayed|vertraagd|retardé|verzögert|delivered|bezorgd|remis|relayed|read:|gelezen:|lu :|automatic reply|automatisch antwoord|réponse automatique|out of office)/i';

    /** @return array<string, true> the blocked addresses (lower case) */
    public static function blocked(): array
    {
        return Cache::remember('email_blocks.v'.Cache::get('email_blocks.stamp', 0), 600,
            fn () => EmailBlock::active()->pluck('email')->map(fn ($e) => self::norm($e))->flip()->map(fn () => true)->all());
    }

    public static function isBlocked(?string $email): bool
    {
        return $email !== null && isset(self::blocked()[self::norm($email)]);
    }

    public static function norm(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private static function flush(): void
    {
        Cache::forever('email_blocks.stamp', (int) Cache::get('email_blocks.stamp', 0) + 1);
    }

    /** Block an address after a bounce (once; a later bounce only updates the reason). */
    public static function block(string $email, ?string $detail = null, ?string $subject = null, ?Carbon $at = null): ?EmailBlock
    {
        $email = self::norm($email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || in_array(substr(strrchr($email, '@'), 1), self::OWN, true)) {
            return null;
        }
        $b = EmailBlock::active()->where('email', $email)->first();
        if ($b) {
            $b->update(['detail' => $detail ?? $b->detail, 'subject' => $subject ?? $b->subject, 'bounced_at' => $at ?? now()]);

            return $b;
        }
        // Who sent the mail that bounced gets the task when the customer has no account owner.
        $notify = MessageSend::where('email', $email)->latest('id')->first()?->message?->sent_by_id;
        $b = EmailBlock::create(['email' => $email, 'reason' => 'bounce', 'detail' => $detail !== null ? mb_substr($detail, 0, 1000) : null,
            'subject' => $subject !== null ? mb_substr($subject, 0, 255) : null, 'bounced_at' => $at ?? now(), 'notify_user_id' => $notify]);
        MessageSend::where('email', $email)->where('status', 'queued')->update(['status' => 'blocked']);
        AuditLog::record('mail.bounced', $b, ['email' => $email, 'subject' => $b->subject]);
        self::flush();

        return $b;
    }

    /** The address was checked (or the customer fixed their mailbox): mails may go to it again. */
    public static function clear(EmailBlock $b, User $by): void
    {
        $b->update(['cleared_at' => now(), 'cleared_by_id' => $by->id]);
        AuditLog::record('mail.unblocked', $b, ['email' => $b->email], $by);
        self::flush();
    }

    /**
     * Just before any mail leaves: blocked addresses are taken out. Without anyone left in "To" the mail is not sent.
     *
     * @return false|null false stops the mail
     */
    public static function filter(Email $mail): ?bool
    {
        $blocked = self::blocked();
        if (! $blocked) {
            return null;
        }
        $keep = fn (array $list) => array_values(array_filter($list, fn (Address $a) => ! isset($blocked[self::norm($a->getAddress())])));
        $to = $keep($mail->getTo());
        if (count($to) !== count($mail->getTo())) {
            Log::info('Mail to a blocked address left out', ['subject' => mb_substr((string) $mail->getSubject(), 0, 120)]);
        }
        if (! $to) {
            return false;
        }
        $mail->to(...$to);
        if ($cc = $mail->getCc()) {
            $keep($cc) ? $mail->cc(...$keep($cc)) : $mail->getHeaders()->remove('Cc');
        }

        return null;
    }

    // ── Reading the bounce messages ─────────────────────────────────────────

    /** A bounce message? (sender and subject) */
    public static function isBounce(string $subject, string $from): bool
    {
        if (preg_match(self::NOT, $subject)) {
            return false;
        }
        $from = self::norm($from);

        return (bool) preg_match(self::SUBJECT, $subject)
            || str_starts_with($from, 'postmaster@') || str_starts_with($from, 'mailer-daemon@') || str_starts_with($from, 'microsoftexchange');
    }

    /**
     * The addresses that failed, from the bounce text: addresses we know (contacts, companies, members, website
     * accounts, mailing lists), never our own or the mail servers' addresses.
     *
     * @return array<int, string>
     */
    public static function failed(string $text, string $from = ''): array
    {
        preg_match_all('/[A-Z0-9._%+\'-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $m);
        $found = collect($m[0])->map(fn ($e) => self::norm(trim($e, '.\'')))->unique()
            ->reject(fn ($e) => $e === self::norm($from) || in_array(substr(strrchr($e, '@'), 1), self::OWN, true)
                || preg_match('/^(postmaster|mailer-daemon|no-?reply|microsoftexchange)/', $e) || preg_match('/(outlook\.com|protection\.outlook|prod\.exchangelabs)$/', $e))
            ->values()->all();
        if (! $found) {
            return [];
        }
        $known = collect()
            ->merge(DB::table('contacts')->whereIn(DB::raw('lower(email)'), $found)->pluck('email'))
            ->merge(DB::table('companies')->whereIn(DB::raw('lower(email)'), $found)->pluck('email'))
            ->merge(DB::table('members')->whereIn(DB::raw('lower(email)'), $found)->pluck('email'))
            ->merge(DB::table('customer_accounts')->whereIn(DB::raw('lower(email)'), $found)->pluck('email'))
            ->merge(DB::table('message_sends')->whereIn('email', $found)->pluck('email'))
            ->map(fn ($e) => self::norm($e))->unique();

        return array_values(array_intersect($found, $known->all()));
    }

    /** The line that says why (a 5.x.x code), else the start of the text. */
    public static function reason(string $text): string
    {
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('/\b5\.\d{1,3}\.\d{1,3}\b|\b55\d\b/', $line)) {
                return mb_substr($line, 0, 300);
            }
        }

        return mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 300);
    }

    /** One bounce message: block the failed addresses. Returns how many were blocked. */
    public static function handle(string $subject, string $from, string $body, ?Carbon $at = null): int
    {
        if (! self::isBounce($subject, $from)) {
            return 0;
        }
        $orig = trim(preg_replace(self::SUBJECT.'u', '', $subject), " :\t");
        $n = 0;
        foreach (self::failed($subject."\n".$body, $from) as $email) {
            if (self::block($email, self::reason($body), $orig ?: null, $at)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Read the new bounce messages in the sending mailbox (every 15 minutes). Without the Mail.Read permission
     * nothing happens (checked again after an hour).
     */
    public static function scan(): int
    {
        if (config('mail.default') !== 'microsoft-graph' || Cache::get('bounces.no_access')) {
            return 0;
        }
        $box = (string) config('mail.from.address');
        $since = Carbon::parse(Cache::get('bounces.since', now()->subDays(3)->toIso8601String()));
        try {
            $token = self::token();
            $url = 'https://graph.microsoft.com/v1.0/users/'.rawurlencode($box).'/mailFolders/inbox/messages';
            $query = ['$filter' => 'receivedDateTime ge '.$since->copy()->utc()->format('Y-m-d\TH:i:s\Z'), '$orderby' => 'receivedDateTime asc',
                '$top' => 50, '$select' => 'id,subject,from,receivedDateTime'];
            $n = 0;
            $last = $since;
            for ($page = 0; $page < 6 && $url; $page++) {
                $r = Http::withToken($token)->timeout(20)->get($url, $page === 0 ? $query : []);
                if (in_array($r->status(), [401, 403], true)) {
                    Cache::put('bounces.no_access', true, 3600);
                    Cache::put('bounces.status', 'no_access', 86400);

                    return 0;
                }
                if (! $r->successful()) {
                    Log::warning('Reading bounces failed', ['status' => $r->status(), 'error' => $r->json('error.code')]);

                    return $n;
                }
                foreach ($r->json('value', []) as $m) {
                    $last = Carbon::parse($m['receivedDateTime']);
                    $from = (string) ($m['from']['emailAddress']['address'] ?? '');
                    if (! self::isBounce((string) ($m['subject'] ?? ''), $from)) {
                        continue;
                    }
                    $full = Http::withToken($token)->withHeaders(['Prefer' => 'outlook.body-content-type="text"'])->timeout(20)
                        ->get('https://graph.microsoft.com/v1.0/users/'.rawurlencode($box).'/messages/'.rawurlencode($m['id']), ['$select' => 'subject,body']);
                    $n += self::handle((string) ($m['subject'] ?? ''), $from, (string) $full->json('body.content', ''), $last);
                }
                $url = $r->json('@odata.nextLink');
            }
            Cache::forever('bounces.since', $last->copy()->addSecond()->toIso8601String());
            Cache::put('bounces.status', 'ok', 86400);
            if ($n) {
                CommercialTasks::refresh();
            }

            return $n;
        } catch (Throwable $e) {
            Log::warning('Reading bounces failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return 0;
        }
    }

    public static function token(): string
    {
        return Cache::remember('graph-mail-token', 3000, function () {
            $r = Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/'.config('services.microsoft.tenant').'/oauth2/v2.0/token', [
                'client_id' => config('services.microsoft.client_id'), 'client_secret' => config('services.microsoft.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new \RuntimeException('Microsoft Graph token failed: '.$r->json('error'));
            }

            return $r->json('access_token');
        });
    }
}
