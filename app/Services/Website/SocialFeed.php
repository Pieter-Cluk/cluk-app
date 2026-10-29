<?php

namespace App\Services\Website;

use App\Models\Social\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Facebook, Instagram and LinkedIn posts (synced by the platform into social_posts) for the website.
 * A post that went out on several platforms shows once: the copy with a picture, Facebook before Instagram before LinkedIn.
 * A post goes to a part of the site through a hashtag in its text:
 *   #CLUKdryhire, #CLUKsales, #CLUKfactory, #CLUKafrit6 or #Afrit6 (also with hyphens, any case).
 * Posts without such a tag show on the homepage and the news page only.
 * Pictures and videos are served from the CLUK server, never straight from Meta.
 */
class SocialFeed
{
    public const SECTIONS = ['dryhire', 'sales', 'factory', 'afrit6'];

    public const PLATFORMS = ['facebook', 'instagram', 'linkedin'];

    // #CLUKdryhire, #CLUKsales, #CLUKfactory, #CLUKafrit6, and #Afrit6 on its own (used on older posts).
    private const TAG = '/#\s?(?:cluk[\s_-]*(dry[\s_-]*hire|sales|factory|afrit[\s_-]*6)|(afrit[\s_-]*6))\b/i';

    /** All website posts, newest first (cached a few minutes). */
    public static function all(): Collection
    {
        $stamp = (string) Post::max('updated_at').'|'.Post::count();

        // The cache keeps plain arrays only (no objects), so the dates are added back afterwards.
        $rows = Cache::remember('site.social.v5.'.md5($stamp), 300, function () {
            $hasHide = Schema::hasColumn('social_posts', 'on_website');

            return Post::with('account:id,platform,name,handle,url')
                ->whereHas('account', fn ($q) => $q->whereIn('platform', self::PLATFORMS)->where('status', '!=', 'off')) // disconnected accounts stay off
                ->whereNotNull('published_at')->where('published_at', '<=', now())
                ->when($hasHide, fn ($q) => $q->where('on_website', true))
                ->orderByDesc('published_at')->limit(300)->get()
                ->map(fn (Post $p) => self::card($p))
                ->filter(fn ($c) => $c['title'] !== '' || $c['has_media'])->values()
                ->pipe(fn ($cards) => self::once($cards))->all();
        });

        return collect($rows)->map(fn ($c) => ['date' => Carbon::createFromTimestamp($c['ts'], config('app.timezone'))] + $c);
    }

    /**
     * The same post on several platforms (from the post planner, or the same text within 3 days) shows once:
     * the copy with a picture first, then Facebook, Instagram, LinkedIn.
     */
    public static function once(Collection $cards): Collection
    {
        $rank = fn (array $c) => ($c['has_media'] ? 0 : 10) + (int) array_search($c['platform'], self::PLATFORMS, true);
        $kept = [];
        foreach ($cards->sortBy($rank)->values() as $c) {
            $twin = collect($kept)->search(fn ($k) => ($c['plan'] && $k['plan'] === $c['plan'])
                || (mb_strlen($c['sig']) >= 20 && $k['sig'] === $c['sig'] && abs($k['ts'] - $c['ts']) <= 3 * 86400));
            if ($twin === false) {
                $kept[] = $c;
            }
        }

        return collect($kept)->sortByDesc('ts')->values();
    }

    /** Posts for one part of the site, or every post when $section is null. */
    public static function for(?string $section = null, int $limit = 0): Collection
    {
        $all = self::all();
        $list = $section ? $all->filter(fn ($c) => in_array($section, $c['sections'], true))->values() : $all;

        return $limit ? $list->take($limit)->values() : $list;
    }

    /** Four posts for the homepage: one from each part where possible, then the newest. */
    public static function mix(int $n = 4): Collection
    {
        $all = self::all();
        $pick = collect();
        foreach (self::SECTIONS as $s) {
            if ($c = $all->first(fn ($c) => in_array($s, $c['sections'], true) && ! $pick->contains('id', $c['id']))) {
                $pick->push($c);
            }
        }
        foreach ($all as $c) {
            if ($pick->count() >= $n) {
                break;
            }
            if (! $pick->contains('id', $c['id'])) {
                $pick->push($c);
            }
        }

        return $pick->take($n)->sortByDesc('ts')->values();
    }

    public static function counts(): array
    {
        $all = self::all();

        return ['all' => $all->count()] + collect(self::SECTIONS)->mapWithKeys(fn ($s) => [$s => $all->filter(fn ($c) => in_array($s, $c['sections'], true))->count()])->all();
    }

    /** The parts of the site a text belongs to, from its hashtags. */
    /** Topic label from a hashtag in the post: #promo (#actie), #new (#nieuw), #event, #tip. Null: no label. */
    public const TOPICS = ['promo' => ['promo', 'actie', 'promotie'], 'new' => ['new', 'nieuw', 'nouveau', 'nuevo'], 'event' => ['event', 'evenement', 'evento'], 'tip' => ['tip', 'astuce', 'consejo']];

    public static function topic(?string $text): ?string
    {
        preg_match_all('/#([\p{L}\p{N}_-]+)/u', (string) $text, $m);
        $tags = array_map(fn ($t) => mb_strtolower($t), $m[1]);
        foreach (self::TOPICS as $topic => $words) {
            if (array_intersect($tags, $words)) {
                return $topic;
            }
        }

        return null;
    }

    public static function sections(?string $text): array
    {
        preg_match_all(self::TAG, (string) $text, $m);

        return array_values(array_unique(array_map(fn ($t) => match (true) {
            str_starts_with(strtolower(preg_replace('/[\s_-]/', '', $t)), 'dry') => 'dryhire',
            str_starts_with(strtolower($t), 'afrit') => 'afrit6',
            default => strtolower($t),
        }, array_filter(array_merge($m[1] ?? [], $m[2] ?? [])))));
    }

    private static function card(Post $p): array
    {
        $text = (string) $p->text;
        $clean = trim(preg_replace(['/#[\p{L}\p{N}_-]+/u', '/[ \t]+/', '/\n{3,}/'], ['', ' ', "\n\n"], $text));
        $first = trim((string) Str::of($clean)->explode("\n")->first(fn ($l) => trim($l) !== ''));
        $title = Str::limit($first, 90);
        $video = self::videoSource($p);

        return [
            'id' => $p->id,
            'ts' => $p->published_at->timestamp,
            'platform' => $p->account?->platform,
            'account' => $p->account?->name,
            'url' => $p->permalink ?: $p->account?->url,
            'title' => $title,
            'text' => Str::limit($clean, 400),
            'sections' => self::sections($text),
            'topic' => self::topic($text),
            'kind' => $p->kind,
            'has_media' => (bool) ($p->thumb_url || self::column($p, 'thumb_path')),
            'image' => $p->thumb_url || self::column($p, 'thumb_path') ? route('site.social.image', $p->id).'?v='.substr(md5((string) ($p->thumb_url ?: self::column($p, 'thumb_path'))), 0, 8) : null,
            'video' => $video ? route('site.social.video', $p->id) : null,
            'plan' => $p->plan_id,
            // Same words, any platform: lower case letters and digits of the text without hashtags, links and emoji.
            'sig' => mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(preg_replace('#https?://\S+#', '', $clean))), 0, 80),
        ];
    }

    private static function column(Post $p, string $col): ?string
    {
        return array_key_exists($col, $p->getAttributes()) ? ($p->getAttributes()[$col] ?: null) : null;
    }

    /** A video the platform keeps (video_path on its disk) or a link to one (video_url). */
    private static function videoSource(Post $p): ?array
    {
        if ($path = self::column($p, 'video_path')) {
            return ['path' => $path];
        }
        if ($url = self::column($p, 'video_url')) {
            return ['url' => $url];
        }

        return null;
    }

    /** Local copy of the post picture (fetched once from Meta and kept). */
    public static function imageFile(Post $p): ?string
    {
        $disk = Storage::disk('local');
        if (($path = self::column($p, 'thumb_path')) && $disk->exists($path)) {
            return $disk->path($path);
        }
        if (! $p->thumb_url) {
            return null;
        }
        $rel = 'site-social/'.$p->id.'-'.substr(md5($p->thumb_url), 0, 10).'.img';
        if (! $disk->exists($rel)) {
            try {
                $res = Http::timeout(15)->get($p->thumb_url);
                if (! $res->successful() || ! str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                    return null;
                }
                $disk->put($rel, $res->body());
            } catch (Throwable) {
                return null;
            }
        }
        $web = Photos::web($rel, 'social-'.$p->id, 1200);

        return $web ?? $disk->path($rel);
    }

    /** Local copy of the post video (at most 60 MB), or null. */
    public static function videoFile(Post $p): ?string
    {
        $src = self::videoSource($p);
        $disk = Storage::disk('local');
        if (! $src) {
            return null;
        }
        if (isset($src['path'])) {
            return $disk->exists($src['path']) ? $disk->path($src['path']) : null;
        }
        $rel = 'site-social/'.$p->id.'-'.substr(md5($src['url']), 0, 10).'.mp4';
        if (! $disk->exists($rel)) {
            try {
                $res = Http::timeout(60)->withOptions(['stream' => true])->get($src['url']);
                $len = (int) $res->header('Content-Length');
                if (! $res->successful() || ($len && $len > 60 * 1024 * 1024)) {
                    return null;
                }
                $disk->put($rel, $res->body());
            } catch (Throwable) {
                return null;
            }
        }

        return $disk->path($rel);
    }
}
