<?php

namespace App\Services\Afrit6;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Member;
use App\Notifications\Confirmation;
use App\Support\InLocale;
use App\Support\SafeMail;

/**
 * "This is what you just did" emails for Afrit 6 members, in the member's
 * language, plus the team's answers on their requests.
 */
class MemberMails
{
    /** @param array<string, string> $details */
    private static function send(Member $m, string $key, array $params, callable $details, array $extraIntro = [], bool $next = false, ?string $nextKey = null): bool
    {
        $mail = InLocale::run($m->locale ?: 'nl', function () use ($m, $key, $params, $details, $extraIntro, $next, $nextKey) {
            $intro = array_merge([__('mails.afrit6.'.$key.'.intro', $params)], $extraIntro);
            $nextLines = $next ? [__('mails.afrit6.'.($nextKey ?? $key).'.next', $params)] : [];

            return new Confirmation(
                subject: __('mails.afrit6.'.$key.'.subject', $params),
                greeting: __('mails.hello', ['name' => $m->first_name]),
                intro: $intro,
                details: array_filter($details(), fn ($v) => $v !== null && $v !== ''),
                next: $nextLines,
                button: [__('mails.afrit6.button'), route('afrit6.portal')],
                team: __('mails.team_afrit6'),
            );
        });

        return SafeMail::notify($m, $mail->locale($m->locale ?: 'nl'));
    }

    private static function day($date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat('l j F Y');
    }

    public static function deskBooked(Member $m, DeskBooking $b): bool
    {
        return self::send($m, 'desk_booked', ['date' => $b->date->format('d/m')], fn () => [
            __('mails.label.desk') => $b->space->code,
            __('mails.label.date') => self::day($b->date),
            __('mails.label.part') => __('workspace.days.parts.'.$b->part).' ('.implode(' – ', config('afrit6.desk_parts.'.$b->part)).')',
            __('mails.label.price') => Pricing::eur($b->price_cents),
        ]);
    }

    public static function deskCancelled(Member $m, DeskBooking $b): bool
    {
        return self::send($m, 'desk_cancelled', ['date' => $b->date->format('d/m')], fn () => [
            __('mails.label.desk') => $b->space->code,
            __('mails.label.date') => self::day($b->date),
            __('mails.label.part') => __('workspace.days.parts.'.$b->part),
        ]);
    }

    public static function roomBooked(Member $m, RoomBooking $b): bool
    {
        return self::send($m, 'room_booked', ['date' => $b->date->format('d/m')], fn () => [
            __('mails.label.date') => self::day($b->date),
            __('mails.label.time') => $b->timeRange(),
            __('mails.label.setup') => __('workspace.room.setups.'.$b->setup),
            __('mails.label.note') => $b->note,
        ]);
    }

    public static function roomCancelled(Member $m, RoomBooking $b): bool
    {
        return self::send($m, 'room_cancelled', ['date' => $b->date->format('d/m')], fn () => [
            __('mails.label.date') => self::day($b->date),
            __('mails.label.time') => $b->timeRange(),
        ]);
    }

    /** @return callable(): array<string, string|null> */
    private static function requestDetails(ServiceRequest $r): callable
    {
        return fn () => [
            __('mails.label.kind') => __('workspace.kinds.'.$r->kind),
            __('mails.label.date') => self::day($r->date),
            __('mails.label.desk') => $r->space?->code,
            __('mails.label.qty') => $r->qty ? (string) $r->qty : null,
            __('mails.label.colis') => $r->colis ? (string) $r->colis : null,
            __('mails.label.pallets') => $r->pallets ? (string) $r->pallets : null,
            __('mails.label.price') => $r->kind === 'desk_add' ? __('workspace.desks.per_month', ['amount' => Pricing::eur((int) $r->amount_cents)])
                : ($r->isStorage() ? __('workspace.tasks.per_day', ['amount' => Pricing::eur($r->qty * Pricing::dayPrice($r->kind))])
                : ($r->amount_cents ? Pricing::eur((int) $r->amount_cents) : null)),
            __('mails.label.note') => $r->note,
        ];
    }

    public static function requestReceived(Member $m, ServiceRequest $r): bool
    {
        if ($r->kind === 'office') {
            return self::send($m, 'office', [], fn () => [
                __('mails.label.from') => self::day($r->date),
                __('mails.label.people') => (string) $r->qty,
                __('mails.label.note') => $r->note,
            ], [], true);
        }

        return self::send($m, 'request', ['kind' => __('workspace.kinds.'.$r->kind)], self::requestDetails($r), [], true);
    }

    public static function requestCancelled(Member $m, ServiceRequest $r): bool
    {
        return self::send($m, 'request_cancelled', ['kind' => __('workspace.kinds.'.$r->kind)], self::requestDetails($r));
    }

    public static function storageStopped(Member $m, ServiceRequest $r): bool
    {
        return self::send($m, 'storage_stopped', ['kind' => __('workspace.kinds.'.$r->kind)], fn () => [
            __('mails.label.kind') => __('workspace.kinds.'.$r->kind),
            __('mails.label.qty') => (string) $r->qty,
            __('mails.label.last_day') => self::day($r->ends_on),
        ]);
    }

    public static function deskSwapped(Member $m, string $from, string $to, $date): bool
    {
        return self::send($m, 'desk_swapped', ['to' => $to, 'date' => $date->locale($m->locale ?: 'nl')->translatedFormat('j F')], fn () => [
            __('mails.label.desk') => $from.' → '.$to,
            __('mails.label.from') => self::day($date),
        ]);
    }

    public static function confirmed(ServiceRequest $r): bool
    {
        return self::send($r->member, 'confirmed', ['kind' => __('workspace.kinds.'.$r->kind)], self::requestDetails($r));
    }

    public static function declined(ServiceRequest $r): bool
    {
        return self::send($r->member, 'declined', ['kind' => __('workspace.kinds.'.$r->kind)], fn () => [
            __('mails.label.kind') => __('workspace.kinds.'.$r->kind),
            __('mails.label.date') => self::day($r->date),
            __('mails.label.reason') => $r->decline_reason,
        ], [], true);
    }

    public static function question(ServiceRequest $r, string $question): bool
    {
        return self::send($r->member, 'question', ['kind' => __('workspace.kinds.'.$r->kind)], fn () => [
            __('mails.label.kind') => __('workspace.kinds.'.$r->kind),
            __('mails.label.date') => self::day($r->date),
            __('mails.label.question') => $question,
        ], [], true);
    }
}
