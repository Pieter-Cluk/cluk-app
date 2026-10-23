<?php

namespace App\Services\Social;

use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\Social\PlanTarget;
use App\Models\Social\Post;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * One platform company's connection (Meta, LinkedIn, Google): sign in (OAuth),
 * the accounts it gives access to, daily figures, recent posts and publishing.
 * Every call fails softly: a platform that changes or is down never breaks a page.
 */
abstract class Provider
{
    abstract public function key(): string;

    abstract public function configured(): bool;

    abstract public function authUrl(string $state): string;

    /** Code from the sign-in → list of accounts (attributes for Account, including the tokens). */
    abstract public function connect(string $code): array;

    /** Daily figures for the last days, and the follower count today. */
    abstract public function syncDaily(Account $a, int $days): void;

    abstract public function syncPosts(Account $a): void;

    /** Publish (or continue publishing) one target. Returns the new status: published, processing or failed. */
    public function publish(PlanTarget $t): string
    {
        throw new RuntimeException(__('social.errors.no_publish'));
    }

    /** Get a fresh access token when it is about to run out. */
    public function refresh(Account $a): void {}

    /** Built from APP_URL, so it is always exactly the address registered with the platform (https, no proxy surprises). */
    public function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/social/connect/'.$this->key().'/callback';
    }

    // ── Helpers for the providers ───────────────────────────────────────────

    protected function ok(Response $r, string $what): array
    {
        if (! $r->successful()) {
            $msg = $r->json('error.message') ?? $r->json('message') ?? $r->json('error_description') ?? $r->json('error') ?? $r->body();
            throw new RuntimeException($what.': HTTP '.$r->status().' '.mb_substr(is_string($msg) ? $msg : json_encode($msg), 0, 300));
        }

        return $r->json() ?? [];
    }

    /** Merge figures into a day: only the values given, the rest stays as it was. */
    protected function day(Account $a, string $date, array $values): void
    {
        $values = array_filter($values, fn ($v) => $v !== null);
        if ($values) {
            $row = Daily::firstOrNew(['account_id' => $a->id, 'date' => $date]);
            $row->fill(array_map(fn ($v) => max(0, (int) round($v)), $values))->save();
        }
    }

    protected function post(Account $a, string $id, array $values): Post
    {
        $p = Post::firstOrNew(['account_id' => $a->id, 'external_id' => $id]);
        $values['text'] = isset($values['text']) ? mb_substr((string) $values['text'], 0, 1000) : $p->text;
        $p->fill($values)->save();

        return $p;
    }

    protected function days(int $n): array
    {
        return collect(range($n, 1))->map(fn ($i) => today()->subDays($i))->all();
    }

    protected static function ts(?string $v): ?Carbon
    {
        return $v ? Carbon::parse($v) : null;
    }
}
