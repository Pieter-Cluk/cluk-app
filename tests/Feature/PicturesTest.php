<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\MicrosoftLoginController;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PicturesTest extends TestCase
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

    public function test_items_get_several_photos_made_smaller_with_a_main_one(): void
    {
        $am = $this->as('asset-manager');
        $item = Item::where('number', 'AUDSPEQSCKLA181')->firstOrFail();

        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/photos', ['photos' => [
            UploadedFile::fake()->image('front.jpg', 3000, 2000), UploadedFile::fake()->image('back.png', 800, 800),
        ]])->assertSessionHasNoErrors();
        $photos = $item->photos()->get();
        $this->assertCount(2, $photos);
        $this->assertSame($photos[0]->path, $item->fresh()->photo_path);
        [$w, $h] = getimagesize(Storage::disk('local')->path($photos[0]->path));
        $this->assertSame([1600, 1067], [$w, $h]);

        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/photos/'.$photos[1]->id.'/main');
        $this->assertSame($photos[1]->path, $item->fresh()->photo_path);
        $this->actingAs($am)->get('/media/items/'.$photos[1]->id)->assertOk();
        $this->actingAs($am)->get('/dry-hire/items/'.$item->id)->assertOk()->assertSee('/media/items/'.$photos[1]->id);

        $this->actingAs($am)->delete('/dry-hire/items/'.$item->id.'/photos/'.$photos[1]->id);
        $this->assertSame($photos[0]->path, $item->fresh()->photo_path);
        Storage::disk('local')->assertMissing($photos[1]->path);

        // Not a picture is refused; read-only cannot upload; pictures need a login.
        $this->actingAs($am)->post('/dry-hire/items/'.$item->id.'/photos', ['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('photos.0');
        $this->actingAs($this->as('read-only'))->post('/dry-hire/items/'.$item->id.'/photos', ['photos' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
        auth('web')->logout();
        $this->get('/media/items/'.$photos[0]->id)->assertRedirect();
    }

    public function test_photos_can_be_added_when_creating_an_item(): void
    {
        $this->actingAs($this->as('asset-manager'))->post('/dry-hire/items', [
            'number' => 'AUDSPEQSCK12', 'kind' => 'item', 'name' => 'QSC K12', 'unit' => 'pcs', 'serial' => '1',
            'photos' => [UploadedFile::fake()->image('k12.jpg', 600, 600)],
        ])->assertRedirect();
        $this->assertNotNull(Item::where('number', 'AUDSPEQSCK12')->value('photo_path'));
    }

    public function test_company_logo_and_contact_photo(): void
    {
        $sales = $this->as('sales');
        $company = Company::create(['name' => 'Demo BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $contact = Contact::create(['first_name' => 'Sarah', 'last_name' => 'Peeters']);
        $company->contacts()->attach($contact->id);

        $this->actingAs($sales)->post('/companies/'.$company->id.'/logo', ['logo' => UploadedFile::fake()->image('logo.png', 1200, 300)])->assertSessionHasNoErrors();
        $path = $company->fresh()->logo_path;
        $this->assertStringEndsWith('.png', $path); // logos keep their transparency
        $this->actingAs($sales)->get('/companies')->assertSee('/media/companies/'.$company->id);

        $this->actingAs($sales)->post('/companies/'.$company->id.'/contacts/'.$contact->id.'/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 500, 500)])->assertSessionHasNoErrors();
        $this->assertNotNull($contact->fresh()->photo_path);
        $this->actingAs($sales)->get('/companies/'.$company->id)->assertSee('/media/contacts/'.$contact->id);

        $this->actingAs($sales)->delete('/companies/'.$company->id.'/logo');
        $this->assertNull($company->fresh()->logo_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_staff_photo_is_taken_over_from_microsoft_365(): void
    {
        $user = $this->as('sales');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAEklEQVR4nGP8z8DAwMDAxMDAAAAeAAH/XqUpcQAAAABJRU5ErkJggg==');
        $jpeg = (function () {
            $i = imagecreatetruecolor(300, 300);
            ob_start();
            imagejpeg($i);

            return ob_get_clean();
        })();
        Http::fake(['graph.microsoft.com/*' => Http::sequence()->push($jpeg, 200, ['Content-Type' => 'image/jpeg'])->push('', 404)]);

        MicrosoftLoginController::takeOverPhoto($user, 'token');
        $this->assertNotNull($user->fresh()->photo_path);
        $this->actingAs($user)->get('/dashboard')->assertSee('/media/users/'.$user->id);

        // No photo in Microsoft (second answer): keep the one we have.
        $before = $user->fresh()->photo_path;
        MicrosoftLoginController::takeOverPhoto($user->fresh(), 'token');
        $this->assertSame($before, $user->fresh()->photo_path);
        $this->assertNotEmpty($png);
    }

    public function test_only_managers_upload_someone_elses_photo(): void
    {
        $sales = $this->as('sales');
        $other = $this->as('sales');
        $this->actingAs($sales)->post('/users/'.$other->id.'/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $this->actingAs($sales)->post('/users/'.$sales->id.'/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($this->as('admin'))->post('/users/'.$other->id.'/photo', ['photo' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasNoErrors();
    }
}
