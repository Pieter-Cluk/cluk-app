<?php

namespace Tests\Feature;

use App\Models\ProductSales\SalesItem;
use App\Services\Website\SalesCatalogue;
use Tests\TestCase;

class WebsiteSalesSizeTest extends TestCase
{
    private function item(array $a): SalesItem
    {
        return (new SalesItem)->forceFill($a);
    }

    public function test_size_and_weight_are_shown_in_readable_units(): void
    {
        $small = $this->item(['width_m' => 0.12, 'length_m' => 0.085, 'height_m' => 0.04, 'weight_kg' => 0.35]);
        $this->assertSame('12 × 8,5 × 4 cm', SalesCatalogue::size($small, 'nl'));
        $this->assertSame('350 g', SalesCatalogue::weight($small, 'nl'));

        $big = $this->item(['width_m' => 1.2, 'length_m' => 0.6, 'height_m' => 0.75, 'weight_kg' => 18.5]);
        $this->assertSame('1.2 × 0.6 × 0.75 m', SalesCatalogue::size($big, 'en'));
        $this->assertSame('18.5 kg', SalesCatalogue::weight($big, 'en'));
    }

    public function test_nothing_is_shown_when_the_product_card_has_no_values(): void
    {
        $this->assertNull(SalesCatalogue::size($this->item(['width_m' => 0.1, 'length_m' => null, 'height_m' => 0.1]), 'en'));
        $this->assertNull(SalesCatalogue::size($this->item([]), 'en'));
        $this->assertNull(SalesCatalogue::weight($this->item([]), 'en'));
    }
}
