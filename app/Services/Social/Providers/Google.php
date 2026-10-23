<?php

namespace App\Services\Social\Providers;

use App\Models\Social\Account;
use App\Models\Social\Review;
use App\Services\Social\Provider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Business Profiles (views in Maps and Search, calls, routes, website
 * clicks, reviews) and YouTube channels (subscribers, views, videos), with
 * one Google sign-in. Read-only for now.
 */
class Google extends Provider
{
    private const STARS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

    public function key(): string
    {
        return 'google';
    }

    public function configured(): bool
    {
        return filled(config('social.google.client_id')) && filled(config('social.google.client_secret'));
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('social.google.client_id'), 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code',
            'scope' => implode(' ', config('social.google.scopes')), 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function tokens(array $form): array
    {
        $t = $this->ok(Http::timeout(20)->asForm()->post('https://oauth2.googleapis.com/token', $form + [
            'client_id' => config('social.google.client_id'), 'client_secret' => config('social.google.client_secret'),
        ]), 'Google sign-in');

        return array_filter(['token' => $t['access_token'], 'refresh_token' => $t['refresh_token'] ?? null, 'token_expires_at' => now()->addSeconds((int) ($t['expires_in'] ?? 3600))]);
    }

    public function connect(string $code): array
    {
        $tok = $this->tokens(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri()]);
        $out = [];
        // Business Profiles (only once Google has approved the API access; until then this part is skipped).
        try {
            foreach ($this->ok(Http::timeout(20)->withToken($tok['token'])->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts'), 'Google accounts')['accounts'] ?? [] as $acc) {
                $locs = $this->ok(Http::timeout(20)->withToken($tok['token'])->get('https://mybusinessbusinessinformation.googleapis.com/v1/'.$acc['name'].'/locations', [
                    'readMask' => 'name,title,metadata', 'pageSize' => 100,
                ]), 'Google locations')['locations'] ?? [];
                foreach ($locs as $l) {
                    $out[] = $tok + ['platform' => 'google', 'external_id' => $l['name'], 'name' => $l['title'] ?? $l['name'], 'url' => $l['metadata']['mapsUri'] ?? null, 'meta' => ['account' => $acc['name']]];
                }
            }
        } catch (Throwable $e) {
            session()->flash('social_note', __('social.connect.google_gbp_pending'));
        }
        // YouTube channels of this Google account.
        try {
            foreach ($this->ok(Http::timeout(20)->withToken($tok['token'])->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet,contentDetails', 'mine' => 'true']), 'YouTube')['items'] ?? [] as $c) {
                $out[] = $tok + ['platform' => 'youtube', 'external_id' => $c['id'], 'name' => $c['snippet']['title'], 'handle' => $c['snippet']['customUrl'] ?? null,
                    'url' => 'https://www.youtube.com/'.($c['snippet']['customUrl'] ?? 'channel/'.$c['id']), 'avatar_url' => $c['snippet']['thumbnails']['default']['url'] ?? null,
                    'meta' => ['uploads' => $c['contentDetails']['relatedPlaylists']['uploads'] ?? null]];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    public function refresh(Account $a): void
    {
        if ($a->refresh_token && (! $a->token_expires_at || $a->token_expires_at->lt(now()->addMinutes(5)))) {
            $a->update($this->tokens(['grant_type' => 'refresh_token', 'refresh_token' => $a->refresh_token]));
        }
    }

    public function syncDaily(Account $a, int $days): void
    {
        $a->platform === 'youtube' ? $this->ytDaily($a, $days) : $this->gbpDaily($a, $days);
    }

    private function gbpDaily(Account $a, int $days): void
    {
        $from = today()->subDays($days);
        $to = today()->subDay();
        $metrics = ['BUSINESS_IMPRESSIONS_DESKTOP_MAPS', 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH', 'BUSINESS_IMPRESSIONS_MOBILE_MAPS', 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
            'CALL_CLICKS', 'WEBSITE_CLICKS', 'BUSINESS_DIRECTION_REQUESTS'];
        $q = implode('&', array_map(fn ($m) => 'dailyMetrics='.$m, $metrics))
            .'&dailyRange.start_date.year='.$from->year.'&dailyRange.start_date.month='.$from->month.'&dailyRange.start_date.day='.$from->day
            .'&dailyRange.end_date.year='.$to->year.'&dailyRange.end_date.month='.$to->month.'&dailyRange.end_date.day='.$to->day;
        $res = $this->ok(Http::timeout(30)->withToken($a->token)->get('https://businessprofileperformance.googleapis.com/v1/'.$a->external_id.':fetchMultiDailyMetricsTimeSeries?'.$q), 'Google Business Profile');
        $per = [];
        foreach ($res['multiDailyMetricTimeSeries'] ?? [] as $group) {
            foreach ($group['dailyMetricTimeSeries'] ?? [] as $series) {
                foreach ($series['timeSeries']['datedValues'] ?? [] as $v) {
                    $d = sprintf('%04d-%02d-%02d', $v['date']['year'], $v['date']['month'], $v['date']['day']);
                    $per[$d][$series['dailyMetric']] = (int) ($v['value'] ?? 0);
                }
            }
        }
        foreach ($per as $d => $m) {
            $views = ($m['BUSINESS_IMPRESSIONS_DESKTOP_MAPS'] ?? 0) + ($m['BUSINESS_IMPRESSIONS_DESKTOP_SEARCH'] ?? 0) + ($m['BUSINESS_IMPRESSIONS_MOBILE_MAPS'] ?? 0) + ($m['BUSINESS_IMPRESSIONS_MOBILE_SEARCH'] ?? 0);
            $this->day($a, $d, ['views' => $views, 'clicks' => $m['WEBSITE_CLICKS'] ?? 0,
                'engagements' => ($m['CALL_CLICKS'] ?? 0) + ($m['BUSINESS_DIRECTION_REQUESTS'] ?? 0) + ($m['WEBSITE_CLICKS'] ?? 0)]);
        }
    }

    private function ytDaily(Account $a, int $days): void
    {
        $c = $this->ok(Http::timeout(20)->withToken($a->token)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'statistics', 'id' => $a->external_id]), 'YouTube')['items'][0]['statistics'] ?? [];
        $this->day($a, today()->toDateString(), ['followers' => $c['subscriberCount'] ?? null]);
        $rep = $this->ok(Http::timeout(30)->withToken($a->token)->get('https://youtubeanalytics.googleapis.com/v2/reports', [
            'ids' => 'channel==MINE', 'startDate' => today()->subDays($days)->toDateString(), 'endDate' => today()->subDay()->toDateString(),
            'metrics' => 'views,likes,comments,shares', 'dimensions' => 'day',
        ]), 'YouTube Analytics');
        foreach ($rep['rows'] ?? [] as $r) {
            $this->day($a, $r[0], ['views' => $r[1], 'engagements' => $r[2] + $r[3] + $r[4]]);
        }
    }

    public function syncPosts(Account $a): void
    {
        if ($a->platform === 'google') {
            $this->reviews($a);

            return;
        }
        $uploads = $a->meta['uploads'] ?? null;
        if (! $uploads) {
            return;
        }
        $ids = collect($this->ok(Http::timeout(20)->withToken($a->token)->get('https://www.googleapis.com/youtube/v3/playlistItems', ['part' => 'contentDetails', 'playlistId' => $uploads, 'maxResults' => 25]), 'YouTube videos')['items'] ?? [])
            ->pluck('contentDetails.videoId')->filter()->implode(',');
        if ($ids === '') {
            return;
        }
        foreach ($this->ok(Http::timeout(20)->withToken($a->token)->get('https://www.googleapis.com/youtube/v3/videos', ['part' => 'snippet,statistics', 'id' => $ids]), 'YouTube videos')['items'] ?? [] as $v) {
            $this->post($a, $v['id'], ['published_at' => self::ts($v['snippet']['publishedAt'] ?? null), 'kind' => 'video', 'text' => $v['snippet']['title'] ?? null,
                'permalink' => 'https://www.youtube.com/watch?v='.$v['id'], 'thumb_url' => $v['snippet']['thumbnails']['medium']['url'] ?? null,
                'views' => $v['statistics']['viewCount'] ?? null, 'likes' => $v['statistics']['likeCount'] ?? null, 'comments' => $v['statistics']['commentCount'] ?? null]);
        }
    }

    private function reviews(Account $a): void
    {
        $res = $this->ok(Http::timeout(30)->withToken($a->token)->get('https://mybusiness.googleapis.com/v4/'.($a->meta['account'] ?? '').'/'.$a->external_id.'/reviews', ['pageSize' => 50]), 'Google reviews');
        foreach ($res['reviews'] ?? [] as $r) {
            Review::updateOrCreate(['account_id' => $a->id, 'external_id' => $r['reviewId']], [
                'author' => $r['reviewer']['displayName'] ?? null, 'rating' => self::STARS[$r['starRating'] ?? ''] ?? null, 'text' => $r['comment'] ?? null,
                'reply' => $r['reviewReply']['comment'] ?? null, 'reviewed_at' => isset($r['createTime']) ? Carbon::parse($r['createTime']) : null,
            ]);
        }
        $a->update(['meta' => array_merge($a->meta ?? [], ['rating' => $res['averageRating'] ?? null, 'reviews' => $res['totalReviewCount'] ?? null])]);
    }
}
