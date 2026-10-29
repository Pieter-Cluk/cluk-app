<?php

namespace App\Services\Social;

use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\Social\Plan;
use App\Models\Social\Post;
use App\Models\Social\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** The figures for Media › Social media, for the last N days. */
class Dashboard
{
    /** Brand colours per platform (for the charts and the platform badges). */
    public const COLORS = ['facebook' => '#1877f2', 'instagram' => '#d62976', 'linkedin' => '#0a66c2', 'google' => '#34a853', 'youtube' => '#ff0033', 'tiktok' => '#111111', 'threads' => '#555555'];

    public static function build(int $days): array
    {
        $from = today()->subDays($days - 1);
        $prevFrom = $from->copy()->subDays($days);
        $accounts = Account::where('status', '!=', 'off')->orderByRaw("case platform when 'facebook' then 1 when 'instagram' then 2 when 'linkedin' then 3 when 'google' then 4 when 'youtube' then 5 else 9 end")->get();
        $ids = $accounts->pluck('id');
        $daily = Daily::whereIn('account_id', $ids)->where('date', '>=', $prevFrom)->orderBy('date')->get()->groupBy('account_id');

        $per = $accounts->map(function (Account $a) use ($daily, $from) {
            $rows = $daily[$a->id] ?? collect();
            $cur = $rows->filter(fn ($r) => $r->date->gte($from));
            $prev = $rows->filter(fn ($r) => $r->date->lt($from));
            $followers = $rows->whereNotNull('followers')->last()?->followers;
            $startFollowers = $cur->whereNotNull('followers')->first()?->followers ?? $prev->whereNotNull('followers')->last()?->followers;

            return [
                'account' => $a,
                'followers' => $followers,
                'followers_delta' => $followers !== null && $startFollowers !== null ? $followers - $startFollowers : null,
                'views' => (int) $cur->sum('views'), 'views_prev' => (int) $prev->sum('views'),
                'reach' => (int) $cur->sum('reach'),
                'engagements' => (int) $cur->sum('engagements'), 'engagements_prev' => (int) $prev->sum('engagements'),
                'clicks' => (int) $cur->sum('clicks'),
                'posts' => Post::where('account_id', $a->id)->where('published_at', '>=', $from)->count(),
            ];
        });

        $sum = fn (string $k) => (int) $per->sum($k);

        return [
            'from' => $from, 'days' => $days,
            'accounts' => $accounts, 'per' => $per,
            'kpi' => [
                'followers' => $per->sum(fn ($p) => (int) $p['followers']), 'followers_delta' => $per->sum(fn ($p) => (int) $p['followers_delta']),
                'views' => $sum('views'), 'views_prev' => $sum('views_prev'),
                'engagements' => $sum('engagements'), 'engagements_prev' => $sum('engagements_prev'),
                'rate' => $sum('views') > 0 ? $sum('engagements') / $sum('views') * 100 : null,
                'posts' => $sum('posts'),
            ],
            'followers_series' => self::followerSeries($accounts, $daily, $from, $days),
            'weekly' => self::weekly($accounts, $daily, $from),
            'top' => Post::with('account')->whereIn('account_id', $ids)->where('published_at', '>=', $from)->get()
                ->sortByDesc(fn ($p) => [$p->engagement(), (int) $p->views])->take(8)->values(),
            'best' => self::bestTimes($ids),
            'upcoming' => Plan::with('targets.account')->whereIn('status', ['scheduled', 'publishing'])->orderBy('scheduled_at')->limit(6)->get(),
            'drafts' => Plan::where('status', 'draft')->count(),
            'reviews' => self::reviews($accounts),
            'web' => self::website($from),
        ];
    }

    /** Followers per platform per day (the last known number is carried forward). */
    /** Periods for the followers chart (own choice, apart from the period of the figures). */
    public const FOLLOWER_RANGES = ['1m' => 30, '3m' => 91, '6m' => 182, '1y' => 365, 'all' => null];

    /**
     * Followers per platform over a chosen period. Long periods are shown per week (the last value of each week),
     * so the line stays readable. "all" starts at the first day we have followers for.
     *
     * @return array<string, array<string, int|null>>
     */
    public static function followers(string $range): array
    {
        $accounts = Account::where('status', '!=', 'off')->get();
        $ids = $accounts->pluck('id');
        $first = Daily::whereIn('account_id', $ids)->whereNotNull('followers')->min('date');
        $days = self::FOLLOWER_RANGES[$range] ?? 365;
        $from = $days ? today()->subDays($days - 1) : ($first ? Carbon::parse($first)->startOfDay() : today()->subDays(29));
        $total = (int) $from->diffInDays(today()) + 1;
        $step = $total > 120 ? 7 : 1;
        // Last known followers per account and day (also the last value before the period starts).
        $rows = Daily::whereIn('account_id', $ids)->whereNotNull('followers')->orderBy('date')->get(['account_id', 'date', 'followers'])->groupBy('account_id');
        $out = [];
        foreach ($accounts->groupBy('platform') as $platform => $list) {
            $byDay = [];
            foreach ($list as $a) {
                foreach ($rows[$a->id] ?? [] as $r) {
                    $byDay[$a->id][$r->date->toDateString()] = (int) $r->followers;
                }
            }
            $last = [];
            foreach ($list as $a) {
                $before = array_filter($byDay[$a->id] ?? [], fn ($k) => $k < $from->toDateString(), ARRAY_FILTER_USE_KEY);
                if ($before) {
                    $last[$a->id] = end($before);
                }
            }
            $points = [];
            for ($i = 0; $i < $total; $i++) {
                $d = $from->copy()->addDays($i)->toDateString();
                foreach ($list as $a) {
                    if (isset($byDay[$a->id][$d])) {
                        $last[$a->id] = $byDay[$a->id][$d];
                    }
                }
                if ($i % $step === $step - 1 || $i === $total - 1) {
                    $points[$d] = $last ? array_sum($last) : null;
                }
            }
            if (array_filter($points, fn ($v) => $v !== null)) {
                $out[$platform] = $points;
            }
        }

        return $out;
    }

    private static function followerSeries(Collection $accounts, Collection $daily, Carbon $from, int $days): array
    {
        $out = [];
        foreach ($accounts->groupBy('platform') as $platform => $list) {
            $points = [];
            $last = [];
            foreach (range(0, $days - 1) as $i) {
                $d = $from->copy()->addDays($i)->toDateString();
                foreach ($list as $a) {
                    $row = ($daily[$a->id] ?? collect())->first(fn ($r) => $r->date->toDateString() === $d && $r->followers !== null);
                    if ($row) {
                        $last[$a->id] = $row->followers;
                    } elseif (! isset($last[$a->id])) {
                        $before = ($daily[$a->id] ?? collect())->filter(fn ($r) => $r->date->toDateString() < $d && $r->followers !== null)->last();
                        if ($before) {
                            $last[$a->id] = $before->followers;
                        }
                    }
                }
                $points[$d] = $last ? array_sum($last) : null;
            }
            if (array_filter($points, fn ($v) => $v !== null)) {
                $out[$platform] = $points;
            }
        }

        return $out;
    }

    /** Views and engagements per week and platform. */
    private static function weekly(Collection $accounts, Collection $daily, Carbon $from): array
    {
        $weeks = [];
        $platformOf = $accounts->pluck('platform', 'id');
        foreach ($daily as $accId => $rows) {
            foreach ($rows as $r) {
                if ($r->date->lt($from)) {
                    continue;
                }
                $w = $r->date->copy()->startOfWeek()->toDateString();
                $p = $platformOf[$accId] ?? 'other';
                $weeks[$w][$p]['views'] = ($weeks[$w][$p]['views'] ?? 0) + (int) $r->views;
                $weeks[$w][$p]['engagements'] = ($weeks[$w][$p]['engagements'] ?? 0) + (int) $r->engagements;
            }
        }
        ksort($weeks);

        return $weeks;
    }

    /** Average engagement per post by weekday and time of day (last 6 months). */
    private static function bestTimes(Collection $ids): array
    {
        $grid = [];
        $posts = Post::whereIn('account_id', $ids)->where('published_at', '>=', now()->subMonths(6))->get();
        foreach ($posts as $p) {
            $t = $p->published_at->copy()->timezone('Europe/Brussels');
            $grid[$t->dayOfWeekIso][intdiv($t->hour, 3)][] = $p->engagement();
        }
        $avg = [];
        foreach ($grid as $d => $blocks) {
            foreach ($blocks as $b => $list) {
                $avg[$d][$b] = ['avg' => array_sum($list) / count($list), 'n' => count($list)];
            }
        }
        $max = collect($avg)->flatten(1)->max('avg') ?: 0;

        return ['grid' => $avg, 'max' => $max, 'posts' => $posts->count()];
    }

    private static function reviews(Collection $accounts): array
    {
        $g = $accounts->where('platform', 'google');
        if ($g->isEmpty()) {
            return [];
        }

        return [
            'profiles' => $g->map(fn ($a) => ['name' => $a->name, 'rating' => $a->meta['rating'] ?? null, 'count' => $a->meta['reviews'] ?? null])->values()->all(),
            'latest' => Review::with('account')->whereIn('account_id', $g->pluck('id'))->orderByDesc('reviewed_at')->limit(5)->get(),
        ];
    }

    /** Which social platform a website visitor came from (utm_source first, else the referring site), or null. */
    public static function platformOf(?string $utm, ?string $referrer): ?string
    {
        $src = strtolower(trim((string) $utm));
        if ($src === '' && $referrer) {
            $src = strtolower((string) parse_url(str_contains($referrer, '://') ? $referrer : 'https://'.$referrer, PHP_URL_HOST));
        }
        foreach (self::PATTERNS as $platform => $re) {
            if ($src !== '' && preg_match($re, $src)) {
                return $platform;
            }
        }

        return null;
    }

    private const PATTERNS = [
        'facebook' => '/(^|\.)(facebook\.com|fb\.com|fb\.me)$|^(facebook|fb)$/',
        'instagram' => '/(^|\.)instagram\.com$|^(instagram|ig)$/',
        'linkedin' => '/(^|\.)(linkedin\.com|lnkd\.in)$|^linkedin$/',
        'youtube' => '/(^|\.)(youtube\.com|youtu\.be)$|^youtube$/',
        'tiktok' => '/(^|\.)tiktok\.com$|^tiktok$/',
        'google' => '/^(google|gbp|google_business)$/',
    ];

    /** Website visitors and requests that came from social media (from the website statistics). */
    private static function website(Carbon $from): array
    {
        $per = [];
        $ids = [];
        foreach (DB::table('web_visitors')->where('first_seen_at', '>=', $from)->get(['id', 'referrer', 'utm_source']) as $v) {
            if ($p = self::platformOf($v->utm_source, $v->referrer)) {
                $per[$p]['visitors'] = ($per[$p]['visitors'] ?? 0) + 1;
                $ids[$v->id] = $p;
            }
        }
        if ($ids) {
            foreach (DB::table('web_events')->whereIn('visitor_id', array_keys($ids))->whereIn('type', ['request', 'order'])->where('created_at', '>=', $from)->get(['visitor_id']) as $e) {
                $per[$ids[$e->visitor_id]]['requests'] = ($per[$ids[$e->visitor_id]]['requests'] ?? 0) + 1;
            }
        }

        return ['per' => $per, 'visitors' => array_sum(array_column($per, 'visitors')), 'requests' => array_sum(array_column($per, 'requests'))];
    }
}
