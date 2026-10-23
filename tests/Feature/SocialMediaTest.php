<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Social\Account;
use App\Models\Social\Daily;
use App\Models\Social\Plan;
use App\Models\Social\PlanTarget;
use App\Models\Social\Post;
use App\Models\User;
use App\Services\Social\Providers\LinkedIn;
use App\Services\Social\Providers\Meta;
use App\Services\Social\Social;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Media › Social media: connecting accounts, the dashboard, and planning and publishing posts. */
class SocialMediaTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $calls = [];

    private bool $viaBusiness = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        config(['social.meta.app_id' => '1650014866758329', 'social.meta.app_secret' => 'test-secret', 'social.meta.config_id' => null]);
        // A small stand-in for the Graph API.
        Http::fake(function (HttpRequest $r) {
            $this->calls[] = $r->method().' '.$r->url();
            $u = $r->url();
            if (str_contains($u, 'oauth/access_token')) {
                return Http::response(['access_token' => 'user-token']);
            }
            if (str_contains($u, 'me/accounts') && $this->viaBusiness) {
                return Http::response(['data' => []]);
            }
            if (str_contains($u, 'me/businesses')) {
                return Http::response(['data' => [['id' => '1721139235236275', 'name' => 'CLUK']]]);
            }
            if (str_contains($u, '1721139235236275/client_pages')) {
                return Http::response(['data' => []]);
            }
            if (str_contains($u, 'me/accounts') || str_contains($u, '1721139235236275/owned_pages')) {
                return Http::response(['data' => [[
                    'id' => '804529296069585', 'name' => 'CLUK', 'access_token' => 'page-token', 'link' => 'https://www.facebook.com/cluk',
                    'instagram_business_account' => ['id' => '17841475959694719', 'username' => 'cluk_group', 'name' => 'CLUK'],
                ]]]);
            }
            if (str_contains($u, '/insights')) {
                return Http::response(['data' => [['values' => [['end_time' => today()->toIso8601String(), 'value' => 50]], 'total_value' => ['value' => 7]]]]);
            }
            if ($r->method() === 'POST' && str_contains($u, '/feed')) {
                return str_contains($r->body(), 'fail') ? Http::response(['error' => ['message' => 'Busy']], 500) : Http::response(['id' => '804529296069585_42']);
            }
            if ($r->method() === 'POST' && str_contains($u, '/photos')) {
                return Http::response(['id' => '9001', 'post_id' => '804529296069585_43']);
            }
            if ($r->method() === 'POST' && str_contains($u, '/media_publish')) {
                return Http::response(['id' => '1799']);
            }
            if ($r->method() === 'POST' && str_contains($u, '/media')) {
                return Http::response(['id' => 'container-1']);
            }

            return Http::response(['followers_count' => 120, 'status_code' => 'FINISHED', 'permalink_url' => 'https://www.facebook.com/cluk/posts/42', 'permalink' => 'https://www.instagram.com/p/x', 'data' => []]);
        });
    }

    private function as(string $role, array $rights = []): User
    {
        $u = User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
        foreach ($rights as $r) {
            $u->grantRight($r);
        }

        return $u;
    }

    private function connect(User $u): void
    {
        $this->actingAs($u)->withSession(['social_state' => ['meta', 'abc']])
            ->get('/social/connect/meta/callback?state=abc&code=xyz')->assertRedirect(route('social.index'));
    }

    public function test_the_dashboard_and_who_may_connect(): void
    {
        $sales = $this->as('sales');
        $this->actingAs($sales)->get('/media/social')->assertOk()->assertSee('No accounts connected yet')->assertDontSee('/media/social/connect/meta');
        $this->actingAs($sales)->get('/media/social/connect/meta')->assertForbidden();

        $boss = $this->as('sales', ['social.connect']);
        $this->actingAs($boss)->get('/media/social')->assertOk()->assertSee('/media/social/connect/meta', false)->assertSee('Keys not set yet');
    }

    public function test_the_sign_in_uses_the_business_configuration_when_set(): void
    {
        $url = app(Meta::class)->authUrl('s1');
        $this->assertStringContainsString('scope=pages_show_list', $url);
        config(['social.meta.config_id' => '123456789']);
        $url = app(Meta::class)->authUrl('s1');
        $this->assertStringContainsString('config_id=123456789', $url);
        $this->assertStringNotContainsString('scope=', $url);
        $this->assertStringContainsString(urlencode('/social/connect/meta/callback'), $url);
    }

    public function test_connecting_stores_the_page_and_instagram_and_fetches_figures(): void
    {
        $boss = $this->as('sales', ['social.connect']);
        // A wrong state is refused.
        $this->actingAs($boss)->withSession(['social_state' => ['meta', 'abc']])->get('/social/connect/meta/callback?state=nope&code=xyz')
            ->assertRedirect(route('social.index'))->assertSessionHasErrors('social');
        $this->assertSame(0, Account::count());

        $this->connect($boss);
        $fb = Account::where('platform', 'facebook')->firstOrFail();
        $ig = Account::where('platform', 'instagram')->firstOrFail();
        $this->assertSame('page-token', $fb->token);
        $this->assertSame('@cluk_group', $ig->handle);
        $this->assertSame('connected', $fb->fresh()->status, (string) $fb->fresh()->last_error);
        $this->assertNotSame('page-token', \DB::table('social_accounts')->where('id', $fb->id)->value('token'), 'tokens are stored encrypted');
        $this->assertSame(120, Daily::where('account_id', $fb->id)->where('date', today()->toDateString())->value('followers'));
        $this->assertSame(2, AuditLog::where('action', 'social.connected')->count());

        $this->actingAs($boss)->get('/media/social')->assertOk()->assertSee('CLUK')->assertSee('@cluk_group')->assertDontSee('No accounts connected yet');
        $this->actingAs($boss)->get('/media/social?d=7')->assertOk();
    }

    public function test_pages_shared_through_the_business_portfolio_are_found_too(): void
    {
        $this->viaBusiness = true;
        $this->connect($this->as('sales', ['social.connect']));
        $this->assertEqualsCanonicalizing(['facebook', 'instagram'], Account::pluck('platform')->all());
    }

    public function test_drafts_planning_and_publishing(): void
    {
        $boss = $this->as('sales', ['social.connect']);
        $this->connect($boss);
        $fb = Account::where('platform', 'facebook')->firstOrFail();
        $ig = Account::where('platform', 'instagram')->firstOrFail();

        // Someone without the publish right can make a draft, not plan it.
        $writer = $this->as('sales');
        $this->actingAs($writer)->get('/media/social/posts/new')->assertOk()->assertSee('Save as draft');
        $this->actingAs($writer)->post('/media/social/posts', ['title' => 'Autumn', 'body' => 'New LED bars', 'accounts' => [$fb->id], 'action' => 'schedule', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')])->assertForbidden();
        $this->actingAs($writer)->post('/media/social/posts', ['title' => 'Autumn', 'body' => 'New LED bars', 'link_url' => 'https://cluk.live/led', 'accounts' => [$fb->id, $ig->id], 'action' => 'draft'])->assertRedirect();
        $plan = Plan::firstOrFail();
        $this->assertSame('draft', $plan->status);
        $this->assertSame(2, $plan->targets()->count());

        // Instagram needs a picture: planning is refused until there is one.
        $pub = $this->as('sales', ['social.publish']);
        $this->actingAs($pub)->put('/media/social/posts/'.$plan->id, ['title' => 'Autumn', 'body' => 'New LED bars', 'link_url' => 'https://cluk.live/led', 'accounts' => [$fb->id, $ig->id], 'action' => 'schedule', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('files');
        $this->actingAs($pub)->put('/media/social/posts/'.$plan->id, ['title' => 'Autumn', 'body' => 'New LED bars', 'link_url' => 'https://cluk.live/led', 'accounts' => [$fb->id, $ig->id], 'action' => 'schedule',
            'scheduled_at' => now('Europe/Brussels')->addHour()->format('Y-m-d H:i'), 'files' => [UploadedFile::fake()->image('bar.jpg', 1080, 1080)]])->assertRedirect(route('social.plans.show', $plan));
        $plan->refresh();
        $this->assertSame('scheduled', $plan->status);
        $this->assertCount(1, $plan->images());
        $this->actingAs($pub)->get('/media/social/posts/'.$plan->id)->assertOk()->assertSee('Autumn');
        $this->actingAs($pub)->get('/media/social/posts?s=planned')->assertOk()->assertSee('Autumn');

        // Not due yet: nothing happens.
        $this->assertSame(0, Social::publishDue());
        // Due: both go out.
        $this->travel(2)->hours();
        $this->assertSame(2, Social::publishDue());
        $plan->refresh();
        $this->assertSame('done', $plan->status);
        $this->assertSame(['published'], $plan->targets()->pluck('status')->unique()->values()->all());
        $this->assertSame(2, Post::whereNotNull('plan_id')->count());
        $this->assertSame(2, AuditLog::where('action', 'social.published')->count());
        // The picture goes to Facebook through a signed link, the link with tracking codes for Facebook.
        $this->assertTrue(collect($this->calls)->contains(fn ($c) => str_starts_with($c, 'POST') && str_contains($c, '804529296069585/photos')));
        $this->assertTrue(collect($this->calls)->contains(fn ($c) => str_contains($c, '/media_publish')));
    }

    public function test_publish_now_and_three_tries(): void
    {
        $boss = $this->as('sales', ['social.connect', 'social.publish']);
        $this->connect($boss);
        $fb = Account::where('platform', 'facebook')->firstOrFail();

        $this->actingAs($boss)->post('/media/social/posts', ['title' => 'Hello', 'body' => 'Hello world', 'accounts' => [$fb->id], 'action' => 'now']);
        $plan = Plan::where('title', 'Hello')->firstOrFail();
        $this->assertSame('done', $plan->status);
        $this->assertSame('https://www.facebook.com/cluk/posts/42', $plan->targets()->value('permalink'));

        // The platform keeps failing: after three tries it stays failed, with the reason.
        $this->actingAs($boss)->post('/media/social/posts', ['title' => 'Bad', 'body' => 'this will fail', 'accounts' => [$fb->id], 'action' => 'now']);
        $bad = Plan::where('title', 'Bad')->firstOrFail();
        Social::publishDue();
        Social::publishDue();
        $t = PlanTarget::where('plan_id', $bad->id)->firstOrFail();
        $this->assertSame('failed', $t->status);
        $this->assertSame(3, $t->tries);
        $this->assertStringContainsString('Busy', $t->error);
        $this->assertSame('failed', $bad->fresh()->status);
        $this->actingAs($boss)->get('/media/social/posts/'.$bad->id)->assertOk()->assertSee('Busy');
    }

    public function test_only_drafts_can_be_deleted_and_texts_are_checked(): void
    {
        $boss = $this->as('sales', ['social.connect', 'social.publish']);
        $this->connect($boss);
        $fb = Account::where('platform', 'facebook')->firstOrFail();
        $this->actingAs($boss)->post('/media/social/posts', ['title' => 'Plan', 'body' => 'x', 'accounts' => [$fb->id], 'action' => 'schedule', 'scheduled_at' => now()->subDay()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('scheduled_at');
        $this->actingAs($boss)->post('/media/social/posts', ['title' => 'Plan', 'body' => 'x', 'accounts' => [], 'action' => 'schedule', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('accounts');
        $this->actingAs($boss)->post('/media/social/posts', ['title' => 'Plan', 'body' => 'x', 'accounts' => [$fb->id], 'action' => 'schedule', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')]);
        $plan = Plan::firstOrFail();
        $this->actingAs($boss)->delete('/media/social/posts/'.$plan->id)->assertStatus(409);
        $this->actingAs($boss)->post('/media/social/posts/'.$plan->id.'/unschedule')->assertRedirect();
        $this->actingAs($boss)->delete('/media/social/posts/'.$plan->id)->assertRedirect(route('social.plans'));
        $this->assertSame(0, Plan::count());
    }

    public function test_tracked_links_and_linkedin_text(): void
    {
        $plan = new Plan(['title' => 'Autumn promo', 'body' => 'x', 'link_url' => 'https://cluk.live/led?ref=1#top']);
        $this->assertSame('https://cluk.live/led?ref=1&utm_source=linkedin&utm_medium=social&utm_campaign=autumn-promo#top', $plan->trackedLink('linkedin'));
        $this->assertSame('Hi \\(you\\) \\#cluk', LinkedIn::escape('Hi (you) #cluk'));
    }
}
