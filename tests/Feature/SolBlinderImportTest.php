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

        // The accessories: counted, 120 each, in their subcategories.
        $acc = require database_path('migrations/2026_10_23_001500_add_sol_i_accessories.php');
        $acc->up();
        $barndoor = Item::where('number', 'LIGACCELASOLIBARNDOOR')->firstOrFail();
        $this->assertSame([false, 120, 314, 10450, 'Lighting accessories'], [$barndoor->serial, $barndoor->stock_qty, $barndoor->day_price_cents, $barndoor->catalog_price_cents, $barndoor->subcategory]);
        $this->assertSame('Truss accessories', Item::where('number', 'RIGTACTAFQUICKCLAMP')->value('subcategory'));
        $this->assertSame(7, Item::where('number', 'like', '%SOLI%')->orWhere('number', 'RIGTACTAFQUICKCLAMP')->count() - 1);
        Item::where('number', 'LIGACCELASOLIOMEGA')->update(['stock_qty' => 100]);
        $acc->up(); // a second run keeps the stock
        $this->assertSame(100, Item::where('number', 'LIGACCELASOLIOMEGA')->value('stock_qty'));

        // Safety and power cables: pieces with a label each, none yet, not in the quote search until priced.
        (require database_path('migrations/2026_10_23_001700_add_safety_and_power_cables.php'))->up();
        $ce = Item::where('number', 'POWCABTRUECONCEE')->firstOrFail();
        $this->assertSame([true, 0, false, 'Power cables & kits', 'Power cable Truecon – CEE 3-pole 1.5 m'], [$ce->serial, $ce->assets()->count(), $ce->in_search, $ce->subcategory, $ce->name]);
        $this->assertSame('Rigging hardware & load cells', Item::where('number', 'RIGHARSAFETYCABLE')->value('subcategory'));
    }
}
