<?php

namespace Tests\Feature;

use App\Models\Social\Account;
use App\Models\Social\Post;
use App\Services\Social\Social;
use App\Services\Website\SocialFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** LinkedIn posts on the website (with their picture), and a post on several platforms shows only once. */
class SocialFeedLinkedInTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $platform, string $id): Account
    {
        return Account::create(['platform' => $platform, 'external_id' => $id, 'name' => 'CLUK', 'token' => 'x', 'status' => 'connected', 'meta' => []]);
    }

    public function test_a_cross_posted_post_shows_once_and_linkedin_only_posts_show(): void
    {
        $fb = $this->account('facebook', '1');
        $ig = $this->account('instagram', '2');
        $li = $this->account('linkedin', '3');
        $text = "New moving heads in our dry-hire!\nCome and see them.";
        Post::create(['account_id' => $li->id, 'external_id' => 'a', 'published_at' => now()->subHours(2), 'text' => $text, 'thumb_url' => 'https://li/img.jpg']);
        Post::create(['account_id' => $ig->id, 'external_id' => 'b', 'published_at' => now()->subHours(2), 'text' => $text.' #promo', 'thumb_url' => 'https://ig/img.jpg']);
        Post::create(['account_id' => $fb->id, 'external_id' => 'c', 'published_at' => now()->subHours(2), 'text' => $text]); // no picture
        Post::create(['account_id' => $li->id, 'external_id' => 'd', 'published_at' => now()->subDay(), 'text' => 'We are hiring a warehouse technician', 'thumb_url' => 'https://li/job.jpg']);

        $all = SocialFeed::all();
        $this->assertCount(2, $all);
        $this->assertSame('instagram', $all[0]['platform']); // the copy with a picture, before LinkedIn
        $this->assertSame('linkedin', $all[1]['platform']);
    }

    public function test_linkedin_sync_keeps_the_post_picture(): void
    {
        Storage::fake('local');
        config(['social.linkedin.client_id' => 'id', 'social.linkedin.client_secret' => 'secret']);
        $li = $this->account('linkedin', '123');
        $li->forceFill(['token_expires_at' => now()->addMonth()])->save();
        Http::fake([
            'api.linkedin.com/rest/posts*' => Http::response(['elements' => [
                ['id' => 'urn:li:share:1', 'publishedAt' => now()->subDay()->getTimestampMs(), 'commentary' => 'Big show tonight', 'content' => ['media' => ['id' => 'urn:li:image:C4E']]],
            ]]),
            'api.linkedin.com/rest/images/*' => Http::response(['downloadUrl' => 'https://media.licdn.com/pic.jpg']),
            'media.licdn.com/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg']),
            '*' => Http::response(['elements' => []]),
        ]);
        Social::sync($li, true);
        $p = Post::where('external_id', 'urn:li:share:1')->firstOrFail();
        $this->assertSame(['photo', 'https://media.licdn.com/pic.jpg'], [$p->kind, $p->thumb_url]);
        $this->assertNotNull($p->fresh()->thumb_path);
        Storage::disk('local')->assertExists($p->fresh()->thumb_path);
    }
}
