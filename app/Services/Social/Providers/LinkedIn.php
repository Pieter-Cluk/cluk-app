<?php

namespace App\Services\Social\Providers;

use App\Models\Social\Account;
use App\Models\Social\PlanTarget;
use App\Services\Social\Media;
use App\Services\Social\Provider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * LinkedIn company pages via the Community Management API (versioned REST
 * API). Access tokens last 60 days and are refreshed with the refresh token.
 */
class LinkedIn extends Provider
{
    private const API = 'https://api.linkedin.com/rest/';

    public function key(): string
    {
        return 'linkedin';
    }

    public function configured(): bool
    {
        return filled(config('social.linkedin.client_id')) && filled(config('social.linkedin.client_secret'));
    }

    public function authUrl(string $state): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code', 'client_id' => config('social.linkedin.client_id'), 'redirect_uri' => $this->redirectUri(),
            'state' => $state, 'scope' => implode(' ', config('social.linkedin.scopes')),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function api(string $token): PendingRequest
    {
        return Http::timeout(30)->withToken($token)->withHeaders(['LinkedIn-Version' => config('social.linkedin.version'), 'X-Restli-Protocol-Version' => '2.0.0']);
    }

    private function tokens(array $form): array
    {
        $t = $this->ok(Http::timeout(20)->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', $form + [
            'client_id' => config('social.linkedin.client_id'), 'client_secret' => config('social.linkedin.client_secret'),
        ]), 'LinkedIn sign-in');

        return ['token' => $t['access_token'], 'refresh_token' => $t['refresh_token'] ?? null, 'token_expires_at' => now()->addSeconds((int) ($t['expires_in'] ?? 5184000))];
    }

    public function connect(string $code): array
    {
        $tok = $this->tokens(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri()]);
        $acls = $this->ok($this->api($tok['token'])->get(self::API.'organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED']), 'LinkedIn pages')['elements'] ?? [];
        $out = [];
        foreach ($acls as $acl) {
            $id = preg_replace('/\D/', '', (string) ($acl['organization'] ?? $acl['organizationTarget'] ?? ''));
            if ($id === '') {
                continue;
            }
            $org = [];
            try {
                $org = $this->ok($this->api($tok['token'])->get(self::API.'organizations/'.$id), 'LinkedIn page');
            } catch (Throwable) {
            }
            $out[] = $tok + ['platform' => 'linkedin', 'external_id' => $id, 'name' => $org['localizedName'] ?? 'LinkedIn '.$id,
                'url' => ! empty($org['vanityName']) ? 'https://www.linkedin.com/company/'.$org['vanityName'] : null, 'meta' => []];
        }

        return $out;
    }

    public function refresh(Account $a): void
    {
        if (! $a->refresh_token || ($a->token_expires_at && $a->token_expires_at->gt(now()->addDays(5)))) {
            return;
        }
        $a->update($this->tokens(['grant_type' => 'refresh_token', 'refresh_token' => $a->refresh_token]));
    }

    private static function urn(Account $a): string
    {
        return 'urn:li:organization:'.$a->external_id;
    }

    // ── Figures ─────────────────────────────────────────────────────────────

    public function syncDaily(Account $a, int $days): void
    {
        $size = $this->ok($this->api($a->token)->get(self::API.'networkSizes/'.rawurlencode(self::urn($a)), ['edgeType' => 'COMPANY_FOLLOWED_BY_MEMBER']), 'LinkedIn followers');
        $this->day($a, today()->toDateString(), ['followers' => $size['firstDegreeSize'] ?? null]);
        // Rest.li parameters: the parentheses must stay as they are.
        $url = self::API.'organizationalEntityShareStatistics?q=organizationalEntity&organizationalEntity='.rawurlencode(self::urn($a))
            .'&timeIntervals=(timeRange:(start:'.(today()->subDays($days)->getTimestampMs()).',end:'.(today()->getTimestampMs()).'),timeGranularityType:DAY)';
        foreach ($this->ok($this->api($a->token)->get($url), 'LinkedIn statistics')['elements'] ?? [] as $e) {
            $s = $e['totalShareStatistics'] ?? [];
            $this->day($a, Carbon::createFromTimestampMs($e['timeRange']['start'])->toDateString(), [
                'reach' => $s['uniqueImpressionsCount'] ?? null, 'views' => $s['impressionCount'] ?? null, 'clicks' => $s['clickCount'] ?? null,
                'engagements' => isset($s['likeCount']) ? ($s['likeCount'] + ($s['commentCount'] ?? 0) + ($s['shareCount'] ?? 0) + ($s['clickCount'] ?? 0)) : null,
            ]);
        }
    }

    public function syncPosts(Account $a): void
    {
        $posts = $this->ok($this->api($a->token)->get(self::API.'posts?author='.rawurlencode(self::urn($a)).'&q=author&count=20'), 'LinkedIn posts')['elements'] ?? [];
        $byUrn = [];
        foreach ($posts as $p) {
            $byUrn[$p['id']] = $this->post($a, $p['id'], [
                'published_at' => isset($p['publishedAt']) ? Carbon::createFromTimestampMs($p['publishedAt']) : (isset($p['createdAt']) ? Carbon::createFromTimestampMs($p['createdAt']) : null),
                'kind' => isset($p['content']['media']) ? 'photo' : (isset($p['content']['article']) ? 'link' : 'post'),
                'text' => isset($p['commentary']) ? preg_replace('/\\\\(.)/', '$1', $p['commentary']) : null,
                'permalink' => 'https://www.linkedin.com/feed/update/'.$p['id'],
            ]);
        }
        $shares = array_filter(array_keys($byUrn), fn ($u) => str_contains($u, ':share:'));
        $ugc = array_filter(array_keys($byUrn), fn ($u) => str_contains($u, ':ugcPost:'));
        if (! $shares && ! $ugc) {
            return;
        }
        $url = self::API.'organizationalEntityShareStatistics?q=organizationalEntity&organizationalEntity='.rawurlencode(self::urn($a))
            .($shares ? '&shares=List('.implode(',', array_map('rawurlencode', $shares)).')' : '')
            .($ugc ? '&ugcPosts=List('.implode(',', array_map('rawurlencode', $ugc)).')' : '');
        try {
            foreach ($this->ok($this->api($a->token)->get($url), 'LinkedIn post statistics')['elements'] ?? [] as $e) {
                $urn = $e['share'] ?? $e['ugcPost'] ?? null;
                $s = $e['totalShareStatistics'] ?? [];
                if ($urn && isset($byUrn[$urn])) {
                    $byUrn[$urn]->update(['views' => $s['impressionCount'] ?? null, 'reach' => $s['uniqueImpressionsCount'] ?? null, 'likes' => $s['likeCount'] ?? null,
                        'comments' => $s['commentCount'] ?? null, 'shares' => $s['shareCount'] ?? null, 'clicks' => $s['clickCount'] ?? null]);
                }
            }
        } catch (Throwable) {
        }
    }

    // ── Publishing ──────────────────────────────────────────────────────────

    /** LinkedIn's "little text" format: these characters must be escaped. */
    public static function escape(string $text): string
    {
        return preg_replace('/([\\\\|{}@\[\]()<>#*_~])/', '\\\\$1', $text);
    }

    public function publish(PlanTarget $t): string
    {
        $a = $t->account;
        $plan = $t->plan;
        $body = ['author' => self::urn($a), 'commentary' => self::escape($t->text()), 'visibility' => 'PUBLIC',
            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
            'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false];
        $images = $plan->images();
        if ($images) {
            $ids = array_map(fn ($img) => $this->uploadImage($a, $plan, $img), array_slice($images, 0, 20));
            $body['content'] = count($ids) === 1 ? ['media' => ['id' => $ids[0]]] : ['multiImage' => ['images' => array_map(fn ($id) => ['id' => $id], $ids)]];
        } elseif ($link = $plan->trackedLink('linkedin')) {
            $body['content'] = ['article' => ['source' => $link, 'title' => mb_substr($plan->title, 0, 200)]];
        }
        $r = $this->api($a->token)->post(self::API.'posts', $body);
        $this->ok($r, 'LinkedIn post');
        $urn = $r->header('x-restli-id') ?: ($r->json('id') ?? '');
        $t->update(['external_id' => $urn, 'permalink' => $urn ? 'https://www.linkedin.com/feed/update/'.$urn : null, 'status' => 'published', 'published_at' => now(), 'error' => null]);

        return 'published';
    }

    private function uploadImage(Account $a, $plan, array $img): string
    {
        $init = $this->ok($this->api($a->token)->post(self::API.'images?action=initializeUpload', ['initializeUploadRequest' => ['owner' => self::urn($a)]]), 'LinkedIn image')['value'] ?? [];
        if (empty($init['uploadUrl']) || empty($init['image'])) {
            throw new RuntimeException('LinkedIn image: no upload address');
        }
        $bytes = Storage::disk('local')->get($img['path']);
        $this->ok(Http::timeout(120)->withToken($a->token)->withBody($bytes, Media::mime($img))->put($init['uploadUrl']), 'LinkedIn image upload');

        return $init['image'];
    }
}
