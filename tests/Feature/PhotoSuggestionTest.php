<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Equipment\PhotoSuggestion;
use App\Models\Role;
use App\Models\User;
use App\Services\Equipment\PhotoSuggestions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(400, 400);
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel($im, random_int(0, 399), random_int(0, 399), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function suggestion(array $items, array $urls): PhotoSuggestion
    {
        $s = PhotoSuggestion::create(['group_key' => 'Robe | Spiider', 'title' => 'Robe Spiider', 'item_numbers' => $items, 'candidates' => $urls, 'status' => 'new', 'source' => 'manufacturer']);
        PhotoSuggestions::fetch();

        return $s->fresh();
    }

    public function test_photos_are_fetched_reviewed_and_used_for_every_item_of_the_product(): void
    {
        $png = $this->png();
        Http::fake(fn ($r) => str_contains($r->url(), 'broken') ? Http::response('nope', 404) : Http::response($png, 200, ['Content-Type' => 'image/png']));
        $a = Item::create(['number' => 'LIGSPIIDER1', 'name' => 'Robe Spiider', 'serial' => false]);
        $b = Item::create(['number' => 'LIGSPIIDER2', 'name' => 'Robe Spiider (case)', 'serial' => false]);
        $s = $this->suggestion(['LIGSPIIDER1', 'LIGSPIIDER2'], ['https://robe.cz/a.png', 'https://robe.cz/broken.png', 'https://robe.cz/c.png']);
        $this->assertSame('pending', $s->status);
        $this->assertSame([0, 2], array_keys($s->usable()));

        $boss = $this->as('admin');
        $this->actingAs($boss)->get('/dry-hire/photos-found')->assertOk()->assertSee('Robe Spiider')->assertSee('Use this photo');
        $this->actingAs($boss)->get('/dry-hire/photos-found/'.$s->id.'/2')->assertOk();
        $this->actingAs($boss)->get('/dry-hire/photos-found/'.$s->id.'/1')->assertNotFound();

        $this->actingAs($boss)->post('/dry-hire/photos-found/'.$s->id, ['use' => 2])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('approved', $s->fresh()->status);
        foreach ([$a, $b] as $it) {
            $it->refresh();
            $this->assertNotNull($it->photo_path);
            Storage::disk('local')->assertExists($it->photo_path);
        }
        $this->assertSame([], $s->fresh()->usable()); // downloaded copies cleaned up
        $this->assertTrue(AuditLog::where('action', 'equipment.photo_approved')->exists());
        $this->actingAs($boss)->post('/dry-hire/photos-found/'.$s->id, ['use' => 0])->assertStatus(409);
    }

    public function test_none_fits_bulk_and_rights(): void
    {
        $png = $this->png();
        Http::fake(fn () => Http::response($png, 200));
        Item::create(['number' => 'X1', 'name' => 'Thing', 'serial' => false]);
        Item::create(['number' => 'Y1', 'name' => 'Thing', 'serial' => false]);
        $s1 = $this->suggestion(['X1'], ['https://x.test/1.png']);
        $s2 = PhotoSuggestion::create(['group_key' => 'B | Y', 'title' => 'B Y', 'item_numbers' => ['Y1'], 'candidates' => ['https://x.test/2.png'], 'status' => 'new']);
        PhotoSuggestions::fetch();

        $this->actingAs($this->as('finance'))->get('/dry-hire/photos-found')->assertForbidden();

        $boss = $this->as('admin');
        $this->actingAs($boss)->post('/dry-hire/photos-found/'.$s1->id)->assertRedirect();
        $this->assertSame('rejected', $s1->fresh()->status);
        $this->assertNull(Item::where('number', 'X1')->value('photo_path'));

        $this->actingAs($boss)->post('/dry-hire/photos-found/bulk', ['ids' => [$s1->id, $s2->id]])->assertRedirect();
        $this->assertSame('rejected', $s1->fresh()->status);
        $this->assertSame('approved', $s2->fresh()->status);
        $this->assertNotNull(Item::where('number', 'Y1')->value('photo_path'));

        foreach (['nl' => 'Foto’s gevonden op het web', 'fr' => 'Photos trouvées sur le web', 'es' => 'Fotos encontradas en la web'] as $loc => $t) {
            $boss->update(['locale' => $loc]);
            $this->actingAs($boss)->get('/dry-hire/photos-found?s=approved')->assertOk()->assertSee($t, false);
        }
    }

    public function test_the_list_is_loaded_from_the_data_file_and_decided_ones_stay(): void
    {
        $n = PhotoSuggestions::load();
        $this->assertGreaterThan(100, $n);
        $s = PhotoSuggestion::first();
        $s->update(['status' => 'rejected']);
        PhotoSuggestions::load();
        $this->assertSame('rejected', $s->fresh()->status);
        $this->assertSame(0, PhotoSuggestions::load());
    }
}
