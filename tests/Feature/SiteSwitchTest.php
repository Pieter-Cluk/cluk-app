<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cluk Factory and Afrit 6 have a switch to the other CLUK sites next to the languages. */
class SiteSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_and_afrit6_link_to_the_other_sites(): void
    {
        $book = $this->get('/book?lang=nl')->assertOk()->assertSee('Onze sites')->assertSee(route('site.dryhire', ['lang' => 'nl']), false)
            ->assertSee(route('afrit6.login'), false)->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s*><b>Cluk Factory/', $book);
        $a6 = $this->get('/afrit6')->assertOk()->assertSee('class="sw"', false)->assertSee(route('factory.book', ['lang' => app()->getLocale()]), false)->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s*><b>Afrit 6/', $a6);
    }
}
