<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Social\Account;
use App\Models\Social\Post;
use App\Models\User;
use App\Services\Social\Social;
use App\Services\Website\SocialFeed;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Facebook: older posts are fetched once (so older #Afrit6 posts reach the website), whole texts are kept, a lost connection is explained. */
class SocialHistoryTest extends TestCase
{
    use RefreshDatabase;

    private int $page2 = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        config(['social.meta.app_id' => '1', 'social.meta.app_secret' => 's']);
        Http::fake(function (HttpRequest $r) {
            $u = $r->url();
            if (str_contains($u, 'after=page2')) {
                $this->page2++;

                return Http::response(['data' => [[
                    'id' => '1_old', 'created_time' => now()->subYears(2)->toIso8601String(), 'permalink_url' => 'https://www.facebook.com/cluk/posts/old',
                    'message' => "Opening day of our co-working space\n".str_repeat('A long story about the building. ', 60)."\n#Afrit6 #Zulte", 'full_picture' => 'https://scontent.example/old.jpg',
                ]]]);
            }
            if (str_contains($u, '/published_posts')) {
                return Http::response(['data' => [['id' => '1_new', 'created_time' => now()->subDay()->toIso8601String(), 'message' => 'New LED walls in stock #CLUKsales']],
                    'paging' => ['next' => 'https://graph.facebook.com/v23.0/804/published_posts?after=page2']]);
            }
            if (str_contains($u, 'old.jpg')) {
                return Http::response('jpegdata', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response(['followers_count' => 10, 'data' => []]);
        });
    }

    public function test_older_tagged_posts_are_fetched_once_and_reach_the_website(): void
    {
        $fb = Account::create(['platform' => 'facebook', 'external_id' => '804', 'name' => 'CLUK', 'token' => 'page-token', 'status' => 'connected', 'meta' => []]);
        $this->assertTrue(Social::sync($fb, true, 4));
        $this->assertSame(1, $this->page2);
        $old = Post::where('external_id', '1_old')->firstOrFail();
        $this->assertGreaterThan(1000, mb_strlen($old->text)); // the tags at the end are kept
        $this->assertNotNull($old->fresh()->thumb_path); // its picture is saved while Meta's link still works
        $this->assertNotEmpty($fb->fresh()->meta['history_at']);
        $this->assertSame(['1_old'], SocialFeed::for('afrit6')->pluck('url')->map(fn ($u) => basename($u) === 'old' ? '1_old' : $u)->values()->all());
        $this->assertSame(1, SocialFeed::for('sales')->count());

        // Next time only the newest page.
        Social::sync($fb->fresh(), true, 4);
        $this->assertSame(1, $this->page2);
    }

    public function test_a_lost_connection_is_explained(): void
    {
        $boss = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        Account::create(['platform' => 'facebook', 'external_id' => '804', 'name' => 'CLUK', 'token' => 'x', 'status' => 'error',
            'last_error' => "Facebook page: HTTP 400 Any of the pages_read_engagement, pages_manage_metadata, pages_read_user_content permission(s) must be granted before impersonating a user's page."]);
        $this->actingAs($boss)->get('/media/social')->assertOk()->assertSee('The connection with Meta no longer works');
    }
}
