<?php

namespace App\Services\Website;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Product\Category;
use App\Models\ProductSales\SalesItem;
use App\Models\Sales\IncomingMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only views on the website statistics (web_visitors, web_events) that
 * the website keeps for visitors who accepted statistics cookies. Kept for
 * 13 months, then deleted (prune()).
 */
class WebActivity
{
    public const KEEP_MONTHS = 13;

    /** Two events further apart than this are two visits. */
    private const VISIT_GAP_MINUTES = 30;

    // ── Who is who ──────────────────────────────────────────────────────────

    /** Visitor ids of a contact: linked by contact or by the same email. */
    public static function visitorsOfContact(Contact $c): Collection
    {
        return DB::table('web_visitors')->where(fn ($q) => $q->where('contact_id', $c->id)
            ->when($c->email, fn ($w) => $w->orWhereRaw('lower(email) = ?', [Str::lower($c->email)])))->pluck('id');
    }

    /** Visitor ids of a company: linked to the company, to one of its contacts, or by their email. */
    public static function visitorsOfCompany(Company $co): Collection
    {
        $contacts = $co->contacts()->get(['contacts.id', 'contacts.email']);
        $emails = $contacts->pluck('email')->filter()->map(fn ($e) => Str::lower($e))->values()->all();

        return DB::table('web_visitors')->where(fn ($q) => $q->where('company_id', $co->id)
            ->orWhereIn('contact_id', $contacts->pluck('id')->all())
            ->when($emails, fn ($w) => $w->orWhereIn(DB::raw('lower(email)'), $emails)))->pluck('id');
    }

    // ── Cards ───────────────────────────────────────────────────────────────

    /**
     * Summary for a contact or company card.
     *
     * @return array{visitors: int, visits: int, first: ?Carbon, last: ?Carbon, sources: array, searches: array, products: array, categories: array, requests: int, devices: array}|null
     */
    public static function summary(Collection $visitorIds): ?array
    {
        if ($visitorIds->isEmpty()) {
            return null;
        }
        $visitors = DB::table('web_visitors')->whereIn('id', $visitorIds)->get();
        $events = DB::table('web_events')->whereIn('visitor_id', $visitorIds)->orderBy('created_at')->get();
        if ($events->isEmpty()) {
            return null;
        }

        return [
            'visitors' => $visitors->count(),
            'visits' => self::visits($events),
            'first' => Carbon::parse($events->first()->created_at),
            'last' => Carbon::parse($events->last()->created_at),
            'sources' => $visitors->map(fn ($v) => self::source($v))->countBy()->sortDesc()->take(4)->all(),
            'searches' => $events->where('type', 'search')->map(fn ($e) => Str::lower(trim((string) $e->label)))->filter()->countBy()->sortDesc()->take(6)->all(),
            'products' => self::products($events->whereIn('type', ['product', 'add'])->whereNotNull('item_id'), 6),
            'categories' => self::categories($events->whereIn('type', ['product', 'add'])->whereNotNull('item_id'), 4),
            'requests' => $events->whereIn('type', ['request', 'order'])->count(),
            'devices' => $visitors->pluck('device')->filter()->countBy()->all(),
        ];
    }

    /**
     * What the person who sent this request did on the website before it:
     * the visitor(s) linked to the request (meta.incoming_id) or to the same email.
     *
     * @return array{summary: ?array, timeline: list<array>}
     */
    public static function beforeRequest(IncomingMessage $m): array
    {
        $ids = DB::table('web_events')->where('type', 'request')->where('label', 'W-'.$m->id)->pluck('visitor_id');
        if ($ids->isEmpty() && $m->from_email) {
            $ids = DB::table('web_visitors')->whereRaw('lower(email) = ?', [Str::lower($m->from_email)])->pluck('id');
        }
        if ($ids->isEmpty()) {
            return ['summary' => null, 'timeline' => []];
        }
        $until = $m->received_at ?? now();
        $events = DB::table('web_events')->whereIn('visitor_id', $ids)->where('created_at', '<=', $until->copy()->addMinute())
            ->orderByDesc('created_at')->limit(60)->get()->reverse()->values();
        $names = self::names($events->whereNotNull('item_id'));

        $timeline = $events->map(fn ($e) => [
            'at' => Carbon::parse($e->created_at), 'type' => $e->type, 'section' => $e->section,
            'text' => match ($e->type) {
                'product', 'add' => $names[$e->section.':'.$e->item_id] ?? $e->label ?? '#'.$e->item_id,
                'search' => $e->label,
                'view' => $e->path,
                default => $e->label,
            },
            'meta' => $e->meta ? json_decode($e->meta, true) : [],
        ])->all();

        return ['summary' => self::summary($ids), 'timeline' => $timeline];
    }

    // ── Management ──────────────────────────────────────────────────────────

    public static function overview(int $days = 30): array
    {
        $from = today()->subDays($days - 1);
        $events = DB::table('web_events')->where('created_at', '>=', $from)->whereNotIn('visitor_id', DB::table('web_visitors')->where('internal', true)->select('id'))->get(['visitor_id', 'type', 'section', 'label', 'item_id', 'meta', 'created_at']);
        $perDay = collect(range(0, $days - 1))->mapWithKeys(fn ($i) => [$from->copy()->addDays($i)->toDateString() => 0])->all();
        foreach ($events->where('type', 'view')->groupBy(fn ($e) => substr($e->created_at, 0, 10)) as $day => $list) {
            if (isset($perDay[$day])) {
                $perDay[$day] = $list->pluck('visitor_id')->unique()->count();
            }
        }
        $searches = $events->where('type', 'search')->filter(fn ($e) => trim((string) $e->label) !== '');
        $noResults = $searches->filter(fn ($e) => (int) (json_decode((string) $e->meta, true)['results'] ?? -1) === 0);
        $visitorIds = $events->pluck('visitor_id')->unique();
        $visitors = DB::table('web_visitors')->whereIn('id', $visitorIds)->get()->keyBy('id');
        $requests = $events->whereIn('type', ['request', 'order']);

        return [
            'from' => $from, 'days' => $perDay,
            'visitors' => $visitorIds->count(),
            'views' => $events->where('type', 'view')->count(),
            'searches_n' => $searches->count(),
            'requests_n' => $requests->count(),
            'no_results' => $noResults->map(fn ($e) => Str::lower(trim($e->label)))->countBy()->sortDesc()->take(15)->all(),
            'searches' => $searches->map(fn ($e) => Str::lower(trim($e->label)))->countBy()->sortDesc()->take(15)->all(),
            'products' => self::products($events->whereIn('type', ['product', 'add'])->whereNotNull('item_id'), 15),
            'sources' => $visitors->map(fn ($v) => self::source($v))->countBy()->sortDesc()->take(10)->all(),
            'request_sources' => $requests->map(fn ($e) => isset($visitors[$e->visitor_id]) ? self::source($visitors[$e->visitor_id]) : '—')->countBy()->sortDesc()->all(),
            'devices' => $visitors->pluck('device')->filter()->countBy()->sortDesc()->all(),
            'linked' => $visitors->filter(fn ($v) => $v->email || $v->contact_id || $v->company_id)->count(),
        ];
    }

    // ── Retention (GDPR) ────────────────────────────────────────────────────

    /** Deletes website events older than 13 months, and visitors not seen since. @return array{events: int, visitors: int} */
    public static function prune(): array
    {
        $limit = now()->subMonths(self::KEEP_MONTHS);
        $events = DB::table('web_events')->where('created_at', '<', $limit)->delete();
        $visitors = DB::table('web_visitors')->where(fn ($q) => $q->where('last_seen_at', '<', $limit)->orWhere(fn ($w) => $w->whereNull('last_seen_at')->where('created_at', '<', $limit)))->delete();

        return ['events' => $events, 'visitors' => $visitors];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private static function visits(Collection $events): int
    {
        $n = 0;
        foreach ($events->groupBy('visitor_id') as $list) {
            $prev = null;
            foreach ($list as $e) {
                $t = Carbon::parse($e->created_at);
                if (! $prev || $prev->diffInMinutes($t) > self::VISIT_GAP_MINUTES) {
                    $n++;
                }
                $prev = $t;
            }
        }

        return $n;
    }

    /** Where a visitor came from: a campaign (utm), another site, or directly. */
    public static function source(object $v): string
    {
        if ($v->utm_source) {
            return Str::lower($v->utm_source).($v->utm_medium ? ' / '.Str::lower($v->utm_medium) : '').($v->utm_campaign ? ' · '.$v->utm_campaign : '');
        }
        $host = $v->referrer ? parse_url(str_contains($v->referrer, '://') ? $v->referrer : 'https://'.$v->referrer, PHP_URL_HOST) : null;
        $host = $host ? preg_replace('/^www\./', '', Str::lower($host)) : null;
        $own = array_filter([config('website.host'), parse_url((string) config('app.url'), PHP_URL_HOST)]);

        return $host && ! in_array($host, $own, true) ? $host : __('webstats.direct');
    }

    /** Names of dry-hire items and sales products, keyed "section:id". */
    private static function names(Collection $events): array
    {
        $out = [];
        $dry = $events->filter(fn ($e) => $e->section !== 'sales')->pluck('item_id')->unique()->all();
        $sales = $events->where('section', 'sales')->pluck('item_id')->unique()->all();
        foreach (Item::whereIn('id', $dry)->get(['id', 'name']) as $i) {
            foreach (['dryhire', ''] as $s) {
                $out[$s.':'.$i->id] = (string) $i->name;
            }
        }
        foreach (SalesItem::whereIn('id', $sales)->get(['id', 'brand', 'type']) as $i) {
            $out['sales:'.$i->id] = $i->name();
        }

        return $out;
    }

    /** Most opened products: [['name' => …, 'section' => …, 'n' => …], …] */
    private static function products(Collection $events, int $take): array
    {
        $names = self::names($events);

        return $events->groupBy(fn ($e) => ($e->section === 'sales' ? 'sales' : 'dryhire').':'.$e->item_id)
            ->map(fn ($g, $k) => ['name' => $names[$k] ?? ($g->first()->label ?: '#'.$g->first()->item_id), 'section' => explode(':', $k)[0], 'n' => $g->count()])
            ->sortByDesc('n')->take($take)->values()->all();
    }

    /** Most viewed categories (from the products opened). */
    private static function categories(Collection $events, int $take): array
    {
        $dry = Item::whereIn('id', $events->filter(fn ($e) => $e->section !== 'sales')->pluck('item_id')->unique())->pluck('category_id', 'id');
        $sales = SalesItem::whereIn('id', $events->where('section', 'sales')->pluck('item_id')->unique())->pluck('category_id', 'id');
        $cats = Category::whereIn('id', $dry->merge($sales)->filter()->unique())->get()->keyBy('id');

        return $events->map(fn ($e) => ($e->section === 'sales' ? $sales : $dry)[$e->item_id] ?? null)->filter()
            ->countBy()->sortDesc()->take($take)->mapWithKeys(fn ($n, $id) => [$cats->get($id)?->label() ?? '?' => $n])->all();
    }
}
