<?php

namespace App\Services\Website;

use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\Social\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Today's digital activity for the dashboard: what happened on the website (per hour) and on the connected
 * social media accounts (per day: the platforms give no hourly figures). Website figures only count visitors
 * who accepted statistics cookies.
 */
class DigitalToday
{
    /** Website event types that are an action (not just looking at a page). */
    public const ACTIONS = ['search', 'product', 'add', 'request', 'order', 'register', 'download', 'chat'];

    public static function get(?Carbon $day = null): array
    {
        $day = ($day ?? today())->copy()->startOfDay();
        $events = DB::table('web_events')->whereBetween('created_at', [$day, $day->copy()->endOfDay()])->get(['visitor_id', 'type', 'created_at']);

        $hours = array_fill(0, 24, 0);
        foreach ($events as $e) {
            $hours[(int) Carbon::parse($e->created_at)->format('G')]++;
        }
        $actions = $events->whereIn('type', self::ACTIONS);

        $accounts = Account::where('status', '!=', 'off')->orderBy('platform')->get(['id', 'platform', 'name', 'last_synced_at']);
        $daily = Daily::whereIn('account_id', $accounts->pluck('id'))->whereDate('date', $day->toDateString())->get()->keyBy('account_id');
        $posts = Post::whereIn('account_id', $accounts->pluck('id'))->whereBetween('published_at', [$day, $day->copy()->endOfDay()])->count();

        $social = $accounts->groupBy('platform')->map(function ($list, $platform) use ($daily) {
            $rows = $list->map(fn ($a) => $daily->get($a->id))->filter();

            return [
                'platform' => $platform,
                'interactions' => (int) $rows->sum(fn ($r) => (int) $r->engagements + (int) $r->clicks),
                'reach' => (int) $rows->sum(fn ($r) => (int) ($r->reach ?: $r->views)),
                'has' => $rows->isNotEmpty(),
            ];
        })->values();

        $web = $events->count();
        $socialN = (int) $social->sum('interactions');
        $sources = collect([['key' => 'website', 'n' => $web]])
            ->merge($social->map(fn ($s) => ['key' => $s['platform'], 'n' => $s['interactions']]))
            ->sortByDesc('n')->values();

        return [
            'day' => $day,
            'total' => $web + $socialN,
            'hours' => $hours,
            'now' => $day->isToday() ? (int) now()->format('G') : 23,
            'web' => [
                'events' => $web,
                'visitors' => $events->pluck('visitor_id')->unique()->count(),
                'views' => $events->where('type', 'view')->count(),
                'actions' => $actions->count(),
                'by_type' => $actions->countBy('type')->sortDesc()->all(),
            ],
            'social' => [
                'connected' => $accounts->isNotEmpty(),
                'interactions' => $socialN,
                'reach' => (int) $social->sum('reach'),
                'posts' => $posts,
                'synced' => $accounts->max('last_synced_at'),
            ],
            'sources' => $sources,
        ];
    }
}
