<?php

namespace App\Services\Social\Providers;

use App\Models\Social\Account;
use App\Models\Social\PlanTarget;
use App\Services\Social\Media;
use App\Services\Social\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Facebook pages and the Instagram professional accounts linked to them, via
 * the Meta Graph API ("Facebook Login for Business"). Page tokens from a
 * long-lived user token do not expire.
 */
class Meta extends Provider
{
    public function key(): string
    {
        return 'meta';
    }

    public function configured(): bool
    {
        return filled(config('social.meta.app_id')) && filled(config('social.meta.app_secret'));
    }

    private function g(string $path = ''): string
    {
        return 'https://graph.facebook.com/'.config('social.meta.version').'/'.ltrim($path, '/');
    }

    public function authUrl(string $state): string
    {
        // With a Login for Business configuration the permissions come from that configuration; otherwise from the scopes.
        $ask = filled(config('social.meta.config_id'))
            ? ['config_id' => config('social.meta.config_id'), 'override_default_response_type' => 'true']
            : ['scope' => implode(',', config('social.meta.scopes'))];

        return 'https://www.facebook.com/'.config('social.meta.version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('social.meta.app_id'), 'redirect_uri' => $this->redirectUri(), 'state' => $state, 'response_type' => 'code',
        ] + $ask);
    }

    public function connect(string $code): array
    {
        $short = $this->ok(Http::timeout(20)->get($this->g('oauth/access_token'), [
            'client_id' => config('social.meta.app_id'), 'client_secret' => config('social.meta.app_secret'), 'redirect_uri' => $this->redirectUri(), 'code' => $code,
        ]), 'Meta sign-in')['access_token'] ?? null;
        $long = $this->ok(Http::timeout(20)->get($this->g('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token', 'client_id' => config('social.meta.app_id'), 'client_secret' => config('social.meta.app_secret'), 'fb_exchange_token' => $short,
        ]), 'Meta long-lived token')['access_token'] ?? $short;
        $pages = $this->ok(Http::timeout(20)->get($this->g('me/accounts'), [
            'fields' => 'id,name,access_token,link,picture{url},instagram_business_account{id,username,name,profile_picture_url}', 'limit' => 100, 'access_token' => $long,
        ]), 'Meta pages')['data'] ?? [];
        $out = [];
        foreach ($pages as $p) {
            $out[] = ['platform' => 'facebook', 'external_id' => (string) $p['id'], 'name' => $p['name'], 'url' => $p['link'] ?? 'https://www.facebook.com/'.$p['id'],
                'avatar_url' => $p['picture']['data']['url'] ?? null, 'token' => $p['access_token'], 'token_expires_at' => null, 'meta' => []];
            if ($ig = $p['instagram_business_account'] ?? null) {
                $out[] = ['platform' => 'instagram', 'external_id' => (string) $ig['id'], 'name' => $ig['name'] ?? $ig['username'], 'handle' => '@'.$ig['username'],
                    'url' => 'https://www.instagram.com/'.$ig['username'], 'avatar_url' => $ig['profile_picture_url'] ?? null, 'token' => $p['access_token'],
                    'token_expires_at' => null, 'meta' => ['page_id' => (string) $p['id']]];
            }
        }

        return $out;
    }

    // ── Figures ─────────────────────────────────────────────────────────────

    public function syncDaily(Account $a, int $days): void
    {
        $a->platform === 'instagram' ? $this->igDaily($a, $days) : $this->pageDaily($a, $days);
    }

    private function pageDaily(Account $a, int $days): void
    {
        $info = $this->ok(Http::timeout(20)->get($this->g($a->external_id), ['fields' => 'followers_count,fan_count', 'access_token' => $a->token]), 'Facebook page');
        $this->day($a, today()->toDateString(), ['followers' => $info['followers_count'] ?? $info['fan_count'] ?? null]);
        $since = today()->subDays($days)->timestamp;
        $until = today()->timestamp;
        $got = [];
        foreach (config('social.meta.page_metrics') as $field => $metric) {
            $field = str_replace('_old', '', $field);
            if (isset($got[$field])) {
                continue; // the new metric worked: no need for the old one
            }
            try {
                $data = $this->ok(Http::timeout(20)->get($this->g($a->external_id.'/insights'), ['metric' => $metric, 'period' => 'day', 'since' => $since, 'until' => $until, 'access_token' => $a->token]), $metric)['data'][0]['values'] ?? [];
            } catch (Throwable) {
                continue; // a metric Meta retired or does not give for this page
            }
            foreach ($data as $v) {
                $this->day($a, Carbon::parse($v['end_time'])->subDay()->toDateString(), [$field => is_numeric($v['value'] ?? null) ? $v['value'] : null]);
            }
            $got[$field] = true;
        }
    }

    private function igDaily(Account $a, int $days): void
    {
        $info = $this->ok(Http::timeout(20)->get($this->g($a->external_id), ['fields' => 'followers_count,media_count', 'access_token' => $a->token]), 'Instagram account');
        $this->day($a, today()->toDateString(), ['followers' => $info['followers_count'] ?? null]);
        foreach ($this->days($days) as $d) {
            $values = [];
            foreach (config('social.meta.ig_metrics') as $field => $metric) {
                try {
                    $res = $this->ok(Http::timeout(20)->get($this->g($a->external_id.'/insights'), [
                        'metric' => $metric, 'period' => 'day', 'metric_type' => 'total_value', 'since' => $d->timestamp, 'until' => $d->copy()->addDay()->timestamp, 'access_token' => $a->token,
                    ]), $metric);
                    $values[$field] = $res['data'][0]['total_value']['value'] ?? null;
                } catch (Throwable) {
                }
            }
            $this->day($a, $d->toDateString(), $values);
        }
    }

    public function syncPosts(Account $a): void
    {
        if ($a->platform === 'instagram') {
            $media = $this->ok(Http::timeout(20)->get($this->g($a->external_id.'/media'), [
                'fields' => 'id,caption,media_type,media_product_type,permalink,thumbnail_url,media_url,timestamp,like_count,comments_count', 'limit' => 25, 'access_token' => $a->token,
            ]), 'Instagram media')['data'] ?? [];
            foreach ($media as $i => $m) {
                $values = ['published_at' => self::ts($m['timestamp'] ?? null), 'kind' => strtolower($m['media_product_type'] === 'REELS' ? 'reel' : ($m['media_type'] ?? 'post')),
                    'text' => $m['caption'] ?? null, 'permalink' => $m['permalink'] ?? null, 'thumb_url' => $m['thumbnail_url'] ?? $m['media_url'] ?? null,
                    'likes' => $m['like_count'] ?? null, 'comments' => $m['comments_count'] ?? null];
                if ($i < 12) { // reach and views for the latest ones
                    try {
                        foreach ($this->ok(Http::timeout(20)->get($this->g($m['id'].'/insights'), ['metric' => 'reach,views,saved,shares', 'access_token' => $a->token]), 'media insights')['data'] ?? [] as $ins) {
                            $key = ['reach' => 'reach', 'views' => 'views', 'saved' => 'saves', 'shares' => 'shares'][$ins['name']] ?? null;
                            if ($key) {
                                $values[$key] = $ins['values'][0]['value'] ?? null;
                            }
                        }
                    } catch (Throwable) {
                    }
                }
                $this->post($a, (string) $m['id'], $values);
            }

            return;
        }
        $posts = $this->ok(Http::timeout(20)->get($this->g($a->external_id.'/posts'), [
            'fields' => 'id,message,created_time,permalink_url,full_picture,shares,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)', 'limit' => 25, 'access_token' => $a->token,
        ]), 'Facebook posts')['data'] ?? [];
        foreach ($posts as $p) {
            $this->post($a, (string) $p['id'], ['published_at' => self::ts($p['created_time'] ?? null), 'kind' => 'post', 'text' => $p['message'] ?? null,
                'permalink' => $p['permalink_url'] ?? null, 'thumb_url' => $p['full_picture'] ?? null, 'likes' => $p['reactions']['summary']['total_count'] ?? null,
                'comments' => $p['comments']['summary']['total_count'] ?? null, 'shares' => $p['shares']['count'] ?? 0]);
        }
    }

    // ── Publishing ──────────────────────────────────────────────────────────

    public function publish(PlanTarget $t): string
    {
        return $t->account->platform === 'instagram' ? $this->publishIg($t) : $this->publishPage($t);
    }

    private function publishPage(PlanTarget $t): string
    {
        $a = $t->account;
        $plan = $t->plan;
        $text = $t->text();
        $link = $plan->trackedLink('facebook');
        if ($video = $plan->video()) {
            $r = $this->ok(Http::timeout(120)->asForm()->post($this->g($a->external_id.'/videos'), ['file_url' => Media::url($plan, $video), 'description' => trim($text."\n\n".$link), 'access_token' => $a->token]), 'Facebook video');
            $id = (string) $r['id'];
        } elseif ($images = $plan->images()) {
            if (count($images) === 1) {
                $r = $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/photos'), ['url' => Media::url($plan, $images[0]), 'caption' => trim($text.($link ? "\n\n".$link : '')), 'access_token' => $a->token]), 'Facebook photo');
                $id = (string) ($r['post_id'] ?? $r['id']);
            } else {
                $ids = [];
                foreach ($images as $img) {
                    $ids[] = $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/photos'), ['url' => Media::url($plan, $img), 'published' => 'false', 'access_token' => $a->token]), 'Facebook photo')['id'];
                }
                $form = ['message' => trim($text.($link ? "\n\n".$link : '')), 'access_token' => $a->token];
                foreach ($ids as $i => $pid) {
                    $form['attached_media['.$i.']'] = json_encode(['media_fbid' => $pid]);
                }
                $id = (string) $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/feed'), $form), 'Facebook post')['id'];
            }
        } else {
            $id = (string) $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/feed'), array_filter(['message' => $text, 'link' => $link, 'access_token' => $a->token])), 'Facebook post')['id'];
        }
        $perma = null;
        try {
            $perma = $this->ok(Http::timeout(20)->get($this->g($id), ['fields' => 'permalink_url', 'access_token' => $a->token]), 'permalink')['permalink_url'] ?? null;
        } catch (Throwable) {
        }
        $t->update(['external_id' => $id, 'permalink' => $perma, 'status' => 'published', 'published_at' => now(), 'error' => null]);

        return 'published';
    }

    private function publishIg(PlanTarget $t): string
    {
        $a = $t->account;
        $plan = $t->plan;
        $caption = $t->text();
        if (! $t->container_id) {
            if ($video = $plan->video()) {
                $container = $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/media'), ['media_type' => 'REELS', 'video_url' => Media::url($plan, $video), 'caption' => $caption, 'access_token' => $a->token]), 'Instagram reel')['id'];
            } elseif (count($images = $plan->images()) > 1) {
                $children = [];
                foreach (array_slice($images, 0, 10) as $img) {
                    $children[] = $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/media'), ['image_url' => Media::url($plan, $img), 'is_carousel_item' => 'true', 'access_token' => $a->token]), 'Instagram photo')['id'];
                }
                $container = $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/media'), ['media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $caption, 'access_token' => $a->token]), 'Instagram carousel')['id'];
            } elseif ($images) {
                $container = $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/media'), ['image_url' => Media::url($plan, $images[0]), 'caption' => $caption, 'access_token' => $a->token]), 'Instagram photo')['id'];
            } else {
                throw new RuntimeException(__('social.errors.ig_media'));
            }
            $t->update(['container_id' => (string) $container]);
        }
        // Videos are processed by Instagram first; photos are ready at once.
        $status = $this->ok(Http::timeout(20)->get($this->g($t->container_id), ['fields' => 'status_code', 'access_token' => $a->token]), 'Instagram status')['status_code'] ?? 'FINISHED';
        if ($status === 'IN_PROGRESS') {
            $t->update(['status' => 'processing']);

            return 'processing';
        }
        if ($status === 'ERROR' || $status === 'EXPIRED') {
            $t->update(['container_id' => null]); // a next try starts with a fresh upload
            throw new RuntimeException(__('social.errors.ig_processing', ['status' => $status]));
        }
        $id = (string) $this->ok(Http::timeout(60)->asForm()->post($this->g($a->external_id.'/media_publish'), ['creation_id' => $t->container_id, 'access_token' => $a->token]), 'Instagram publish')['id'];
        $perma = null;
        try {
            $perma = $this->ok(Http::timeout(20)->get($this->g($id), ['fields' => 'permalink', 'access_token' => $a->token]), 'permalink')['permalink'] ?? null;
        } catch (Throwable) {
        }
        $t->update(['external_id' => $id, 'permalink' => $perma, 'status' => 'published', 'published_at' => now(), 'error' => null]);

        return 'published';
    }
}
