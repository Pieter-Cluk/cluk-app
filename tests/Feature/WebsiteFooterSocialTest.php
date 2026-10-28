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
            ->assertSee('href="'.config('website.social.linkedin').'"', false); // not set: the default stays
    }
}
