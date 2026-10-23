<?php

namespace Tests\Feature;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Elation Sol I Blinders from the HireHop asset list are in dry-hire, each with a code for its QR label. */
class SolBlinderImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_blinders_and_their_pieces_are_there(): void
    {
        app()->instance('test.sol_blinders', true);
        $migration = require database_path('migrations/2026_10_23_001200_add_elation_sol_i_blinders.php');
        $migration->up();
        $migration->up(); // a second run adds nothing
        $item = Item::where('number', 'LIGBATELASOLIBLINDER')->firstOrFail();
        $this->assertSame('Elation Sol I Blinder', $item->name);
        $this->assertSame(1900, $item->day_price_cents);
        $this->assertSame(89000, $item->catalog_price_cents);
        $this->assertSame('CN', $item->origin);
        $this->assertSame('Battens, strobes & blinders', $item->subcategory);
        $this->assertTrue($item->serial);
        $this->assertSame(120, $item->assets()->count());
        $this->assertSame(120, $item->assets()->distinct()->count('code'));
        $a = Asset::where('serial_number', '273030436')->firstOrFail();
        $this->assertSame('HireHop barcode 810008264874 · last PAT test 12/08/2026', $a->note);
        $this->assertSame(Asset::AVAILABLE, $a->status);
        $this->assertNull($a->label_printed_at);
    }
}
