<?php

namespace App\Services\Website;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Website\Chat;
use App\Models\Website\ChatMessage;
use App\Notifications\Confirmation;
use App\Support\InLocale;
use App\Support\SafeMail;
use App\Support\StaffMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The team side of Clukie chats: a team member answers in the same chat (the visitor's browser
 * shows it as "CLUK team"), closes it, and the team is told when a chat is handed over or when
 * the visitor writes while waiting for a person.
 */
class TeamChats
{
    /** No new line from the visitor for this long: they left, so the answer also goes by email. */
    public const LEFT_MINUTES = 10;

    /** Mails about one chat to the team: at most one per this many minutes. */
    public const MAIL_GAP_MINUTES = 10;

    public static function reply(Chat $chat, User $by, string $text): ChatMessage
    {
        abort_if($chat->status === 'closed', 409);
        $lastVisitor = $chat->messages()->where('role', 'visitor')->latest('id')->value('created_at');
        $msg = $chat->say('staff', $text, null, $by->id);
        $chat->forceFill(['status' => 'staff', 'staff_user_id' => $chat->staff_user_id ?? $by->id])->save();
        AuditLog::record('chat.replied', $chat, ['chat' => $chat->id, 'to' => $chat->name ?: ($chat->email ?: '—')], $by);
        $left = ! $lastVisitor || \Illuminate\Support\Carbon::parse($lastVisitor)->lt(now()->subMinutes(self::LEFT_MINUTES));
        if ($left && filter_var($chat->email, FILTER_VALIDATE_EMAIL) && self::mailVisitor($chat, $by, $text)) {
            $msg->forceFill(['meta' => ['mailed' => true]])->save();
        }

        return $msg;
    }

    public static function close(Chat $chat, User $by): void
    {
        $chat->forceFill(['status' => 'closed'])->save();
        AuditLog::record('chat.closed', $chat, ['chat' => $chat->id], $by);
    }

    /**
     * Companies this chat could be about: the companies of contacts with the visitor's email, a company with that
     * email, and the company of their website login. Never linked by itself: someone chooses.
     */
    public static function candidates(Chat $chat): Collection
    {
        $email = Str::lower(trim((string) $chat->email));
        $ids = collect();
        if ($email !== '') {
            $ids = $ids->merge(DB::table('company_contact')->join('contacts', 'contacts.id', '=', 'company_contact.contact_id')->whereRaw('lower(contacts.email) = ?', [$email])->pluck('company_contact.company_id'))
                ->merge(DB::table('companies')->whereRaw('lower(email) = ?', [$email])->pluck('id'));
        }
        if ($chat->customer_account_id) {
            $ids = $ids->merge(DB::table('customer_accounts')->where('id', $chat->customer_account_id)->whereNotNull('company_id')->pluck('company_id'));
        }

        return \App\Models\Company::whereIn('id', $ids->unique())->orderBy('name')->get(['id', 'name', 'city', 'vat_number']);
    }

    /** Link the chat (and its Incoming item) to a company, and to the contact with the visitor's email there. */
    public static function link(Chat $chat, ?\App\Models\Company $company, User $by): void
    {
        $contact = $company && $chat->email
            ? $company->contacts()->whereRaw('lower(contacts.email) = ?', [Str::lower(trim($chat->email))])->first()
            : null;
        $chat->forceFill(['company_id' => $company?->id, 'contact_id' => $contact?->id])->save();
        if ($chat->incoming && $company) {
            $chat->incoming->forceFill(['company_id' => $company->id, 'contact_id' => $contact?->id ?? $chat->incoming->contact_id])->save();
        }
        AuditLog::record($company ? 'chat.linked' : 'chat.unlinked', $chat, ['chat' => $chat->id, 'company' => $company?->name ?? '—'], $by);
    }

    /** The visitor left: the answer by email, in the chat's language. */
    public static function mailVisitor(Chat $chat, User $by, string $text): bool
    {
        $loc = array_key_exists($chat->locale, config('cluk.locales')) ? $chat->locale : 'en';
        $mail = InLocale::run($loc, fn () => (new Confirmation(
            subject: __('chats.mail.subject'),
            greeting: __('mails.hello', ['name' => Str::before((string) $chat->name, ' ') ?: '']),
            intro: [__('chats.mail.intro'), $text],
            button: [__('chats.mail.button'), route('site.sales')],
            signer: $by,
        ))->locale($loc));
        return SafeMail::notify(Notification::route('mail', $chat->email), $mail);
    }

    /** Who hears about chats: the team member on it, else everyone who works in Sales. */
    public static function team(Chat $chat): Collection
    {
        if ($chat->staff_user_id && ($u = User::find($chat->staff_user_id)) && $u->isActive()) {
            return collect([$u]);
        }

        return User::where('status', User::STATUS_ACTIVE)->get()->filter(fn (User $u) => $u->canUseModule('sales', 2))->values();
    }

    /** A line was added to a chat: tell the team when it needs a person. */
    public static function noticed(ChatMessage $m): void
    {
        $chat = $m->chat;
        if (! $chat) {
            return;
        }
        $handover = $m->role === 'note' && $m->body === 'handed_over';
        $visitorWaits = $m->role === 'visitor' && in_array($chat->status, ['waiting', 'staff'], true);
        if (! $handover && ! $visitorWaits) {
            return;
        }
        if (! Cache::add('chat-mail:'.$chat->id, 1, self::MAIL_GAP_MINUTES * 60) && ! $handover) {
            return;
        }
        foreach (self::team($chat) as $u) {
            $loc = $u->locale ?: 'en';
            StaffMail::send($u, 'web_chat', InLocale::run($loc, fn () => (new Confirmation(
                subject: __($handover ? 'chats.notify.handed_subject' : 'chats.notify.wrote_subject', ['name' => $chat->name ?: ($chat->email ?: __('chats.visitor'))]),
                greeting: __('mails.hello', ['name' => $u->first_name]),
                intro: [$handover ? __('chats.notify.handed_intro') : __('chats.notify.wrote_intro'), Str::limit($handover ? (string) ($chat->incoming?->payload['summary'] ?? '') : $m->body, 500)],
                button: [__('chats.notify.button'), route('chats.show', $chat)],
            ))->locale($loc)));
        }
    }

    /** Website visits of this visitor (same browser, same email or same login). */
    public static function visitorIds(Chat $chat): Collection
    {
        return DB::table('web_visitors')->where(function ($q) use ($chat) {
            $q->whereRaw('1 = 0');
            if ($chat->visitor_uuid) {
                $q->orWhere('uuid', $chat->visitor_uuid);
            }
            if ($chat->email) {
                $q->orWhereRaw('lower(email) = ?', [Str::lower($chat->email)]);
            }
            if ($chat->customer_account_id) {
                $q->orWhere('customer_account_id', $chat->customer_account_id);
            }
        })->pluck('id');
    }

    /** Chats waiting for a person (dashboard and menu): handed over, or the visitor wrote last. */
    public static function waitingCount(): int
    {
        return Chat::whereIn('status', ['waiting', 'staff'])->get()->filter(fn (Chat $c) => self::needsAnswer($c))->count();
    }

    public static function needsAnswer(Chat $c): bool
    {
        if ($c->status === 'waiting') {
            return true;
        }
        $last = $c->messages()->whereIn('role', ['visitor', 'staff'])->latest('id')->value('role');

        return $c->status === 'staff' && $last === 'visitor';
    }
}
