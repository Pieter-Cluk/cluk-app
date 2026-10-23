<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyRegistry;
use App\Models\Finance\PreInvoice;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Credit\CreditCheck;
use App\Services\Credit\RegistryImport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Customer card › Credit check: register data (KBO), VIES and our own payments. */
class CreditCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $this->owner = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
    }

    private function company(string $name, ?string $vat, array $extra = []): Company
    {
        $vies = $extra['vies_status'] ?? null;
        unset($extra['vies_status']);
        $c = Company::create(array_merge(['name' => $name, 'vat_number' => $vat, 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE', 'account_owner_id' => $this->owner->id], $extra));

        if ($vies) {
            $c->forceFill(['vies_status' => $vies])->save();
        }

        return $c;
    }

    private function extract(array $enterprises, array $numbers, string $snapshot = '2026-10-22'): array
    {
        return ['snapshot' => $snapshot, 'numbers' => $numbers, 'enterprises' => $enterprises, 'codes' => [
            'JuridicalSituation' => ['000' => ['nl' => 'Normale toestand', 'fr' => 'Situation normale'], '050' => ['nl' => 'Faillissement', 'fr' => 'Faillite'], '012' => ['nl' => 'Wettelijke fusie', 'fr' => 'Fusion légale']],
            'JuridicalForm' => ['610' => ['nl' => 'Besloten vennootschap', 'fr' => 'Société à responsabilité limitée']],
        ]];
    }

    public function test_numbers_and_import_with_alert_when_a_customer_turns_red(): void
    {
        $ok = $this->company('Demo Theatre vzw', 'BE0123456789');
        $bad = $this->company('Demo Events BV', 'BE0999999999');
        $gone = $this->company('Old Name BV', 'BE0555555555');
        $this->company('Demo GmbH', 'DE123456789', ['country' => 'DE']);
        $this->assertSame(['0123.456.789', '0555.555.555', '0999.999.999'], RegistryImport::numbers());

        $first = $this->extract([
            '0123.456.789' => ['status' => 'AC', 'situation' => '000', 'form' => '610', 'start' => '01-02-2015', 'name' => 'Demo Theatre', 'address' => 'Karreweg 17, 9870 Zulte'],
            '0999.999.999' => ['status' => 'AC', 'situation' => '000', 'form' => '610', 'start' => '01-02-2015'],
        ], RegistryImport::numbers());
        $file = tempnam(sys_get_temp_dir(), 'kbo');
        file_put_contents($file, json_encode($first));
        $this->assertSame(0, Artisan::call('cluk:kbo-import', ['file' => $file]));
        $this->assertSame('green', CreditCheck::for($ok->fresh())['level']);
        $this->assertSame('Besloten vennootschap', $ok->registry->text('form', 'nl'));
        $this->assertSame('Société à responsabilité limitée', $ok->fresh()->registry->text('form', 'fr'));
        $this->assertSame('orange', CreditCheck::for($gone->fresh())['level']); // not in the register
        Notification::assertNothingSent();

        // A week later: one goes bankrupt → red, audit and an email to the account owner.
        $second = $first;
        $second['enterprises']['0999.999.999']['situation'] = '050';
        RegistryImport::run($second);
        $check = CreditCheck::for($bad->fresh());
        $this->assertSame('red', $check['level']);
        $this->assertStringContainsString('Faillissement', $check['reasons'][0]['text']);
        $this->assertTrue(AuditLog::where('action', 'crm.registry_changed')->where('subject_id', $bad->id)->exists());
        Notification::assertSentTo($this->owner, Confirmation::class, fn ($n) => str_contains($n->subject, 'Demo Events BV'));

        // A merger is unusual but not alarming → orange.
        $second['enterprises']['0123.456.789']['situation'] = '012';
        RegistryImport::run($second);
        $this->assertSame('orange', CreditCheck::for($ok->fresh())['level']);
    }

    public function test_young_company_vies_and_our_own_payments(): void
    {
        $c = $this->company('Startup BV', 'BE0123456789');
        CompanyRegistry::create(['company_id' => $c->id, 'enterprise_number' => '0123.456.789', 'status' => 'AC', 'situation_code' => '000', 'start_date' => today()->subMonths(8), 'snapshot_date' => today()]);
        $this->assertSame('orange', CreditCheck::for($c->fresh())['level']);

        $foreign = $this->company('Demo GmbH', 'DE123456789', ['country' => 'DE', 'vies_status' => 'valid']);
        $this->assertSame('green', CreditCheck::for($foreign)['level']);
        $foreign->forceFill(['vies_status' => 'invalid'])->save();
        $this->assertSame('red', CreditCheck::for($foreign->fresh())['level']);
        $this->assertSame('unknown', CreditCheck::for($this->company('Nobody', null))['level']);

        // Our proformas: 20 days overdue → orange, 75 days → red.
        $nl = $this->company('Demo NL BV', 'NL123456789B01', ['country' => 'NL', 'vies_status' => 'valid']);
        $pf = PreInvoice::create(['number' => 'PF-1', 'source_type' => 'manual', 'source_id' => 1, 'module' => 'sales', 'company_id' => $nl->id, 'description' => 'x', 'lines' => [], 'status' => 'sent', 'net_cents' => 1000, 'vat_cents' => 0, 'gross_cents' => 1000, 'invoice_date' => today()->subDays(50), 'due_date' => today()->subDays(20)]);
        $this->assertSame('orange', CreditCheck::for($nl)['level']);
        $pf->update(['due_date' => today()->subDays(75)]);
        $this->assertSame('red', CreditCheck::for($nl)['level']);
    }

    public function test_the_card_shows_light_reasons_and_register_facts(): void
    {
        $c = $this->company('Demo Events BV', 'BE0999999999');
        CompanyRegistry::create(['company_id' => $c->id, 'enterprise_number' => '0999.999.999', 'status' => 'AC', 'situation_code' => '050', 'situation' => ['nl' => 'Faillissement', 'fr' => 'Faillite'],
            'form' => ['nl' => 'Besloten vennootschap'], 'start_date' => '2015-02-01', 'official_name' => 'Demo Events', 'address' => 'Karreweg 17, 9870 Zulte', 'snapshot_date' => '2026-10-22']);
        $this->actingAs($this->owner)->get('/companies/'.$c->id)->assertOk()
            ->assertSee('Credit check')->assertSee('Prepayment advised')->assertSee('Faillissement')->assertSee('Karreweg 17, 9870 Zulte')
            ->assertSee('kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer=0999999999', false)->assertSee('22/10/2026');
        $other = $this->company('Unknown BV', 'BE0123456789');
        $this->actingAs($this->owner)->get('/companies/'.$other->id)->assertOk()->assertSee('Register data arrives with the next KBO update.');
    }
}
