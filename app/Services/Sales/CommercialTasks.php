<?php

namespace App\Services\Sales;

use App\Models\Company;
use App\Models\ProductSales\SalesQuote;
use App\Models\Sales\Quote;
use App\Models\Sales\SalesTask;
use App\Services\Credit\CreditCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Commercial tasks, made and closed by the app itself (every 15 minutes):
 *  - card:  a customer card that misses or has wrong information (only customers we work with);
 *  - quote: a quote that is open too long (yellow 5 days, orange 10, red 20 or past its validity);
 *  - web:   a customer of ours active on the website (also when they came via social media).
 * Colours: red = act now, orange = important, yellow = needs attention, blue = opportunity.
 * A task always goes to the account owner of the customer; customers without an owner get no tasks
 * (Management shows how many there are, to give them one).
 * A task disappears by itself when the reason is gone; a salesperson can also mark it done or snooze it.
 */
class CommercialTasks
{
    /** Days a sent quote may stay open before it turns yellow, orange, red. */
    public const QUOTE_DAYS = [5, 10, 20];

    /** Website activity of the last days that counts. */
    public const WEB_DAYS = 3;

    private const SOCIAL = ['facebook', 'instagram', 'linkedin', 'tiktok', 'youtube', 'threads', 'fb', 'ig', 'l.facebook.com', 'lm.facebook.com', 'm.facebook.com', 'l.instagram.com', 'lnkd.in'];

    /** Make and close tasks. Returns the number of open tasks. */
    public static function refresh(): int
    {
        $seen = [];
        foreach ([...self::quoteTasks(), ...self::cardTasks(), ...self::webTasks()] as $t) {
            $seen[] = self::upsert($t);
        }
        $seen = array_values(array_filter($seen));
        // Tasks of customers that lost their owner close too.
        SalesTask::whereIn('status', ['open', 'snoozed'])->whereNull('owner_id')->update(['status' => 'resolved', 'handled_at' => now()]);
        // Reasons that are gone: the task closes by itself (website tasks close after a week).
        SalesTask::whereIn('kind', ['card', 'quote'])->whereIn('status', ['open', 'snoozed'])->whereNotIn('key', $seen)
            ->update(['status' => 'resolved', 'handled_at' => now()]);
        SalesTask::where('kind', 'web')->whereIn('status', ['open', 'snoozed'])->where('updated_at', '<', now()->subDays(7))
            ->update(['status' => 'resolved', 'handled_at' => now()]);
        Cache::put('sales_tasks.refreshed', now()->timestamp, 86400);

        return SalesTask::open()->count();
    }

    /** At most every few minutes (opening the task list refreshes when it is older). */
    public static function refreshIfStale(int $minutes = 5): void
    {
        if ((int) Cache::get('sales_tasks.refreshed', 0) < now()->subMinutes($minutes)->timestamp) {
            try {
                self::refresh();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** New task, or the same one updated (a higher level re-opens a task someone dismissed). Null: no owner, no task. */
    private static function upsert(array $t): ?string
    {
        if (! $t['owner_id']) {
            return null;
        }
        $task = SalesTask::firstOrNew(['key' => $t['key']]);
        $rank = fn (?string $l) => array_search($l, SalesTask::LEVELS, true);
        $worse = $task->exists && $rank($t['level']) < $rank($task->level);
        $values = $t;
        if (! $task->exists || $task->status === 'resolved' || ($worse && in_array($task->status, ['dismissed', 'done'], true))) {
            $values += ['status' => 'open', 'handled_at' => null, 'handled_by_id' => null, 'snoozed_until' => null];
        }
        $task->fill($values);
        if ($task->isDirty()) {
            $task->save();
        }

        return $t['key'];
    }

    // ── Quotes open too long ────────────────────────────────────────────────

    private static function quoteLevel(?Carbon $sent, ?Carbon $validUntil): ?string
    {
        if (! $sent) {
            return null;
        }
        $days = (int) $sent->copy()->startOfDay()->diffInDays(today());
        [$y, $o, $r] = self::QUOTE_DAYS;

        return match (true) {
            ($validUntil && $validUntil->lt(today())) || $days >= $r => 'red',
            $days >= $o => 'orange',
            $days >= $y => 'yellow',
            default => null,
        };
    }

    private static function quoteTasks(): array
    {
        $out = [];
        $owners = Company::whereNotNull('account_owner_id')->pluck('account_owner_id', 'id');
        foreach (Quote::where('status', 'sent')->get() as $q) {
            if (! $q->isLatest() || ! ($level = self::quoteLevel($q->sent_at, $q->valid_until))) {
                continue;
            }
            $out[] = ['key' => 'quote:'.$q->id, 'kind' => 'quote', 'company_id' => $q->company_id, 'contact_id' => $q->contact_id, 'quote_id' => $q->id,
                'owner_id' => $owners[$q->company_id] ?? null, 'level' => $level, 'data' => ['number' => $q->number, 'title' => $q->title, 'cents' => (int) $q->net_cents,
                    'days' => (int) $q->sent_at->copy()->startOfDay()->diffInDays(today()), 'expired' => (bool) ($q->valid_until && $q->valid_until->lt(today())),
                    'url' => route('quotes.show', $q)]];
        }
        if (Schema::hasTable('sales_quotes')) {
            foreach (SalesQuote::where('status', 'sent')->get() as $q) {
                if (! ($level = self::quoteLevel($q->sent_at, $q->valid_until))) {
                    continue;
                }
                $out[] = ['key' => 'squote:'.$q->id, 'kind' => 'quote', 'company_id' => $q->company_id, 'contact_id' => $q->contact_id, 'quote_id' => null,
                    'owner_id' => $owners[$q->company_id] ?? null, 'level' => $level, 'data' => ['number' => $q->number, 'title' => $q->title, 'cents' => (int) $q->net_cents,
                        'days' => (int) $q->sent_at->copy()->startOfDay()->diffInDays(today()), 'expired' => (bool) ($q->valid_until && $q->valid_until->lt(today())),
                        'url' => route('psales.show', $q)]];
            }
        }

        return $out;
    }

    // ── Customer cards ──────────────────────────────────────────────────────

    /** Customers we work with: a quote or order in the last two years, an open deal, or a website account. */
    private static function activeCustomerIds(): Collection
    {
        $since = now()->subYears(2);
        $ids = collect()
            ->merge(DB::table('quotes')->where('created_at', '>=', $since)->whereNotNull('company_id')->pluck('company_id'))
            ->merge(DB::table('orders')->where('created_at', '>=', $since)->whereNotNull('company_id')->pluck('company_id'))
            ->merge(DB::table('deals')->whereNotIn('stage', ['won', 'lost'])->whereNotNull('company_id')->pluck('company_id'));
        if (Schema::hasTable('sales_quotes')) {
            $ids = $ids->merge(DB::table('sales_quotes')->where('created_at', '>=', $since)->whereNotNull('company_id')->pluck('company_id'));
        }
        if (Schema::hasColumn('customer_accounts', 'company_id')) {
            $ids = $ids->merge(DB::table('customer_accounts')->whereNotNull('company_id')->pluck('company_id'));
        }

        return $ids->unique()->values();
    }

    /** What is missing or wrong on a customer card. */
    public static function cardProblems(Company $c): array
    {
        $p = [];
        // No phone is fine: many companies no longer have a fixed line.
        if (blank($c->email)) {
            $p[] = 'no_email';
        }
        if (blank($c->street) || blank($c->postcode) || blank($c->city)) {
            $p[] = 'no_address';
        }
        if (blank($c->vat_number)) {
            $p[] = 'no_vat';
        } elseif ($c->vies_status === 'invalid') {
            $p[] = 'vat_invalid';
        }
        if (! $c->contacts()->exists()) {
            $p[] = 'no_contact';
        } elseif (! $c->contacts()->whereNotNull('email')->where('email', '!=', '')->exists()) {
            $p[] = 'no_contact_email';
        }
        try {
            if (CreditCheck::for($c)['level'] === 'red') {
                $p[] = 'credit_red';
            }
        } catch (Throwable) {
        }

        return $p;
    }

    private static function cardTasks(): array
    {
        $ids = self::activeCustomerIds();
        if ($ids->isEmpty()) {
            return [];
        }
        $busy = collect()
            ->merge(DB::table('quotes')->where('status', 'sent')->pluck('company_id'))
            ->merge(DB::table('orders')->whereIn('status', ['allocation', 'approved'])->pluck('company_id'))
            ->filter()->unique()->flip();
        $out = [];
        foreach (Company::active()->whereNotNull('account_owner_id')->whereIn('id', $ids)->get() as $c) {
            $problems = self::cardProblems($c);
            if (! $problems) {
                continue;
            }
            // Red: wrong information or a red credit check; orange: missing what invoicing needs while there is work running.
            $level = array_intersect($problems, ['credit_red', 'vat_invalid']) ? 'red'
                : (isset($busy[$c->id]) && array_intersect($problems, ['no_vat', 'no_address', 'no_email']) ? 'orange' : 'yellow');
            $out[] = ['key' => 'card:'.$c->id, 'kind' => 'card', 'company_id' => $c->id, 'contact_id' => null, 'quote_id' => null,
                'owner_id' => $c->account_owner_id, 'level' => $level, 'data' => ['problems' => $problems, 'url' => route('companies.show', $c)]];
        }

        return $out;
    }

    // ── Website (and social media) activity of our customers ────────────────

    private static function viaSocial(object $v): ?string
    {
        foreach ([Str::lower((string) $v->utm_source), Str::lower((string) parse_url(str_contains((string) $v->referrer, '://') ? $v->referrer : 'https://'.$v->referrer, PHP_URL_HOST))] as $s) {
            $s = preg_replace('/^www\./', '', $s);
            foreach (self::SOCIAL as $name) {
                if ($s !== '' && str_contains($s, $name)) {
                    return match (true) {
                        str_contains($s, 'insta') || $s === 'ig' => 'Instagram',
                        str_contains($s, 'face') || $s === 'fb' => 'Facebook',
                        str_contains($s, 'linked') || $s === 'lnkd.in' => 'LinkedIn',
                        default => Str::ucfirst($name),
                    };
                }
            }
        }

        return null;
    }

    /** Website visits and downloads by people of a company we know. */
    private static function webTasks(): array
    {
        $out = collect(self::webEventTasks())->keyBy('company_id');
        if (! Schema::hasTable('web_download_logs')) {
            return $out->values()->all();
        }
        // Downloads count even without statistics cookies: we know who from their login or the name and email they gave.
        $logs = DB::table('web_download_logs')->join('web_downloads', 'web_downloads.id', '=', 'web_download_logs.download_id')
            ->where('web_download_logs.created_at', '>=', now()->subDays(self::WEB_DAYS))->whereNotNull('web_download_logs.company_id')
            ->get(['web_download_logs.company_id', 'web_download_logs.contact_id', 'web_download_logs.created_at', 'web_downloads.title']);
        if ($logs->isEmpty()) {
            return $out->values()->all();
        }
        $companies = Company::active()->whereIn('id', $logs->pluck('company_id')->unique())->get()->keyBy('id');
        foreach ($logs->groupBy('company_id') as $companyId => $rows) {
            $c = $companies->get($companyId);
            if (! $c) {
                continue;
            }
            $titles = $rows->map(fn ($r) => (json_decode($r->title, true)['en'] ?? null) ?: collect(json_decode($r->title, true))->first())->filter()->unique()->take(3)->values()->all();
            $last = Carbon::parse($rows->max('created_at'));
            $t = $out->get($companyId) ?? ['key' => 'web:'.$companyId.':'.$last->format('o-W'), 'kind' => 'web', 'company_id' => $companyId, 'contact_id' => $rows->pluck('contact_id')->filter()->first(),
                'quote_id' => null, 'owner_id' => $c->account_owner_id, 'level' => 'blue', 'data' => ['views' => 0, 'products' => 0, 'basket' => 0, 'searches' => [], 'social' => [], 'sections' => [], 'last' => $last->toIso8601String(), 'url' => route('companies.show', $c)]];
            $t['data']['downloads'] = $titles;
            $t['contact_id'] ??= $rows->pluck('contact_id')->filter()->first();
            $out->put($companyId, $t);
        }

        return $out->values()->all();
    }

    private static function webEventTasks(): array
    {
        if (! Schema::hasTable('web_events')) {
            return [];
        }
        $events = DB::table('web_events')->where('created_at', '>=', now()->subDays(self::WEB_DAYS))->get(['visitor_id', 'type', 'section', 'item_id', 'label', 'created_at']);
        if ($events->isEmpty()) {
            return [];
        }
        $visitors = DB::table('web_visitors')->whereIn('id', $events->pluck('visitor_id')->unique())->get()->keyBy('id');
        // Who is who: the visitor's company, the company of their contact, or a contact with the same email.
        $contactCompany = DB::table('company_contact')->whereIn('contact_id', $visitors->pluck('contact_id')->filter())->pluck('company_id', 'contact_id');
        $emails = $visitors->pluck('email')->filter()->map(fn ($e) => Str::lower($e))->unique()->values()->all();
        $byEmail = $emails ? DB::table('contacts')->join('company_contact', 'company_contact.contact_id', '=', 'contacts.id')
            ->whereIn(DB::raw('lower(contacts.email)'), $emails)->get(['contacts.id', 'contacts.email', 'company_contact.company_id'])
            ->keyBy(fn ($r) => Str::lower($r->email)) : collect();
        $perCompany = [];
        foreach ($visitors as $v) {
            $company = $v->company_id ?? ($v->contact_id ? ($contactCompany[$v->contact_id] ?? null) : null) ?? ($v->email ? ($byEmail[Str::lower($v->email)]->company_id ?? null) : null);
            $contact = $v->contact_id ?? ($v->email ? ($byEmail[Str::lower($v->email)]->id ?? null) : null);
            if ($company) {
                $perCompany[$company]['visitors'][] = $v;
                $perCompany[$company]['contact'] ??= $contact;
            }
        }
        if (! $perCompany) {
            return [];
        }
        $companies = Company::active()->whereIn('id', array_keys($perCompany))->get()->keyBy('id');
        $out = [];
        foreach ($perCompany as $companyId => $info) {
            $c = $companies->get($companyId);
            if (! $c) {
                continue;
            }
            $ids = collect($info['visitors'])->pluck('id')->all();
            $ev = $events->whereIn('visitor_id', $ids);
            $products = $ev->whereIn('type', ['product', 'add'])->count();
            $basket = $ev->where('type', 'add')->count();
            $searches = $ev->where('type', 'search')->pluck('label')->filter()->unique()->take(3)->values()->all();
            $views = $ev->count();
            $social = collect($info['visitors'])->map(fn ($v) => self::viaSocial($v))->filter()->unique()->values()->all();
            // Worth a call: something in the basket, several products looked at, a search, or coming from our social media.
            if (! $basket && $products < 3 && ! $searches && ! $social) {
                continue;
            }
            $last = Carbon::parse($ev->max('created_at'));
            $out[] = ['key' => 'web:'.$companyId.':'.$last->format('o-W'), 'kind' => 'web', 'company_id' => $companyId, 'contact_id' => $info['contact'], 'quote_id' => null,
                'owner_id' => $c->account_owner_id, 'level' => $basket ? 'orange' : 'blue',
                'data' => ['views' => $views, 'products' => $products, 'basket' => $basket, 'searches' => $searches, 'social' => $social,
                    'sections' => $ev->pluck('section')->filter()->unique()->values()->all(), 'last' => $last->toIso8601String(), 'url' => route('companies.show', $c)]];
        }

        return $out;
    }
}
