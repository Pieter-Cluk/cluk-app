<?php

namespace Tests\Feature;

use App\Models\Social\Account;
use App\Models\Social\Post;
use App\Services\Website\SocialFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Website › news: Facebook and Instagram posts placed by their #CLUK… hashtag. */
class WebsiteNewsTest extends TestCase
{
    use RefreshDatabase;

    private function posts(): void
    {
        $ig = Account::create(['platform' => 'instagram', 'external_id' => 'ig1', 'name' => 'cluk.live', 'status' => 'connected']);
        $fb = Account::create(['platform' => 'facebook', 'external_id' => 'fb1', 'name' => 'CLUK', 'status' => 'connected', 'url' => 'https://facebook.com/cluk']);
        $li = Account::create(['platform' => 'linkedin', 'external_id' => 'li1', 'name' => 'CLUK BV', 'status' => 'connected']);
        $n = 0;
        foreach ([
            [$ig, "New L-Acoustics K2 in the warehouse\nReady for the season. #CLUK-dry-hire #livesound", 'https://cdn.example/k2.jpg'],
            [$fb, "Autumn promo on Astera tubes #CLUKsales", 'https://cdn.example/astera.jpg'],
            [$ig, "Wedding season at Factory #cluk_factory", null],
            [$fb, "Two desks free at Afrit 6 #CLUK-Afrit6", 'https://cdn.example/desk.jpg'],
            [$fb, "Team lunch", 'https://cdn.example/lunch.jpg'],
            [$li, "LinkedIn only #CLUKsales", 'https://cdn.example/li.jpg'],
        ] as [$acc, $text, $img]) {
            Post::create(['account_id' => $acc->id, 'external_id' => 'p'.(++$n), 'published_at' => now()->subDays($n), 'kind' => 'post', 'text' => $text,
                'permalink' => 'https://example.com/p'.$n, 'thumb_url' => $img]);
        }
    }

    public function test_hashtags_place_posts_on_the_right_parts(): void
    {
        $this->assertSame(['dryhire'], SocialFeed::sections('Nice #CLUKdryhire'));
        $this->assertSame(['dryhire', 'afrit6'], SocialFeed::sections('#Cluk-Dry-Hire and #CLUK_afrit_6'));
        $this->assertSame(['sales', 'factory'], SocialFeed::sections("#CLUK-sales\n#CLUKFactory #clukfoo"));
        $this->assertSame([], SocialFeed::sections('#cluk #dryhire'));
    }

    public function test_homepage_news_page_and_sales_frame(): void
    {
        $this->posts();
        $this->get('/site')->assertOk()->assertSee('New L-Acoustics K2 in the warehouse')->assertSee('Two desks free at Afrit 6')
            ->assertSee('Latest posts')->assertDontSee('#CLUK-dry-hire')->assertDontSee('LinkedIn only');
        $this->get('/site/news')->assertOk()->assertSee('Team lunch')->assertSee('Wedding season at Factory');
        $this->get('/site/news?part=sales')->assertOk()->assertSee('Autumn promo on Astera tubes')->assertDontSee('Team lunch');
        $this->get('/site/news?part=dryhire&lang=nl')->assertOk()->assertSee('Nieuws')->assertSee('New L-Acoustics K2');
        $this->get('/site/sales')->assertOk()->assertSee('Autumn promo on Astera tubes');
    }

    public function test_pictures_come_from_our_server(): void
    {
        $this->posts();
        Http::fake(['cdn.example/*' => Http::response(base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAAA//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AN//Z'), 200, ['Content-Type' => 'image/jpeg'])]);
        $k2 = Post::where('external_id', 'p1')->first();
        $this->get('/site/news/image/'.$k2->id)->assertOk();
        $this->get('/site/news/image/'.Post::where('external_id', 'p6')->value('id'))->assertNotFound(); // LinkedIn is not on the website
        $this->get('/site/news/video/'.$k2->id)->assertNotFound(); // no video kept for this post
    }
}
