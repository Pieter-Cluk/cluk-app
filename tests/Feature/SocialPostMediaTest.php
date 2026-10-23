<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Social\Account;
use App\Models\Social\Post;
use App\Models\User;
use App\Services\Social\PostMedia;
use App\Services\Social\Social;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Meta sync for the website: videos, own copies of pictures and videos, and the "show on the website" tick. */
class SocialPostMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        config(['social.meta.app_id' => '1', 'social.meta.app_secret' => 's']);
        Http::fake(function (HttpRequest $r) {
            $u = $r->url();
            if (str_contains($u, 'cdn.example/pic')) {
                return Http::response(str_repeat('j', 2000), 200, ['Content-Type' => 'image/jpeg']);
            }
            if (str_contains($u, 'cdn.example/big')) {
                return Http::response('x', 200, ['Content-Type' => 'video/mp4', 'Content-Length' => (string) (61 * 1024 * 1024)]);
            }
            if (str_contains($u, 'cdn.example/vid')) {
                return Http::response(str_repeat('v', 5000), 200, ['Content-Type' => 'video/mp4']);
            }
            if (str_contains($u, '/media')) {
                return Http::response(['data' => [
                    ['id' => 'ig1', 'caption' => 'New lights #CLUKfactory', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'permalink' => 'https://instagram.com/p/1', 'thumbnail_url' => 'https://cdn.example/pic1.jpg', 'media_url' => 'https://cdn.example/vid1.mp4', 'timestamp' => now()->subDay()->toIso8601String()],
                    ['id' => 'ig2', 'caption' => 'A photo', 'media_type' => 'IMAGE', 'permalink' => 'https://instagram.com/p/2', 'media_url' => 'https://cdn.example/pic2.jpg', 'timestamp' => now()->subDays(2)->toIso8601String()],
                ]]);
            }
            if (str_contains($u, 'published_posts')) {
                return Http::response(['data' => [
                    ['id' => 'p_1', 'message' => 'Our new truck', 'created_time' => now()->subHours(5)->toIso8601String(), 'permalink_url' => 'https://facebook.com/1', 'full_picture' => 'https://cdn.example/pic3.jpg',
                        'attachments' => ['data' => [['media_type' => 'video', 'target' => ['id' => '777'], 'media' => ['image' => ['src' => 'https://cdn.example/pic3.jpg']]]]]],
                ]]);
            }
            if (str_contains($u, '/777')) {
                return Http::response(['source' => 'https://cdn.example/vid3.mp4']);
            }

            return Http::response(['data' => [], 'followers_count' => 10]);
        });
    }

    public function test_videos_and_own_copies_and_the_website_tick(): void
    {
        $ig = Account::create(['platform' => 'instagram', 'external_id' => '178', 'name' => 'CLUK', 'token' => 't', 'status' => 'connected']);
        $fb = Account::create(['platform' => 'facebook', 'external_id' => '804', 'name' => 'CLUK', 'token' => 't', 'status' => 'connected']);
        Social::sync($ig, true, 2);
        Social::sync($fb, true, 2);

        $reel = Post::where('external_id', 'ig1')->firstOrFail();
        $this->assertSame(['reel', 'https://cdn.example/vid1.mp4', 'https://cdn.example/pic1.jpg'], [$reel->kind, $reel->video_url, $reel->thumb_url]);
        $this->assertNotNull($reel->thumb_path);
        $this->assertNotNull($reel->video_path);
        Storage::disk('local')->assertExists([$reel->thumb_path, $reel->video_path]);
        $photo = Post::where('external_id', 'ig2')->firstOrFail();
        $this->assertNull($photo->video_url);
        $this->assertNotNull($photo->thumb_path);
        $fbVideo = Post::where('external_id', 'p_1')->firstOrFail();
        $this->assertSame(['video', 'https://cdn.example/vid3.mp4'], [$fbVideo->kind, $fbVideo->video_url]);
        $this->assertNotNull($fbVideo->video_path);
        // A second sync does not fetch again.
        $before = $reel->fresh()->thumb_path;
        Social::sync($ig, true, 2);
        $this->assertSame($before, $reel->fresh()->thumb_path);

        // Social › Posts › On the website: untick a post.
        $boss = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $this->actingAs($boss)->get(route('social.plans', ['s' => 'site']))->assertOk()->assertSee('Show on the website')->assertSee('Our new truck');
        $this->assertTrue($photo->fresh()->on_website);
        $this->actingAs($boss)->post(route('social.posts.website', $photo), ['on' => 0])->assertRedirect();
        $this->assertFalse($photo->fresh()->on_website);

        // The Factory booking page shows the #CLUKfactory post.
        $this->get('/book')->assertOk()->assertSee('Latest from Cluk Factory')->assertSee('New lights');
    }

    public function test_videos_above_60_mb_are_not_kept(): void
    {
        $a = Account::create(['platform' => 'instagram', 'external_id' => '178', 'name' => 'CLUK', 'token' => 't', 'status' => 'connected']);
        $p = Post::create(['account_id' => $a->id, 'external_id' => 'big', 'published_at' => now(), 'video_url' => 'https://cdn.example/big.mp4']);
        $this->assertNull(PostMedia::video($p));
        $this->assertNull($p->fresh()->video_path);
        $this->assertNull($p->fresh()->video_url); // not tried again
    }
}
