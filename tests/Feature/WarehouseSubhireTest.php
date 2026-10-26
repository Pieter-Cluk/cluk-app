<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Warehouse\Moves;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseSubhireTest extends TestCase
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
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    /** An order where all 6 pieces come from a partner. */
    private function job(): array
    {
        $item = Item::create(['number' => 'LT-K2', 'kind' => 'item', 'name' => 'Ayrton Rivale', 'category' => 'Light', 'serial' => true, 'day_price_cents' => 9000]);
        $c = Company::create(['name' => 'Demo Production BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        $p = Company::create(['name' => 'Demo Partner Rental', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'en']);
        $o = Order::create(['number' => 'R-OC-2026-0200', 'status' => 'approved', 'company_id' => $c->id, 'title' => 'Festival', 'language' => 'en',
            'out_from' => now()->addDay(), 'out_until' => now()->addDays(3), 'use_from' => today()->addDay(), 'use_until' => today()->addDays(2), 'vat_rate_bp' => 2100, 'net_cents' => 1]);
        $line = $o->lines()->create(['kind' => 'item', 'item_id' => $item->id, 'name' => 'Ayrton Rivale', 'qty' => 6, 'amount_cents' => 1, 'position' => 0]);
        $r = SubhireRequest::create(['number' => 'SHQ-2026-0001', 'confirm_number' => 'SHC-2026-0001', 'order_id' => $o->id, 'supplier_id' => $p->id, 'status' => 'accepted',
            'delivery_at' => now()->addHours(5), 'return_at' => now()->addDays(4)]);
        $sl = $r->lines()->create(['order_line_id' => $line->id, 'item_id' => $item->id, 'name' => 'Ayrton Rivale', 'qty' => 6]);

        return [$o, $r, $sl];
    }

    public function test_sub_hire_must_come_in_before_the_order_counts_as_picked(): void
    {
        [$o, $r, $sl] = $this->job();
        $crew = $this->as('warehouse');
        $this->assertFalse(Moves::isPicked($o), 'nothing of ours to pick, but the partner has not delivered');
        $this->actingAs($crew)->get('/w')->assertOk()->assertSee('SUB-HIRE IN')->assertSee('Demo Partner Rental');
        $this->actingAs($crew)->get('/w/pick/'.$o->id)->assertOk()->assertSee('not in yet')->assertSee('must come in before loading');
        $this->actingAs($crew)->post('/w/pick/'.$o->id.'/load')->assertSessionHasErrors();

        // Count 5 of 6, a photo is needed, and a note because one is missing.
        $this->actingAs($crew)->get('/w/subhire/'.$r->id.'/in')->assertOk()->assertSee('not our equipment');
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/in/lines/'.$sl->id, ['delta' => 5]);
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/in/done')->assertSessionHasErrors('photos');
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/in/photo', ['photos' => [UploadedFile::fake()->image('cases.jpg', 800, 600)]]);
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/in/done')->assertSessionHasErrors('note');
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/in/done', ['note' => 'One Rivale missing, partner brings it tomorrow'])->assertRedirect('/w');
        $this->assertNotNull($r->fresh()->received_at);
        $this->assertSame(5, $sl->fresh()->received_qty);
        $this->assertTrue(Moves::isPicked($o));
        $this->actingAs($crew)->get('/w/pick/'.$o->id)->assertSee('5 of 6 in');

        // Going back: count, photo, done.
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/back/lines/'.$sl->id, ['delta' => 99]);
        $this->assertSame(5, $sl->fresh()->back_qty);
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/back/photo', ['photos' => [UploadedFile::fake()->image('back.jpg', 800, 600)]]);
        $this->actingAs($crew)->post('/w/subhire/'.$r->id.'/back/done')->assertRedirect('/w');
        $this->assertNotNull($r->fresh()->handed_back_at);
        $this->assertSame(2, AuditLog::whereIn('action', ['warehouse.subhire_in', 'warehouse.subhire_back'])->count());
        $this->actingAs($crew)->get('/w/subhire/'.$r->id.'/in/photos/0')->assertOk();
    }

    public function test_counted_lines_with_partner_pieces_say_why_the_count_stops(): void
    {
        [$o] = $this->job();
        $cable = Item::create(['number' => 'CB-DMX5', 'kind' => 'item', 'name' => 'DMX cable 5 m', 'serial' => false, 'stock_qty' => 4]);
        // 10 needed: 4 from our stock, 6 from the partner.
        $line = $o->lines()->create(['kind' => 'item', 'item_id' => $cable->id, 'name' => 'DMX cable 5 m', 'qty' => 10, 'own_qty' => 4, 'amount_cents' => 1, 'position' => 1]);
        $r = SubhireRequest::first();
        $r->lines()->create(['order_line_id' => $line->id, 'item_id' => $cable->id, 'name' => 'DMX cable 5 m', 'qty' => 6]);
        // A counted line that comes from the partner only: no buttons, it says so.
        $only = $o->lines()->create(['kind' => 'item', 'item_id' => $cable->id, 'name' => 'DMX cable 10 m', 'qty' => 3, 'own_qty' => 0, 'amount_cents' => 1, 'position' => 2]);
        $r->lines()->create(['order_line_id' => $only->id, 'item_id' => $cable->id, 'name' => 'DMX cable 10 m', 'qty' => 3]);
        $crew = $this->as('warehouse');
        $page = $this->actingAs($crew)->get('/w/pick/'.$o->id)->assertOk()->assertSee('0 / 4')->assertSee('own stock')->assertSee('from partner');
        $this->assertSame(1, substr_count($page->getContent(), 'value="picked_qty"') / 3, 'only the line with own stock has buttons');

        $this->actingAs($crew)->post(route('w.count', [$o, $line]), ['field' => 'picked_qty', 'delta' => 4])->assertSessionMissing('warning');
        $this->assertSame(4, $line->fresh()->picked_qty);
        // "All" again or "+": nothing more from our stock, and the app says where the rest comes from.
        $this->actingAs($crew)->post(route('w.count', [$o, $line]), ['field' => 'picked_qty', 'delta' => 1])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'Demo Partner Rental') && str_contains($m, 'All 4'));
        $this->actingAs($crew)->get('/w/pick/'.$o->id)->assertSee('flash-warn', false);
    }
}
