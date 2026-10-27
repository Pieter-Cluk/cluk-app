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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        if ($m->kind === 'invitation') {
            return self::invitees();
        }
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

    /** Countries first in the plan (in this order); the others follow by size, people without a country last. */
    public const FIRST_COUNTRIES = ['BE', 'NL', 'LU', 'FR', 'GB', 'DE'];

    /** Invitation hours (Brussels): mails go out on weekdays between these hours only. */
    public const HOURS = [9, 16];

    /**
     * Invitation to register on the website: contacts of customer companies with an email address, except who
     * unsubscribed from promotions and who already has a website account. Country = the customer company's country.
     *
     * @return array<string, array{email: string, contact_id: int, member_id: null, locale: string, country: ?string}>
     */
    public static function invitees(): array
    {
        $registered = array_flip(DB::table('customer_accounts')->pluck('email')->map(fn ($e) => mb_strtolower(trim((string) $e)))->all());
        $list = [];
        $contacts = Contact::with(['companies' => fn ($q) => $q->where('is_customer', true)->select('companies.id', 'country', 'language')])
            ->whereNotNull('email')->where('email', '!=', '')->where('promo_opt_out', false)
            ->whereHas('companies', fn ($q) => $q->where('is_customer', true))->orderBy('id')->get();
        foreach ($contacts as $c) {
            $email = mb_strtolower(trim($c->email));
            if (isset($registered[$email]) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $co = $c->companies->first();
            $country = strtoupper(trim((string) $co?->country));
            $list[$email] ??= ['email' => $email, 'contact_id' => $c->id, 'member_id' => null, 'locale' => self::locale($c->language ?: $co?->language),
                'country' => preg_match('/^[A-Z]{2}$/', $country) ? ($country === 'UK' ? 'GB' : $country) : null];
        }

        return $list;
    }

    /**
     * The sending plan: country by country in the plan's order, each country starting on a new day, at most
     * $perDay people a day, weekdays only.
     *
     * @param  array<int, array{country: ?string}>  $people
     * @return array{days: array<int, array{date: string, country: string, n: int}>, countries: array<string, array{n: int, from: string, until: string}>, rows: array<int, string>}
     */
    public static function plan(array $people, Carbon $start, int $perDay, array $first = self::FIRST_COUNTRIES): array
    {
        $byCountry = collect($people)->groupBy(fn ($p) => $p['country'] ?? '')->map->values();
        $order = collect($first)->filter(fn ($c) => $byCountry->has($c))
            ->merge($byCountry->keys()->reject(fn ($c) => $c === '' || in_array($c, $first, true))->sortByDesc(fn ($c) => $byCountry[$c]->count())->values())
            ->when($byCountry->has(''), fn ($o) => $o->push(''))->values();
        $day = $start->copy()->startOfDay();
        $next = function (Carbon $d): Carbon {
            while ($d->isWeekend()) {
                $d->addDay();
            }

            return $d;
        };
        $day = $next($day);
        $days = $countries = $rows = [];
        $i = 0;
        foreach ($order as $country) {
            $people = $byCountry[$country];
            foreach ($people->chunk(max(1, $perDay)) as $k => $chunk) {
                if ($i > 0) {
                    $day = $next($day->copy()->addDay());
                }
                $i++;
                $days[] = ['date' => $day->toDateString(), 'country' => $country, 'n' => $chunk->count()];
                foreach ($chunk as $p) {
                    $rows[$p['email'] ?? count($rows)] = $day->toDateString();
                }
                $countries[$country]['n'] = ($countries[$country]['n'] ?? 0) + $chunk->count();
                $countries[$country]['from'] ??= $day->toDateString();
                $countries[$country]['until'] = $day->toDateString();
            }
        }

        return ['days' => $days, 'countries' => $countries, 'rows' => $rows];
    }

    /** Whether invitations may go out now (weekdays, office hours in Brussels). */
    public static function invitationHours(?Carbon $now = null): bool
    {
        $now = ($now ?? now())->copy()->timezone('Europe/Brussels');

        return ! $now->isWeekend() && $now->hour >= self::HOURS[0] && $now->hour < self::HOURS[1];
    }

    public static function locale(?string $l): string
    {
        return in_array($l, Message::LOCALES, true) ? $l : 'en';
    }

    /** "Send now": put everyone on the list and start mailing. */
    public static function start(Message $m, User $by, bool $sendFirstBatch = true): int
    {
        abort_unless(in_array($m->kind, Message::MAILED, true) && in_array($m->status, ['draft', 'scheduled'], true), 409);
        $people = self::recipients($m);
        if ($m->kind === 'invitation') {
            $plan = $m->plan ?? [];
            $dates = self::plan($people, Carbon::parse($plan['start'] ?? now()->toDateString(), 'Europe/Brussels'), (int) ($plan['per_day'] ?? 100), $plan['countries'] ?? self::FIRST_COUNTRIES)['rows'];
            foreach ($people as $email => $r) {
                $people[$email]['planned_on'] = $dates[$email] ?? null;
            }
        }
        foreach ($people as $r) {
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
            $invite = $m->kind === 'invitation';
            if ($invite && ! self::invitationHours()) {
                continue;
            }
            $registered = $invite ? DB::table('customer_accounts')->pluck('email')->map(fn ($e) => mb_strtolower(trim((string) $e)))->flip() : collect();
            $due = $m->sends()->where('status', 'queued')
                ->when($invite, fn ($q) => $q->where(fn ($w) => $w->whereNull('planned_on')->orWhereDate('planned_on', '<=', now()->timezone('Europe/Brussels')->toDateString())))
                ->orderBy('planned_on')->orderBy('id')->limit(max(0, $limit - $done))->get();
            foreach ($due as $s) {
                if ($invite && $registered->has($s->email)) {
                    $s->update(['status' => 'skipped']); // registered on the website in the meantime

                    continue;
                }
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
        $button = $m->kind === 'invitation'
            ? [__('messages.invite.button', [], $locale), self::registerUrl($locale, $s?->email)]
            : null;

        return (new MessageMail(
            subject: $repl($m->text('subject', $locale)),
            body: $repl($m->text('body', $locale)),
            imageUrl: $m->image_path ? URL::signedRoute('messages.public.image', $m) : null,
            pdfLink: $pdfLink,
            unsubscribeUrl: $unsub,
            files: $files,
            button: $button,
        ))->locale($locale);
    }

    /** The website's registration page in the person's language, with the email address filled in. */
    public static function registerUrl(string $locale, ?string $email = null): string
    {
        $url = route('site.account.register', array_filter(['lang' => $locale, 'email' => $email]));

        return $url;
    }

    /** The ready-made invitation to register on the website (en, nl, fr, es), as a draft to plan. */
    public static function invitationDraft(): Message
    {
        $subject = [
            'en' => '{name}, your own CLUK account on our new website',
            'nl' => '{name}, je eigen CLUK-account op onze nieuwe website',
            'fr' => '{name}, votre propre compte CLUK sur notre nouveau site',
            'es' => '{name}, tu propia cuenta CLUK en nuestra nueva web',
        ];
        $body = [
            'en' => "Hi {name},\n\nOur new website is online, and as a customer of CLUK you can now have your own account on it.\n\nWith your account you can:\n• see our dry-hire and sales range with your own prices\n• put together a dry-hire or sales basket and send it to us as a request\n• follow your quotes, orders and invoices, and download your invoices\n• add colleagues to your company account\n\nRegistering takes two minutes. We then link your account to your company, and from that moment you see your own prices and conditions.\n\nQuestions? Just reply to this mail.",
            'nl' => "Hallo {name},\n\nOnze nieuwe website staat online, en als klant van CLUK kan je er nu je eigen account op aanmaken.\n\nMet je account kan je:\n• ons dry-hire- en verkoopaanbod bekijken met je eigen prijzen\n• een dry-hire- of verkoopmandje samenstellen en het als aanvraag naar ons sturen\n• je offertes, orders en facturen opvolgen en je facturen downloaden\n• collega's toevoegen aan je bedrijfsaccount\n\nRegistreren duurt twee minuten. Daarna koppelen wij je account aan je bedrijf, en vanaf dan zie je je eigen prijzen en voorwaarden.\n\nVragen? Antwoord gewoon op deze mail.",
            'fr' => "Bonjour {name},\n\nNotre nouveau site est en ligne et, en tant que client de CLUK, vous pouvez maintenant y créer votre propre compte.\n\nAvec votre compte, vous pouvez :\n• consulter notre offre de location (dry-hire) et de vente avec vos propres prix\n• composer un panier de location ou de vente et nous l’envoyer comme demande\n• suivre vos offres, commandes et factures, et télécharger vos factures\n• ajouter des collègues au compte de votre société\n\nL’inscription prend deux minutes. Nous lions ensuite votre compte à votre société et, dès ce moment, vous voyez vos propres prix et conditions.\n\nDes questions ? Répondez simplement à ce mail.",
            'es' => "Hola {name}:\n\nNuestra nueva web ya está en línea y, como cliente de CLUK, ahora puedes crear tu propia cuenta en ella.\n\nCon tu cuenta puedes:\n• ver nuestra oferta de alquiler (dry-hire) y de venta con tus propios precios\n• preparar una cesta de alquiler o de venta y enviárnosla como solicitud\n• seguir tus presupuestos, pedidos y facturas, y descargar tus facturas\n• añadir compañeros a la cuenta de tu empresa\n\nRegistrarte lleva dos minutos. Después vinculamos tu cuenta a tu empresa y, desde ese momento, ves tus propios precios y condiciones.\n\n¿Preguntas? Responde a este correo.",
        ];
        return Message::create([
            'kind' => 'invitation', 'title' => 'Invitation to register on the new website', 'status' => 'draft',
            'subject' => $subject, 'body' => $body,
            'source_hash' => hash('sha256', $subject['en']."\n".$body['en']),
            'plan' => ['countries' => self::FIRST_COUNTRIES, 'per_day' => 100],
            'sent_count' => 0,
        ]);
    }
}
