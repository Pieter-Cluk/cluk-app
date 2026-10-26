<?php

namespace App\Services\Social;

use App\Models\AuditLog;
use App\Models\Social\Account;
use App\Models\Social\Plan;
use App\Models\Social\PlanTarget;
use App\Models\Social\Post;
use App\Models\User;
use App\Services\Social\Providers\Google;
use App\Services\Social\Providers\LinkedIn;
use App\Services\Social\Providers\Meta;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Media › Social media: connecting accounts, keeping their figures up to date and publishing planned posts. */
class Social
{
    public const PROVIDERS = ['meta' => Meta::class, 'linkedin' => LinkedIn::class, 'google' => Google::class];

    public static function provider(string $key): Provider
    {
        abort_unless(isset(self::PROVIDERS[$key]), 404);

        return app(self::PROVIDERS[$key]);
    }

    public static function for(Account $a): ?Provider
    {
        $key = config('social.platforms.'.$a->platform.'.provider');

        return $key && isset(self::PROVIDERS[$key]) ? self::provider($key) : null;
    }

    /** After the sign-in: store or update the accounts it gives access to. */
    public static function storeAccounts(string $provider, array $accounts, User $by): array
    {
        $saved = [];
        foreach ($accounts as $data) {
            $a = Account::firstOrNew(['platform' => $data['platform'], 'external_id' => $data['external_id']]);
            $a->fill($data + ['status' => 'connected', 'last_error' => null, 'connected_by_id' => $by->id])->save();
            $saved[] = $a;
            AuditLog::record('social.connected', $a, ['platform' => $a->platform, 'name' => $a->name], $by);
        }

        return $saved;
    }

    public static function disconnect(Account $a, User $by): void
    {
        $a->update(['status' => 'off', 'token' => null, 'refresh_token' => null]);
        AuditLog::record('social.disconnected', $a, ['platform' => $a->platform, 'name' => $a->name], $by);
    }

    /** Fetch figures (and, less often, posts) for one account. */
    public static function sync(Account $a, bool $posts = true, int $days = 7): bool
    {
        $p = self::for($a);
        if (! $p || ! $p->configured() || $a->status === 'off' || ! $a->token) {
            return false;
        }
        try {
            $p->refresh($a);
            $p->syncDaily($a, $days);
            if ($posts) {
                $p->syncPosts($a);
                $a->posts_synced_at = now();
                PostMedia::keepFor($a);
            }
            $a->forceFill(['last_synced_at' => now(), 'status' => 'connected', 'last_error' => null])->save();

            return true;
        } catch (Throwable $e) {
            $a->forceFill(['last_synced_at' => now(), 'status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
            Log::warning('Social sync', ['account' => $a->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return false;
        }
    }

    /** Background: the accounts whose figures are oldest, a few at a time. */
    public static function syncDue(int $max = 3): int
    {
        $n = 0;
        $due = Account::whereIn('status', ['connected', 'error'])->whereNotNull('token')
            ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', now()->subHours((int) config('social.sync_hours', 3)))
                ->orWhere(fn ($q) => $q->where('status', 'error')->where('last_synced_at', '<', now()->subMinutes(30))))
            ->orderByRaw('last_synced_at is not null')->orderBy('last_synced_at')->limit($max)->get();
        // Facebook and Instagram accounts whose older posts were not fetched yet (just connected): first.
        $history = Account::where('status', 'connected')->whereNotNull('token')->whereIn('platform', ['facebook', 'instagram'])->get()
            ->filter(fn ($a) => empty($a->meta['history_at']))->take(1);
        foreach ($history as $a) {
            $n += (int) self::sync($a, true, 4);
        }
        $due = $due->reject(fn ($a) => $history->contains('id', $a->id));
        foreach ($due as $a) {
            $posts = $a->status === 'error' || ! $a->posts_synced_at || $a->posts_synced_at->lt(now()->subHours((int) config('social.posts_hours', 6)));
            $n += (int) self::sync($a, $posts, $a->last_synced_at ? 4 : 90);
        }

        return $n;
    }

    /** Background: publish what is due, and finish Instagram videos that were still being processed. */
    public static function publishDue(): int
    {
        $n = 0;
        foreach (Plan::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get() as $plan) {
            $plan->update(['status' => 'publishing']);
        }
        foreach (Plan::where('status', 'publishing')->with(['targets.account'])->get() as $plan) {
            foreach ($plan->targets->whereIn('status', ['pending', 'processing']) as $t) {
                $t->setRelation('plan', $plan);
                $n += (int) self::publishTarget($t);
            }
            self::finish($plan->fresh('targets'));
        }

        return $n;
    }

    public static function publishTarget(PlanTarget $t): bool
    {
        $p = self::for($t->account);
        try {
            if (! $p || ! $t->account->canPublish()) {
                throw new \RuntimeException(__('social.errors.not_connected'));
            }
            $p->refresh($t->account);
            $status = $p->publish($t);
            if ($status === 'published') {
                Post::updateOrCreate(['account_id' => $t->account_id, 'external_id' => $t->external_id],
                    ['published_at' => now(), 'kind' => $t->plan->video() ? 'video' : ($t->plan->images() ? 'photo' : 'post'), 'text' => mb_substr($t->text(), 0, 1000), 'permalink' => $t->permalink, 'plan_id' => $t->plan_id]);
                AuditLog::record('social.published', $t->plan, ['account' => $t->account->label(), 'link' => $t->permalink], $t->plan->scheduledBy);
            }

            return $status === 'published';
        } catch (Throwable $e) {
            $tries = $t->tries + 1;
            // Three tries (a platform can be busy), then it stays failed with the reason.
            $t->update(['tries' => $tries, 'status' => $tries >= 3 ? 'failed' : $t->status, 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }

    private static function finish(Plan $plan): void
    {
        $s = $plan->targets->pluck('status');
        if ($s->contains(fn ($x) => in_array($x, ['pending', 'processing'], true))) {
            return;
        }
        $plan->update(['status' => $s->every(fn ($x) => $x === 'published') ? 'done' : ($s->contains('published') ? 'partly' : 'failed')]);
    }
}
