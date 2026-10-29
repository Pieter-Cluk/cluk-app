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
        putenv('APP_URL=https://app.cluk.live');
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = 'https://app.cluk.live';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('WEBSITE_HOST');
        putenv('APP_URL');
        unset($_ENV['WEBSITE_HOST'], $_SERVER['WEBSITE_HOST'], $_ENV['APP_URL'], $_SERVER['APP_URL']);
    }

    public function test_the_website_answers_on_www_cluk_live(): void
    {
        $this->assertSame('https://www.cluk.live', rtrim(route('site.home'), '/'));
        $this->assertSame('https://www.cluk.live/sales', route('site.sales'));
        $this->get('https://www.cluk.live/')->assertOk()->assertSee('https://app.cluk.live/afrit6', false);
        $this->get('https://www.cluk.live/sales')->assertOk()->assertSee('clukie-cfg', false);
        $this->get('https://www.cluk.live/dry-hire')->assertOk();
    }

    public function test_cluk_live_and_the_old_preview_links_forward_with_their_utm_codes(): void
    {
        $this->get('https://cluk.live/')->assertStatus(301)->assertRedirect('https://www.cluk.live/');
        $this->get('https://cluk.live/sales?utm_source=instagram&utm_medium=bio')
            ->assertStatus(301)->assertRedirect('https://www.cluk.live/sales?utm_source=instagram&utm_medium=bio');
        $this->get('https://app.cluk.live/site')->assertStatus(301)->assertRedirect('https://www.cluk.live/');
        $this->get('https://app.cluk.live/site/sales?utm_source=linkedin&utm_campaign=launch')
            ->assertStatus(301)->assertRedirect('https://www.cluk.live/sales?utm_source=linkedin&utm_campaign=launch');
    }

    public function test_the_platform_stays_on_app_cluk_live(): void
    {
        $this->get('https://app.cluk.live/login')->assertOk();
        $this->get('https://app.cluk.live/')->assertRedirect(); // portal: to the login page, not the website
        $this->assertStringNotContainsString('www.cluk.live', (string) $this->get('https://app.cluk.live/')->headers->get('Location'));
    }
}
