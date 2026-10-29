<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The website on its own address (WEBSITE_HOST=www.cluk.live) instead of the app.cluk.live/site preview. */
class WebsiteOwnAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // routes are registered at boot, so the address has to be known before the app starts
        putenv('WEBSITE_HOST=www.cluk.live');
        $_ENV['WEBSITE_HOST'] = $_SERVER['WEBSITE_HOST'] = 'www.cluk.live';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('WEBSITE_HOST');
        unset($_ENV['WEBSITE_HOST'], $_SERVER['WEBSITE_HOST']);
    }

    public function test_the_website_answers_on_www_cluk_live(): void
    {
        $this->get('https://www.cluk.live/')->assertOk()
            ->assertSee('href="https://www.cluk.live/sales"', false)      // the site's own links
            ->assertSee(rtrim(config('app.url'), '/').'/afrit6', false)    // Afrit 6 and the team stay on the app
            ->assertDontSee(rtrim(config('app.url'), '/').'/site', false);
        $this->get('https://www.cluk.live/sales')->assertOk()->assertSee('clukie-cfg', false);
        $this->get('https://www.cluk.live/dry-hire')->assertOk();
    }

    public function test_cluk_live_and_the_old_preview_links_forward_with_their_utm_codes(): void
    {
        $this->get('https://cluk.live/')->assertStatus(301)->assertRedirect('https://www.cluk.live/');
        $this->get('https://cluk.live/sales?utm_source=instagram&utm_medium=bio')
            ->assertStatus(301)->assertRedirect('https://www.cluk.live/sales?utm_source=instagram&utm_medium=bio');
        $app = rtrim(config('app.url'), '/'); // the platform's own address
        $this->get($app.'/site')->assertStatus(301)->assertRedirect('https://www.cluk.live/');
        $this->get($app.'/site/sales?utm_source=linkedin&utm_campaign=launch')
            ->assertStatus(301)->assertRedirect('https://www.cluk.live/sales?utm_source=linkedin&utm_campaign=launch');
    }

    public function test_the_platform_stays_on_app_cluk_live(): void
    {
        $app = rtrim(config('app.url'), '/');
        $this->get($app.'/login')->assertOk();
        $this->get($app.'/')->assertRedirect(); // portal: to the login page, not the website
        $this->assertStringNotContainsString('www.cluk.live', (string) $this->get($app.'/')->headers->get('Location'));
    }
}
