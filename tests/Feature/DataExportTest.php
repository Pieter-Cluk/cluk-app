<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Product\Subcategory;
use App\Models\ProductSales\SalesItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Data\Exporter;
use App\Support\SheetReader;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Settings › Import & export: the export has the import's columns, so it can be imported again unchanged. */
class DataExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en', 'first_name' => 'Pieter', 'last_name' => 'Test']);
    }

    private function data(User $owner): void
    {
        $co = Company::create(['name' => 'Demo Theatre vzw', 'vat_number' => 'BE0123456789', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl',
            'city' => 'Zulte', 'country' => 'BE', 'email' => 'info@theatre.example', 'customer_payment_days' => 30, 'account_owner_id' => $owner->id]);
        $sup = Company::create(['name' => 'Demo Audio Distribution BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE']);
        $jo = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@theatre.example', 'language' => 'nl', 'newsletter' => true]);
        $co->contacts()->attach($jo->id, ['job_title' => 'Technical director']);
        $ks = new Item(['number' => 'AUDSUBLA KS28', 'kind' => 'item', 'name' => 'L-Acoustics KS28', 'brand' => 'L-Acoustics', 'type' => 'KS28', 'unit' => 'pcs', 'serial' => true, 'day_price_cents' => 9500, 'active' => true, 'weight_kg' => 79]);
        $ks->placeIn(Subcategory::findOrFail(self::sub('SUB', 'AUD')));
        $ks->save();
        $ks->assets()->create(['code' => 'KS28-001', 'serial_number' => 'SN-1001', 'status' => 'available']);
        $ks->assets()->create(['code' => 'KS28-002', 'serial_number' => 'SN-1002', 'status' => 'available']);
        $mic = SalesItem::create(['number' => 'S-100001', 'brand' => 'Shure', 'type' => 'SM58', 'manufacturer_code' => 'SM58-LCE', 'unit' => 'piece', 'list_price_cents' => 11900, 'stock_qty' => 6, 'active' => true]);
        $mic->suppliers()->attach($sup->id, ['discount_bp' => 3500, 'lead_days' => 3, 'preferred' => true, 'supplier_ref' => 'DAD-778812']);
    }

    public function test_export_has_the_import_columns_and_imports_back_unchanged(): void
    {
        $admin = $this->as('admin');
        $this->data($admin);
        $this->actingAs($admin)->get('/settings/import')->assertOk()->assertSee('Import &amp; export', false)->assertSee('Download (.xlsx)');

        $res = $this->actingAs($admin)->post('/settings/import/export', ['kinds' => Exporter::KINDS])->assertOk();
        $file = $res->baseResponse->getFile()->getPathname();
        $sheets = SheetReader::read($file, 'xlsx');
        $this->assertSame(['Companies', 'Contacts', 'Equipment', 'Sales items'], array_keys($sheets));
        $this->assertContains('Demo Theatre vzw', array_column($sheets['Companies'], 0));
        $eq = $sheets['Equipment'];
        $ks = array_values(array_filter($eq, fn ($r) => $r[0] === 'AUDSUBLA KS28'));
        $this->assertCount(2, $ks); // one row per piece
        $this->assertSame(['SN-1001', 'SN-1002'], array_column($ks, array_search('Serial number', $eq[0])));
        $s = $sheets['Sales items'];
        $this->assertSame('SM58-LCE', $s[1][array_search('Manufacturer code', $s[0])]);
        $this->assertSame('DAD-778812', $s[1][array_search('Supplier 1 article no.', $s[0])]);
        $this->assertSame('35', (string) $s[1][array_search('Supplier 1 discount %', $s[0])]);
        $this->assertTrue(AuditLog::where('action', 'data.exported')->exists());

        // Import the same file: nothing new, nothing changed.
        $copy = UploadedFile::fake()->createWithContent('CLUK_data.xlsx', file_get_contents($file));
        $page = $this->actingAs($admin)->post('/settings/import/preview', ['file' => $copy, 'kind' => 'auto'])->assertOk();
        foreach ($page->viewData('reports') as $kind => $r) {
            $this->assertSame(0, $r['created'], $kind.': '.json_encode($r['messages']));
            $this->assertSame([], array_values(array_filter($r['messages'], fn ($m) => $m['level'] === 'error')), $kind);
        }
    }

    public function test_purchase_discounts_only_for_those_who_see_margins(): void
    {
        $this->data($this->as('admin'));
        $manager = $this->as('sales');
        $this->assertFalse($manager->hasRight('finance.see_margins'));
        $rows = (new Exporter($manager))->rows('salesitems');
        $this->assertNull($rows[1][array_search('Supplier 1 discount %', $rows[0])]);
        $this->assertNull($rows[1][array_search('Purchase price (calculated)', $rows[0])]);
        $this->assertSame('DAD-778812', $rows[1][array_search('Supplier 1 article no.', $rows[0])]);
    }

    public function test_export_needs_access_to_the_import_page(): void
    {
        $this->actingAs($this->as('sales'))->post('/settings/import/export', ['kinds' => ['contacts']])->assertForbidden();
    }
}
