<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyRegistry;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Credit\CreditCheck;
use App\Services\Credit\KboLookup;
use App\Services\Credit\RegistryImport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Live lookups in the KBO Public Search web service (SOAP). */
class KboLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        config(['services.kbo_ws.user' => 'cluk-test', 'services.kbo_ws.password' => 'secret']);
    }

    private static function reply(string $situation = '000', string $sitNl = 'Normale toestand', string $status = 'AC'): string
    {
        return <<<XML
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Header>
<ReplyContext xmlns:ns2="http://economie.fgov.be/kbopub/webservices/v1/datamodel" xmlns="http://economie.fgov.be/kbopub/webservices/v1/messages">
<RequestId>x</RequestId><ReplyId>y</ReplyId><ReplyStatus><Code>00001</Code><Description>The operation completed successfully</Description></ReplyStatus>
<AccountBalance>2998</AccountBalance><SnapshotDate>2026-10-22</SnapshotDate></ReplyContext></soap:Header>
<soap:Body><ReadEnterpriseReply xmlns:ns2="http://economie.fgov.be/kbopub/webservices/v1/datamodel" xmlns="http://economie.fgov.be/kbopub/webservices/v1/messages"><ns2:Enterprise>
<ns2:Number>1010948054</ns2:Number>
<ns2:JuridicalSituation><ns2:Code>{$situation}</ns2:Code>
<ns2:Description><ns2:Value>Situation x</ns2:Value><ns2:Language>fr</ns2:Language></ns2:Description>
<ns2:Description><ns2:Value>{$sitNl}</ns2:Value><ns2:Language>nl</ns2:Language></ns2:Description>
<ns2:Status><ns2:Code>{$status}</ns2:Code></ns2:Status></ns2:JuridicalSituation>
<ns2:Period><ns2:Begin>2014-09-01</ns2:Begin></ns2:Period>
<ns2:JuridicalForm><ns2:Code>610</ns2:Code><ns2:Description><ns2:Value>Société à responsabilité limitée</ns2:Value><ns2:Language>fr</ns2:Language></ns2:Description>
<ns2:Description><ns2:Value>Besloten Vennootschap</ns2:Value><ns2:Language>nl</ns2:Language></ns2:Description></ns2:JuridicalForm>
<ns2:Denomination><ns2:Code>001</ns2:Code><ns2:Description><ns2:Value>CLUK</ns2:Value><ns2:Language>nl</ns2:Language></ns2:Description></ns2:Denomination>
<ns2:Address><ns2:Street><ns2:Description><ns2:Value>Nachtegaalstraat</ns2:Value><ns2:Language>nl</ns2:Language></ns2:Description></ns2:Street>
<ns2:HouseNumber>12</ns2:HouseNumber><ns2:Zipcode>9870</ns2:Zipcode><ns2:Municipality><ns2:Description><ns2:Value>Zulte</ns2:Value><ns2:Language>nl</ns2:Language></ns2:Description></ns2:Municipality>
<ns2:TypeOfAddress><ns2:Code>REGO</ns2:Code></ns2:TypeOfAddress></ns2:Address>
</ns2:Enterprise></ReadEnterpriseReply></soap:Body></soap:Envelope>
XML;
    }

    private function company(): Company
    {
        $owner = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);

        return Company::create(['name' => 'CLUK', 'vat_number' => 'BE1010948054', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl', 'country' => 'BE', 'account_owner_id' => $owner->id]);
    }

    public function test_reply_is_read_and_request_is_signed(): void
    {
        $r = KboLookup::parse(self::reply());
        $this->assertTrue($r['found']);
        $this->assertSame('2026-10-22', $r['snapshot']);
        $this->assertSame(['AC', '000', '610', '2014-09-01', 'CLUK', 'Nachtegaalstraat 12, 9870 Zulte'],
            [$r['entry']['status'], $r['entry']['situation'], $r['entry']['form'], $r['entry']['start_date'], $r['entry']['name'], $r['entry']['address']]);
        $this->assertSame('Besloten Vennootschap', $r['entry']['form_text']['nl']);

        $gone = '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Header><ReplyContext xmlns="http://economie.fgov.be/kbopub/webservices/v1/messages"><ReplyStatus><Code>00010</Code></ReplyStatus></ReplyContext></soap:Header><soap:Body><ReadEnterpriseReply xmlns="http://economie.fgov.be/kbopub/webservices/v1/messages"/></soap:Body></soap:Envelope>';
        $this->assertFalse(KboLookup::parse($gone)['found']);

        Http::fake(['kbopub.economie.fgov.be/*' => Http::response(self::reply())]);
        $this->assertTrue(KboLookup::read('1010.948.054')['found']);
        Http::assertSent(function (Request $req) {
            $b = $req->body();

            return str_contains($b, '<dat:EnterpriseNumber>1010948054</dat:EnterpriseNumber>') && str_contains($b, '#PasswordDigest')
                && str_contains($b, '<wsse:Username>cluk-test</wsse:Username>') && ! str_contains($b, 'secret');
        });
    }

    public function test_card_looks_up_live_and_a_bankruptcy_turns_red(): void
    {
        $c = $this->company();
        $admin = User::where('email', '!=', '')->first();
        Http::fake(['kbopub.economie.fgov.be/*' => Http::sequence()->push(self::reply())->push(self::reply('050', 'Faillissement'))]);

        $this->actingAs($admin)->get('/companies/'.$c->id)->assertOk()->assertSee('Nachtegaalstraat 12, 9870 Zulte')->assertSee('No warning signs')->assertSee('Check now in the KBO');
        $this->assertSame('kbo-ws', $c->registry()->first()->source);
        // Fresh data: opening the card again does not look it up again.
        $this->actingAs($admin)->get('/companies/'.$c->id)->assertOk();
        Http::assertSentCount(1);

        $this->actingAs($admin)->post('/companies/'.$c->id.'/credit/refresh')->assertRedirect();
        $this->assertSame('red', CreditCheck::for($c->fresh())['level']);
        $this->assertTrue(AuditLog::where('action', 'crm.registry_changed')->exists());
        Notification::assertSentTo($c->accountOwner, Confirmation::class);
    }

    public function test_background_refreshes_the_oldest_first_and_stops_when_the_service_fails(): void
    {
        $a = $this->company();
        $b = Company::create(['name' => 'Other', 'vat_number' => 'BE0123456749', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl', 'country' => 'BE']);
        CompanyRegistry::create(['company_id' => $b->id, 'enterprise_number' => '0123.456.749', 'status' => 'AC', 'situation_code' => '000', 'checked_at' => now()->subDays(2), 'snapshot_date' => today()]);
        Http::fake(['kbopub.economie.fgov.be/*' => Http::sequence()->push(self::reply())
            ->push('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultstring>Quota Exceeded!</faultstring></soap:Fault></soap:Body></soap:Envelope>', 500)]);
        $this->assertSame(1, RegistryImport::refreshStale(5)); // only the one without (recent) data
        $this->assertNotNull($a->registry()->first());

        $a->registry()->update(['checked_at' => now()->subDays(30)]);
        $this->assertSame(0, RegistryImport::refreshStale(5));
    }
}
