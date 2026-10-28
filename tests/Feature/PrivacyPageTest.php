<?php

namespace Tests\Feature;

use App\Models\CompanyDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_policy_shows_in_every_language_with_the_company_details(): void
    {
        CompanyDetail::current()->update(['legal_name' => 'CLUK', 'legal_form' => 'BV', 'vat_number' => 'BE1010948054', 'email' => 'info@cluk.live', 'street' => 'Karreweg 17B', 'postcode' => '9870', 'city' => 'Zulte']);
        foreach (['en' => 'Privacy policy', 'nl' => 'Privacybeleid', 'fr' => 'Politique de confidentialité', 'es' => 'Política de privacidad'] as $l => $title) {
            $this->get(route('site.privacy', ['lang' => $l]))->assertOk()->assertSee($title)->assertSee('CLUK BV, Karreweg 17B, 9870 Zulte')->assertSee('info@cluk.live')->assertDontSee('privacy.');
        }
        $this->get(route('site.home'))->assertOk()->assertSee(route('site.privacy'));
        $this->get(route('site.account.register'))->assertOk()->assertSee(route('site.privacy'));
    }
}
