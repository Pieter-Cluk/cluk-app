<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Move;
use App\Models\Warehouse\Position;
use App\Services\Warehouse\QrSheets;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A4 QR sheets for the trucks and the packing zone, and the task codes on them. */
class QrSheetsTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_sheets_are_pdfs_with_tasks_zones_and_most_used_places(): void
    {
        $this->seed(RoleSeeder::class);
        $crew = $this->as('warehouse');
        $busy = Position::where('kind', 'pallet')->where('active', true)->orderByDesc('code')->first();
        foreach (range(1, 5) as $i) {
            Move::create(['to_code' => $busy->code, 'kind' => 'move', 'user_id' => $crew->id]);
        }

        $truck = collect(QrSheets::cells('truck'));
        $this->assertSame(['SCAN', 'DELIVERY', 'DONE', 'INBOUND', 'DISPATCH', 'UNKNOWN'], $truck->take(6)->pluck('code')->all());
        $this->assertSame($busy->code, $truck[6]['code']); // most used first
        $this->assertSame(12, $truck->count());
        $this->assertSame(strtoupper($truck[0]['url']), $truck[0]['url']); // upper case: fewer, bigger squares
        $this->assertStringEndsWith('/W/DO/SCAN', $truck[0]['url']);
        $this->assertStringEndsWith('/L/'.$busy->code, $truck[6]['url']);
        $this->assertSame(['SCAN', 'ADD', 'REMOVE', 'DONE', 'AFRIT6', 'DISPATCH'], collect(QrSheets::cells('packing'))->pluck('code')->all());

        foreach (QrSheets::SHEETS as $s) {
            $r = $this->actingAs($crew)->get(route('warehouse.sheet', $s))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
        $this->actingAs($crew)->get(route('warehouse.labels'))->assertOk()->assertSee('QR sheets for trucks and the packing zone');
    }

    public function test_task_codes_open_screens_and_set_the_scanner(): void
    {
        $this->seed(RoleSeeder::class);
        $crew = $this->as('warehouse');
        $this->actingAs($crew)->get('/W/DO/DELIVERY')->assertRedirect(route('w.delivery'));
        $this->actingAs($crew)->get('/w/do/scan')->assertRedirect(route('w.scan'));
        $this->actingAs($crew)->withSession(['w.carrier' => 5])->get('/W/DO/REMOVE')->assertRedirect(route('w.scan'))->assertSessionHas('w.carrier_mode', 'remove');
        $this->actingAs($crew)->withSession(['w.carrier' => 5, 'w.carrier_mode' => 'remove'])->get('/W/DO/DONE')->assertSessionMissing('w.carrier')->assertSessionMissing('w.carrier_mode');
        $this->actingAs($crew)->get('/W/DO/NOPE')->assertNotFound();
        // The same code scanned in the scan screen.
        $this->actingAs($crew)->post(route('w.scan.post'), ['code' => 'HTTPS://APP.CLUK.LIVE/W/DO/ADD'])->assertRedirect(route('w.scan'))->assertSessionHas('w.carrier_mode', 'add');
        // Look-only users can open screens but not change what the scanner does.
        $this->actingAs($this->as('read-only'))->get('/W/DO/ADD')->assertForbidden();
    }
}
