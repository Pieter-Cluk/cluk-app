<?php

namespace App\Services\Social;

use App\Models\Social\Account;
use App\Models\Social\Post;
use App\Services\Website\SocialFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Own copies of post pictures and videos (Meta's links expire after some days).
 * Kept on the local disk under social-posts/; the website reads thumb_path and video_path.
 */
class PostMedia
{
    public const VIDEO_MAX = 60 * 1024 * 1024;

    public const IMAGE_MAX = 15 * 1024 * 1024;

    /** The newest posts of an account that miss a copy: a few pictures and one video per run. */
    public static function keepFor(Account $a, int $images = 12, int $videos = 1): void
    {
        if (! in_array($a->platform, ['facebook', 'instagram', 'linkedin'], true)) {
            return;
        }
        $posts = Post::where('account_id', $a->id)->latest('published_at')->limit(60)->get();
        // Posts tagged for a part of the website (#CLUKsales, #Afrit6…) keep their picture first, also older ones:
        // Meta's picture links stop working after a while, so these are saved while the link is fresh.
        $tagged = Post::where('account_id', $a->id)->whereNull('thumb_path')->whereNotNull('thumb_url')->latest('published_at')->limit(1000)->get()
            ->filter(fn ($p) => SocialFeed::sections($p->text) !== [])->take(40);
        foreach ($tagged->concat($posts->filter(fn ($p) => $p->thumb_url && ! $p->thumb_path))->unique('id')->take($images + $tagged->count()) as $p) {
            self::image($p);
        }
        foreach ($posts->filter(fn ($p) => $p->video_url && ! $p->video_path)->take($videos) as $p) {
            self::video($p);
        }
    }

    public static function image(Post $p): ?string
    {
        try {
            $res = Http::timeout(20)->get($p->thumb_url);
            $type = (string) $res->header('Content-Type');
            if (! $res->successful() || ! str_starts_with($type, 'image/') || strlen($res->body()) > self::IMAGE_MAX) {
                return null;
            }
            $ext = match (true) {
                str_contains($type, 'png') => 'png', str_contains($type, 'webp') => 'webp', default => 'jpg'
            };
            $path = 'social-posts/'.$p->id.'.'.$ext;
            Storage::disk('local')->put($path, $res->body());
            $p->forceFill(['thumb_path' => $path])->saveQuietly();

            return $path;
        } catch (Throwable $e) {
            Log::info('Social post picture not kept', ['post' => $p->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }

    /** Streams the video to disk and stops above 60 MB (then the website shows the picture only). */
    public static function video(Post $p): ?string
    {
        $disk = Storage::disk('local');
        $path = 'social-posts/'.$p->id.'.mp4';
        $disk->makeDirectory('social-posts');
        $tmp = $disk->path($path.'.part');
        try {
            $res = Http::timeout(120)->withOptions(['sink' => $tmp, 'progress' => function ($total, $done) {
                if ($total > self::VIDEO_MAX || $done > self::VIDEO_MAX) {
                    throw new \RuntimeException('video larger than 60 MB');
                }
            }])->get($p->video_url);
            if ((int) $res->header('Content-Length') > self::VIDEO_MAX || (is_file($tmp) && filesize($tmp) > self::VIDEO_MAX)) {
                throw new \RuntimeException('video larger than 60 MB');
            }
            if (! $res->successful() || ! is_file($tmp) || filesize($tmp) === 0) {
                @unlink($tmp);

                return null;
            }
            rename($tmp, $disk->path($path));
            $p->forceFill(['video_path' => $path])->saveQuietly();

            return $path;
        } catch (Throwable $e) {
            @unlink($tmp);
            // Too big or gone: remember not to try again for this link.
            if (str_contains($e->getMessage(), '60 MB')) {
                $p->forceFill(['video_url' => null])->saveQuietly();
            }
            Log::info('Social post video not kept', ['post' => $p->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }
}
