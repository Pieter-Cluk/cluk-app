<?php

namespace Tests\Feature;

use App\Services\Website\SocialFeed;
use Tests\TestCase;

class SocialTopicTest extends TestCase
{
    public function test_a_hashtag_gives_the_post_its_label_on_the_sales_page(): void
    {
        $this->assertSame('promo', SocialFeed::topic('Herfstactie op alle kabels #CLUKsales #Actie'));
        $this->assertSame('new', SocialFeed::topic('Shure SM7dB binnen! #nieuw #CLUKsales'));
        $this->assertSame('event', SocialFeed::topic('Demo day #CLUK-sales #event'));
        $this->assertSame('tip', SocialFeed::topic('#tip: bestel voor 15u'));
        $this->assertNull(SocialFeed::topic('Gewoon nieuws #CLUKsales #newsletter'));
    }
}
