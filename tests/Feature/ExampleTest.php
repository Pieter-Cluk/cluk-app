<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_sends_guests_to_the_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_login_page_shows_the_microsoft_button(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in with Microsoft');
    }
}
