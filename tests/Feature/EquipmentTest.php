<?php

namespace Tests\Feature;

use App\Imports\EquipmentImport;
use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function spiider(): Item
    {
        return Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => true, 'day_price_cents' => 6500, 'catalog_price_cents' => 985000, 'weight_kg' => 13.5]);
    }

    public function test_only_the_known_items_are_in_the_database(): void
    {
        $this->assertSame(22, Item::where('kind', 'service')->count());
        // The four audio items Pieter gave to test sub-hire, without pieces yet.
        $this->assertSame(['AUDACCK&MM20POLE', 'AUDACCK&MSTAND1', 'AUDSPEQSCKLA12', 'AUDSPEQSCKLA181'], Item::where('kind', 'item')->orderBy('number')->pluck('number')->all());
        $this->assertSame(3000, Item::where('number', 'AUDSPEQSCKLA181')->value('day_price_cents'));
        $this->assertEquals(47.2, Item::where('number', 'AUDSPEQSCKLA181')->value('weight_kg'));
        $this->assertStringContainsString('1,000 W', Item::where('number', 'AUDSPEQSCKLA12')->first()->description('en'));
        $this->assertSame(35000, Item::where('number', 'A6-DESK-MONTH')->value('day_price_cents'));
        $this->assertSame(1250, Item::where('number', 'A6-ROOM-HOUR')->value('day_price_cents'));
        $this->assertSame(160, Item::where('number', 'WH-PACK-COLI')->value('day_price_cents'));
        $this->assertSame(300000, Item::where('number', 'CF-L')->value('day_price_cents'));
        $this->assertSame(0, Asset::count());
    }

    public function test_pages_work_in_every_language_without_missing_texts(): void
    {
        $item = $this->spiider();
        $item->assets()->create(['code' => 'LT-0001', 'serial_number' => 'S1']);
        $admin = $this->as('admin');
        $pattern = '/\b(equipment|nav|users)\.[a-z_]+(\.[a-z_]+)*\b/';

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/dry-hire', '/dry-hire?kind=service', '/dry-hire/items/'.$item->id, '/dry-hire/items/new', '/dry-hire/items/'.$item->id.'/edit', '/dry-hire/assets/'.Asset::first()->id, '/dry-hire/labels?item='.$item->id] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression($pattern, strip_tags($html), "{$url} ({$locale})");
            }
        }
    }

    public function test_view_only_people_cannot_change_equipment(): void
    {
        $item = $this->spiider();
        $sales = $this->as('read-only'); // dry-hire level 1

        $this->actingAs($sales)->get('/dry-hire')->assertOk()->assertDontSee(route('equipment.create'));
        $this->actingAs($sales)->get('/dry-hire/items/'.$item->id)->assertOk();
        $this->actingAs($sales)->get('/dry-hire/items/new')->assertForbidden();
        $this->actingAs($sales)->post('/dry-hire/items/'.$item->id.'/assets', ['count' => 2])->assertForbidden();
        $this->actingAs($this->as('workspace-host'))->get('/dry-hire')->assertForbidden(); // no dry-hire access
    }

    public function test_an_item_is_created_and_changes_are_logged(): void
    {
        $am = $this->as('asset-manager'); // dry-hire level 3
        $this->actingAs($am)->post('/dry-hire/items', [
            'number' => 'lt-megap', 'kind' => 'item', 'name' => 'Robe MegaPointe', 'category' => 'Light', 'unit' => 'pcs',
            'serial' => 1, 'day_price' => '85', 'catalog_price' => '14200', 'desc' => ['en' => 'Beam, spot and wash', 'nl' => 'Beam, spot en wash'],
            'in_search' => 1, 'active' => 1,
        ])->assertRedirect();

        $item = Item::where('number', 'LT-MEGAP')->firstOrFail();
        $this->assertSame([8500, 1420000, true], [$item->day_price_cents, $item->catalog_price_cents, $item->serial]);
        $this->assertSame('Beam, spot en wash', $item->description('nl'));
        $this->assertSame('Beam, spot and wash', $item->description('fr'), 'falls back to English');

        $this->actingAs($am)->put('/dry-hire/items/'.$item->id, ['number' => 'LT-MEGAP', 'kind' => 'item', 'name' => 'Robe MegaPointe', 'unit' => 'pcs', 'serial' => 1, 'day_price' => '90', 'active' => 1])->assertRedirect();
        $this->assertSame(9000, $item->fresh()->day_price_cents);
        $this->assertTrue(AuditLog::where('action', 'equipment.item_updated')->exists());

        $this->actingAs($am)->post('/dry-hire/items', ['number' => 'LT-MEGAP', 'kind' => 'item', 'name' => 'x', 'unit' => 'pcs'])->assertSessionHasErrors('number');
    }

    public function test_pieces_get_codes_serials_and_labels(): void
    {
        $item = $this->spiider();
        $am = $this->as('asset-manager');

        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/assets', ['serials' => "3021-0142\n3021-0143\n\n", 'count' => 1, 'purchase_price' => '8900'])
            ->assertRedirect()->assertSessionHas('print', ['LT-0001', 'LT-0002']);
        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/assets', ['count' => 3]);
        $this->assertSame(['LT-0001', 'LT-0002', 'LT-0003', 'LT-0004', 'LT-0005'], $item->assets()->pluck('code')->all());
        $this->assertSame(890000, Asset::where('code', 'LT-0001')->value('purchase_price_cents'), 'asset manager may see money');
        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/assets', ['serials' => '3021-0142'])->assertSessionHasErrors('serials');

        $this->assertSame(5, $item->fresh()->totalQty());
        $html = $this->actingAs($am)->get('/dry-hire/labels?item='.$item->id)->assertOk()->getContent();
        $this->assertSame(5, substr_count($html, '<svg'));
        $this->assertStringContainsString('size: 25mm 25mm', $html);
        $this->assertNotNull(Asset::first()->label_printed_at);

        // Scanning a label opens the asset card.
        $this->actingAs($am)->get('/a/lt-0002')->assertRedirect(route('equipment.assets.show', Asset::where('code', 'LT-0002')->first()));
    }

    public function test_money_fields_need_the_margins_right(): void
    {
        $item = $this->spiider();
        $crew = $this->as('warehouse'); // dry-hire level 2, no margins right
        $this->actingAs($crew)->post('/dry-hire/items/'.$item->id.'/assets', ['count' => 1, 'purchase_price' => '8900', 'supplier' => 'X']);
        $a = Asset::firstOrFail();

        $this->assertNull($a->purchase_price_cents);
        $this->actingAs($crew)->get('/dry-hire/assets/'.$a->id)->assertOk()->assertDontSee(__('equipment.book_value'));
        $this->actingAs($this->as('admin'))->get('/dry-hire/assets/'.$a->id)->assertSee(__('equipment.book_value'));
    }

    public function test_retiring_needs_the_right_and_removes_the_piece_from_stock(): void
    {
        $item = $this->spiider();
        $a = $item->assets()->create(['code' => 'LT-0001']);
        $item->assets()->create(['code' => 'LT-0002']);

        $this->actingAs($this->as('sales'))->post('/dry-hire/assets/'.$a->id.'/retire', ['reason' => 'sold'])->assertForbidden();
        $this->actingAs($this->as('manager'))->post('/dry-hire/assets/'.$a->id.'/retire', ['reason' => 'lost'])->assertRedirect();

        $this->assertSame(['retired', 'lost'], [$a->fresh()->status, $a->fresh()->retired_reason]);
        $this->assertSame(1, $item->fresh()->totalQty());
        $this->assertTrue(AuditLog::where('action', 'equipment.asset_retired')->exists());
    }

    public function test_kits_and_package_contents(): void
    {
        $am = $this->as('asset-manager');
        $kara = Item::create(['number' => 'SD-KARA2', 'name' => 'Kara II', 'serial' => true]);
        $cable = Item::create(['number' => 'AC-PT1-03', 'name' => 'powerCON 3 m', 'serial' => false, 'stock_qty' => 25]);
        $pkg = Item::create(['number' => 'PK-KARA-WE', 'kind' => 'package', 'name' => 'Kara weekend', 'serial' => false]);
        foreach (range(1, 5) as $i) {
            $kara->assets()->create(['code' => 'SD-000'.$i]);
        }

        $this->actingAs($am)->post('/dry-hire/items/'.$pkg->id.'/components', ['child_id' => $kara->id, 'qty' => 2])->assertSessionHasNoErrors();
        $this->actingAs($am)->post('/dry-hire/items/'.$pkg->id.'/components', ['child_id' => $cable->id, 'qty' => 4])->assertSessionHasNoErrors();
        $this->actingAs($am)->post('/dry-hire/items/'.$kara->id.'/components', ['child_id' => $cable->id, 'qty' => 1])->assertSessionHasNoErrors();
        $this->actingAs($am)->post('/dry-hire/items/'.$cable->id.'/components', ['child_id' => $pkg->id, 'qty' => 1])->assertSessionHasErrors('child_id');

        $pkg = $pkg->fresh();
        $this->assertSame('content', $pkg->contents->first()->pivot->role);
        $this->assertSame(2, $pkg->availableQty(), 'min(5 Kara / 2, 25 cables / 4)');
        $this->assertSame('kit', $kara->fresh()->kit->first()->pivot->role);
    }

    public function test_photo_upload_and_display(): void
    {
        $item = $this->spiider();
        $am = $this->as('asset-manager');

        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/photos', ['photos' => [UploadedFile::fake()->image('spiider.jpg', 600, 600)]])->assertRedirect();
        $this->assertNotNull($item->fresh()->photo_path);
        $this->actingAs($this->as('read-only'))->get('/dry-hire/items/'.$item->id.'/photo')->assertOk();
    }

    public function test_the_import_reads_the_template_rows(): void
    {
        $rows = [
            ['Itemnumber', 'Category', 'Sub-Category', 'Unit', 'Brand', 'Type', 'Description', 'Catalog Price', 'Country of Origin', 'HS-Code', 'Weight (kg)', 'Width (m)', 'Length (m)', 'Height (m)', 'Volume (m³)', 'Serial number', 'Quantity', 'Rental price per day'],
            ['LT-SPIIDER', 'Light', 'Moving head · wash', 'pcs', 'Robe', 'Spiider', 'LED wash beam', '9850', 'CZ', '9405.42.00', '13,5', '0.31', '0.39', '0.51', '=IF(COUNT(L2:N2)=3,ROUND(L2*M2*N2,4),"")', '3021-0142', null, '65'],
            ['LT-SPIIDER', 'Light', 'Moving head · wash', 'pcs', 'Robe', 'Spiider', 'LED wash beam', '9850', 'CZ', '9405.42.00', '13,5', '0.31', '0.39', '0.51', null, '3021-0143', null, '65'],
            ['AC-SC-060', 'Accessories', 'Safety cable', 'pcs', 'Doughty', 'Safety 60 cm', 'Safety cable 60 cm', '18', 'GB', null, '0.2', null, null, null, null, null, '42', '1'],
            ['', 'Light', null, null, null, null, null, null, null, null, null, null, null, null, null, null, '3', null],
        ];
        $report = (new EquipmentImport)->run($rows);

        $spiider = Item::where('number', 'LT-SPIIDER')->firstOrFail();
        $this->assertSame(['Robe Spiider', 6500, 985000, 13.5, 'CZ', true], [$spiider->name, $spiider->day_price_cents, $spiider->catalog_price_cents, $spiider->weight_kg, $spiider->origin, $spiider->serial]);
        $this->assertSame(['3021-0142', '3021-0143'], $spiider->assets()->pluck('serial_number')->all());
        $this->assertSame(['LT-0001', 'LT-0002'], $spiider->assets()->pluck('code')->all());
        $safety = Item::where('number', 'AC-SC-060')->firstOrFail();
        $this->assertSame([false, 42], [$safety->serial, $safety->stock_qty]);
        $this->assertSame(1, collect($report['messages'])->where('level', 'error')->count());

        // Importing the same file again does not double anything.
        (new EquipmentImport)->run($rows);
        $this->assertSame(2, $spiider->assets()->count());
    }
}
