<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment\Item;
use App\Models\Finance\PreInvoice;
use App\Models\Role;
use App\Models\Storage\StorageCheck;
use App\Models\Storage\StorageContract;
use App\Models\Storage\StoredPiece;
use App\Models\User;
use App\Models\Warehouse\CheckProcedure;
use App\Models\Warehouse\Position;
use App\Services\Warehouse\Procedures;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A customer keeps equipment here: places per day, goods in/out, Tech Checks by CLUK at the procedure's duration, a monthly statement. */
class CustomerStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_storage_from_contract_to_monthly_statement(): void
    {
        $boss = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        $crew = User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id'), 'locale' => 'en']);
        $co = Company::create(['name' => 'Sound Rental NV', 'is_customer' => true, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en']);
        $ours = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'category' => 'Light', 'serial' => true]);
        $pal = Position::updateOrCreate(['code' => 'GA9A1'], ['code' => 'GA9A1', 'floor' => 'G', 'row' => 'A', 'rack' => 9, 'layer' => 'A', 'spot' => 1, 'kind' => 'pallet', 'use' => 'customer', 'active' => true]);
        $rental = Position::updateOrCreate(['code' => 'GA1A1'], ['code' => 'GA1A1', 'floor' => 'G', 'row' => 'A', 'rack' => 1, 'layer' => 'A', 'spot' => 1, 'kind' => 'pallet', 'use' => 'rental', 'active' => true]);
        $proc = Procedures::create(['nl' => 'Moving head', 'en' => 'Moving head'], $boss, [['key' => 'fan', 'title' => ['nl' => 'Ventilator', 'en' => 'Fan'], 'text' => ['nl' => '', 'en' => ''], 'answer' => 'okfail', 'photos' => [], 'choices' => [], 'costs' => []]]);
        $proc->update(['minutes' => 30]);
        Procedures::publish($proc, null, $boss);

        $this->actingAs($boss)->get('/warehouse/storage')->assertOk()->assertSee('€ 1,00');
        $sales = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $this->actingAs($sales)->post('/warehouse/storage', ['company_id' => $co->id, 'start_date' => '2026-11-01'])->assertForbidden();
        $this->actingAs($boss)->post('/warehouse/storage', ['company_id' => $co->id, 'start_date' => '2026-11-01'])->assertRedirect();
        $c = StorageContract::firstOrFail();

        // Places: only customer storage places, one customer each.
        $this->actingAs($boss)->post('/warehouse/storage/'.$c->id.'/places', ['position_id' => $rental->id, 'kind' => 'pallet', 'from' => '2026-11-01'])->assertSessionHasErrors('position_id');
        $this->actingAs($boss)->post('/warehouse/storage/'.$c->id.'/places', ['position_id' => $pal->id, 'kind' => 'pallet', 'from' => '2026-11-01'])->assertSessionHasNoErrors();

        // Their equipment gets our labels; it never joins our fleet.
        $this->actingAs($boss)->post('/warehouse/storage/'.$c->id.'/items', ['name' => 'Robe Spiider (theirs)', 'pieces' => 2, 'serials' => "SN1\nSN2", 'same_as_item_id' => $ours->id, 'check_procedure_id' => $proc->id])->assertSessionHasNoErrors();
        $this->assertSame(['ST-00001', 'ST-00002'], StoredPiece::orderBy('id')->pluck('code')->all());
        $this->assertSame(0, $ours->fresh()->totalQty());

        // Goods in: 2 colli and 1 pallet (handling), both pieces here.
        $this->actingAs($boss)->post('/warehouse/storage/'.$c->id.'/moves', ['kind' => 'in', 'date' => '2026-11-03', 'colis' => 2, 'pallets' => 1, 'pieces' => StoredPiece::pluck('id')->all(), 'signed_name' => 'Tom'])->assertSessionHasNoErrors();
        $this->assertSame(['in', 'in'], StoredPiece::pluck('status')->all());

        // The phone: scanning the label opens its Tech Check; by CLUK, billed at the procedure's 30 minutes.
        $piece = StoredPiece::firstOrFail();
        $this->actingAs($crew)->post('/w/scan', ['code' => 'st-00001'])->assertRedirect('/w/stored/'.$piece->id);
        $this->actingAs($crew)->get('/w/stored/'.$piece->id)->assertOk()->assertSee('Storage of Sound Rental NV')->assertSee('Complete view of the fixture');
        $this->actingAs($crew)->post('/w/stored/'.$piece->id.'/step', ['dirt' => 1, 'broken' => 'no'])->assertRedirect();
        $this->actingAs($crew)->post('/w/stored/'.$piece->id.'/step', ['answer' => 'ok'])->assertRedirect();
        $this->actingAs($crew)->get('/w/stored/'.$piece->id)->assertSee('Who did the check?');
        $this->actingAs($crew)->post('/w/stored/'.$piece->id.'/finish', ['done_by' => 'cluk', 'minutes' => 30])->assertRedirect('/w/tech');
        $this->assertSame(['ok', 30, 'cluk'], [StorageCheck::first()->result, StorageCheck::first()->minutes, StorageCheck::first()->done_by]);
        // The second one by their own staff: not billed.
        $two = StoredPiece::where('code', 'ST-00002')->first();
        $this->actingAs($crew)->get('/w/stored/'.$two->id);
        $this->actingAs($crew)->post('/w/stored/'.$two->id.'/step', ['dirt' => 1, 'broken' => 'no']);
        $this->actingAs($crew)->post('/w/stored/'.$two->id.'/step', ['answer' => 'ok']);
        $this->actingAs($crew)->post('/w/stored/'.$two->id.'/finish', ['done_by' => 'customer']);

        $this->actingAs($boss)->get('/warehouse/storage/'.$c->id)->assertOk()->assertSee('ST-00001')->assertSee('By CLUK · 30 min')->assertSee('By their staff');

        // November: 30 days pallet place (€ 30), handling 2 × € 2 + € 8, one check 30 min × € 60/h.
        $this->actingAs($boss)->post('/warehouse/storage/'.$c->id.'/statement', ['month' => '2026-11'])->assertSessionHasNoErrors();
        $pf = PreInvoice::where('source_type', 'storage')->firstOrFail();
        $this->assertSame([3000 + 1200 + 3000, 'warehouse', 'check'], [$pf->net_cents, $pf->module, $pf->status]);
        $this->assertSame('Pallet place GA9A1 · 01/11 – 30/11 (30 days)', $pf->lines[0]['d']);
        $this->assertStringContainsString('Tech Check ST-00001', $pf->lines[2]['d']);
        $this->actingAs($boss)->post('/warehouse/storage/'.$c->id.'/statement', ['month' => '2026-11'])->assertSessionHasErrors('month'); // once per month

        // Pages in every language.
        foreach (array_keys(config('cluk.locales')) as $l) {
            $boss->forceFill(['locale' => $l])->save();
            foreach (['/warehouse/storage', '/warehouse/storage/'.$c->id, '/warehouse/tech-check'] as $url) {
                $html = $this->actingAs($boss->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\bstorage\.[a-z_]+\.[a-z_]+\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)), "{$url} ({$l})");
            }
        }
    }
}
