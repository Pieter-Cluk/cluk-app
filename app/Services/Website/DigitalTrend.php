<?php

namespace App\Services\Website;

use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\Social\Post;
use App\Services\Social\Dashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dashboard: the last days per channel (website visitors, social media interactions per day: the platforms give no
 * hourly figures) and every post on a day × hour grid (the exact time it went out, sized by its interactions).
 */
class DigitalTrend
{
    public const SHAPES = ['facebook' => 'circle', 'instagram' => 'square', 'linkedin' => 'diamond'];

    public static function get(int $days = 30): array
    {
        $from = today()->subDays($days - 1);
        $dates = collect(range(0, $days - 1))->map(fn ($i) => $from->copy()->addDays($i));
        $key = fn ($d) => Carbon::parse($d)->toDateString();

        // Website: visitors per day (the team's own visits left out).
        $web = DB::table('web_events')->where('created_at', '>=', $from)
            ->whereNotIn('visitor_id', DB::table('web_visitors')->where('internal', true)->select('id'))
            ->get(['visitor_id', 'created_at'])
            ->groupBy(fn ($e) => $key($e->created_at))->map(fn ($g) => $g->pluck('visitor_id')->unique()->count());
        $rows = [['key' => 'website', 'measure' => 'visitors', 'color' => null, 'values' => $dates->map(fn ($d) => (int) ($web[$d->toDateString()] ?? 0))->all()]];

        // Social media: interactions (engagements + clicks) per day, per platform.
        $accounts = Account::where('status', '!=', 'off')->get(['id', 'platform']);
        $daily = Daily::whereIn('account_id', $accounts->pluck('id'))->whereDate('date', '>=', $from->toDateString())->get();
        foreach ($accounts->groupBy('platform') as $platform => $list) {
            $per = $daily->whereIn('account_id', $list->pluck('id'))->groupBy(fn ($r) => $key($r->date))
                ->map(fn ($g) => (int) $g->sum(fn ($r) => (int) $r->engagements + (int) $r->clicks));
            $rows[] = ['key' => $platform, 'measure' => 'interactions', 'color' => Dashboard::COLORS[$platform] ?? '#888', 'values' => $dates->map(fn ($d) => (int) ($per[$d->toDateString()] ?? 0))->all()];
        }
        $rows = collect($rows)->map(fn ($r) => $r + ['total' => array_sum($r['values']), 'max' => max(1, max($r['values']))])->all();

        // Posts: when they went out (Brussels time) and how much they did.
        $platforms = $accounts->pluck('platform', 'id');
        $posts = Post::whereIn('account_id', $accounts->pluck('id'))->where('published_at', '>=', $from)->orderBy('published_at')->get()
            ->map(function ($p) use ($platforms, $from) {
                $at = $p->published_at->copy()->timezone(config('app.timezone'));
                $platform = $platforms[$p->account_id];

                return [
                    'platform' => $platform, 'color' => Dashboard::COLORS[$platform] ?? '#888', 'shape' => self::SHAPES[$platform] ?? 'circle',
                    'day' => (int) $from->diffInDays($at->copy()->startOfDay()), 'hour' => $at->hour + $at->minute / 60, 'at' => $at,
                    'interactions' => (int) $p->likes + (int) $p->comments + (int) $p->shares + (int) $p->saves + (int) $p->clicks,
                    'text' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $p->text)), 70), 'url' => $p->permalink,
                ];
            })->values();

        return ['dates' => $dates, 'rows' => $rows, 'posts' => $posts, 'maxPost' => max(1, (int) $posts->max('interactions')), 'connected' => $accounts->isNotEmpty()];
    }
}
