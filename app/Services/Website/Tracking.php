<?php

namespace App\Services\Website;

use App\Models\Contact;
use App\Models\Sales\IncomingMessage;
use App\Models\Web\CustomerAccount;
use App\Services\Web\CustomerAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Visitor behaviour on the website, kept only with consent (cookie cluk_consent=all).
 * The visitor id is a random UUID in the first-party cookie cluk_vid; no IP is stored.
 * Requests, orders and sign-ins link the visitor to an email, contact, company or login.
 */
class Tracking
{
    public const CONSENT = 'cluk_consent';

    public const VISITOR = 'cluk_vid';

    public const TYPES = ['view', 'search', 'product', 'add', 'request', 'order', 'login', 'register', 'download'];

    public static function consented(Request $r): bool
    {
        return $r->cookie(self::CONSENT) === 'all';
    }

    /** The visitor row for this browser (made on the first event), or null without consent. */
    public static function visitor(Request $r, bool $make = false): ?object
    {
        if (! self::consented($r)) {
            return null;
        }
        $uuid = (string) $r->cookie(self::VISITOR);
        if (! Str::isUuid($uuid)) {
            return null;
        }
        $v = DB::table('web_visitors')->where('uuid', $uuid)->first();
        if (! $v && $make) {
            $id = DB::table('web_visitors')->insertGetId([
                'uuid' => $uuid, 'locale' => app()->getLocale(), 'device' => self::device((string) $r->userAgent()),
                'referrer' => Str::limit((string) $r->input('referrer'), 250, ''), 'landing' => Str::limit((string) $r->input('path'), 250, ''),
                'utm_source' => Str::limit((string) $r->input('utm.source'), 100, '') ?: null, 'utm_medium' => Str::limit((string) $r->input('utm.medium'), 100, '') ?: null,
                'utm_campaign' => Str::limit((string) $r->input('utm.campaign'), 100, '') ?: null,
                'first_seen_at' => now(), 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $v = DB::table('web_visitors')->find($id);
        }

        return $v;
    }

    public static function record(Request $r, string $type, array $d = []): void
    {
        if (! in_array($type, self::TYPES, true) || ! ($v = self::visitor($r, true))) {
            return;
        }
        DB::table('web_events')->insert([
            'visitor_id' => $v->id, 'type' => $type, 'section' => Str::limit((string) ($d['section'] ?? ''), 10, '') ?: null,
            'path' => Str::limit((string) ($d['path'] ?? ''), 250, '') ?: null, 'label' => Str::limit((string) ($d['label'] ?? ''), 250, '') ?: null,
            'item_id' => isset($d['item_id']) && is_numeric($d['item_id']) ? (int) $d['item_id'] : null,
            'meta' => ! empty($d['meta']) ? json_encode($d['meta'], JSON_UNESCAPED_UNICODE) : null, 'created_at' => now(),
        ]);
        $upd = ['last_seen_at' => now(), 'updated_at' => now()];
        if ($type === 'view') {
            $upd['views'] = DB::raw('views + 1');
        }
        if ($a = CustomerAuth::current()) {
            $upd += ['customer_account_id' => $a->id, 'email' => $a->email, 'contact_id' => $a->contact_id, 'company_id' => $a->company_id];
        }
        DB::table('web_visitors')->where('id', $v->id)->update($upd);
    }

    /** A request or order was sent: link this browser's visits to who sent it. */
    public static function linkRequest(Request $r, IncomingMessage $m, string $type = 'request'): void
    {
        if (! ($v = self::visitor($r, true))) {
            return;
        }
        DB::table('web_visitors')->where('id', $v->id)->update(array_filter([
            'email' => $m->from_email, 'contact_id' => $m->contact_id, 'company_id' => $m->company_id, 'updated_at' => now(),
        ]));
        self::record($r, $type, ['section' => $m->module, 'label' => 'W-'.$m->id, 'meta' => ['incoming_id' => $m->id]]);
    }

    public static function linkAccount(Request $r, CustomerAccount $a, string $type = 'login'): void
    {
        if (! ($v = self::visitor($r, true))) {
            return;
        }
        DB::table('web_visitors')->where('id', $v->id)->update(array_filter([
            'customer_account_id' => $a->id, 'email' => $a->email, 'contact_id' => $a->contact_id ?? Contact::where('email', $a->email)->value('id'), 'company_id' => $a->company_id, 'updated_at' => now(),
        ]));
        self::record($r, $type, ['section' => 'account']);
    }

    private static function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|Android|iPhone/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }
}
