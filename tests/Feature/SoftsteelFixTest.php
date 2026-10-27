<?php

namespace Tests\Feature;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftsteelFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_softsteels_become_counted_with_20_in_stock(): void
    {
        $item = Item::create(['number' => 'RIGHARSOFSTEEL2T1M', 'kind' => 'item', 'name' => 'Softsteel Black 2T 1m', 'serial' => true]);
        $other = Item::create(['number' => 'LT-OTHER', 'kind' => 'item', 'name' => 'Other', 'serial' => true]);
        foreach (range(1, 20) as $i) {
            $item->assets()->create(['code' => 'RIGH-'.$i, 'status' => $i < 19 ? Asset::CHECK : Asset::AVAILABLE, 'location' => $i < 19 ? 'INBOUND' : 'GA2C1']);
        }
        $other->assets()->create(['code' => 'LT-1', 'status' => Asset::AVAILABLE]);

        (require database_path('migrations/2026_10_27_000100_softsteels_counted.php'))->up();

        $item->refresh();
        $this->assertFalse((bool) $item->serial);
        $this->assertSame(20, $item->stock_qty);
        $this->assertSame(0, $item->assets()->count());
        $this->assertSame(1, Asset::count());
    }
}
