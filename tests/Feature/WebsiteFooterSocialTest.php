<?php

namespace Tests\Feature;

use App\Services\Website\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteFooterSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_icons_use_the_links_from_media_website(): void
    {
        SiteSettings::put('general.social', ['instagram' => 'https://www.instagram.com/cluk_group/', 'facebook' => 'https://www.facebook.com/clukbv'], null);

        $this->get('/site')->assertOk()
            ->assertSee('href="https://www.instagram.com/cluk_group/"', false)
            ->assertSee('href="https://www.facebook.com/clukbv"', false)
            ->assertDontSee('aria-label="LinkedIn"', false); // no LinkedIn page set: no icon

        SiteSettings::put('general.social', ['linkedin' => 'https://www.linkedin.com/company/example/'], null);
        $this->get('/site')->assertOk()
            ->assertSee('href="https://www.linkedin.com/company/example/"', false)
            ->assertSee('href="https://www.instagram.com/cluk_group/"', false); // the default when nothing is saved
    }
}
