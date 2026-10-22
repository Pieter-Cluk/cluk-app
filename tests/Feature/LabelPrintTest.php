<?php

namespace Tests\Feature;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use App\Support\QrSvg;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function as(string $role, string $locale = 'en'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => $locale]);
    }

    public function test_pick_and_print_location_and_item_labels(): void
    {
        $w = $this->as('warehouse');
        $this->actingAs($w)->get('/warehouse')->assertOk()->assertSee('/warehouse/labels', false);
        $this->actingAs($w)->get('/warehouse/labels?floor=G&row=A')->assertOk()->assertSee('GA1A1')->assertSee('59 × 102 mm');

        $r = $this->actingAs($w)->post('/warehouse/labels/locations', ['codes' => ['ga1a1', 'CLEAN', 'NOPE']])->assertOk();
        $r->assertSee('GA1A1')->assertSee('size: 102mm 59mm', false)->assertSee('<path d="M', false)->assertDontSee('NOPE');

        $item = Item::create(['number' => 'LT-SP', 'name' => 'Robe Spiider', 'serial' => true]);
        $a = Asset::create(['item_id' => $item->id, 'code' => 'LT-0142', 'serial_number' => '3021-0142', 'status' => Asset::AVAILABLE]);
        Asset::create(['item_id' => $item->id, 'code' => 'LT-0143', 'status' => Asset::RETIRED]);
        $this->actingAs($w)->get('/warehouse/labels?tab=items&q=spiider')->assertOk()->assertSee('LT-0142')->assertDontSee('LT-0143');

        $this->actingAs($w)->post('/warehouse/labels/items', ['ids' => [$a->id]])->assertOk()->assertSee('SN 3021-0142')->assertSee('size: 25mm 25mm', false);
        $this->assertNotNull($a->fresh()->label_printed_at);
        $this->actingAs($w)->get('/warehouse/labels?tab=items&new=1')->assertOk()->assertDontSee('LT-0142');

        // View-only people can look but not print.
        $this->actingAs($this->as('read-only'))->post('/warehouse/labels/items', ['ids' => [$a->id]])->assertForbidden();

        foreach (['nl' => 'Locatielabels', 'fr' => 'Étiquettes d’emplacement', 'es' => 'Etiquetas de ubicación'] as $loc => $t) {
            $this->actingAs($this->as('warehouse', $loc))->get('/warehouse/labels')->assertOk()->assertSee($t, false);
        }
    }

    public function test_the_qr_code_with_the_c_still_reads(): void
    {
        foreach (['https://app.cluk.live/a/LT-SPIIDER-0012', 'https://app.cluk.live/l/GA1A1', 'https://app.cluk.live/a/X1', 'https://app.cluk.live/a/AUDFULLAC4XK1-0003', 'https://app.cluk.live/a/'.str_repeat('Z', 40)] as $url) {
            $this->assertStringContainsString('stroke-width', QrSvg::make($url, true));
            $o = QrSvg::options(true, QrSvg::version($url), ['outputInterface' => QRGdImagePNG::class, 'scale' => 8, 'drawLightModules' => true]);
            $png = (new QRCode($o))->render($url);
            $im = imagecreatefromstring($png);
            $s = (int) ($o->logoSpaceWidth * 8 * 0.8);
            imagefilledellipse($im, imagesx($im) / 2, imagesy($im) / 2, $s, $s, imagecolorallocate($im, 0, 0, 0)); // the letter, at its worst
            $file = tempnam(sys_get_temp_dir(), 'qr').'.png';
            imagepng($im, $file);
            $this->assertSame($url, (string) (new QRCode)->readFromFile($file));
            @unlink($file);
        }
    }
}
