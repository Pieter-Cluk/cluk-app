<?php

namespace Tests\Feature;

use App\Models\Afrit6\ServiceRequest;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse\Delivery;
use App\Models\Warehouse\Position;
use App\Notifications\Confirmation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehousePart2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Notification::fake();
    }

    private function as(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    public function test_an_unannounced_delivery_for_a_member_goes_on_the_24h_shelf_and_the_member_is_told(): void
    {
        $crew = $this->as('warehouse');
        $member = Member::factory()->active()->create(['locale' => 'en']);
        $this->actingAs($crew)->get('/w/delivery')->assertOk()->assertSee($member->companyName());
        $this->actingAs($crew)->post('/w/delivery', ['colli' => 2, 'pallets' => 0, 'match' => 'member', 'member_id' => $member->id])->assertSessionHasErrors('photos');
        $this->actingAs($crew)->post('/w/delivery', ['photos' => [UploadedFile::fake()->image('label.jpg')], 'colli' => 2, 'pallets' => 0, 'carrier' => 'DHL', 'match' => 'member', 'member_id' => $member->id])->assertRedirect('/w');
        $r = ServiceRequest::where('member_id', $member->id)->firstOrFail();
        $this->assertSame(['receive', 'done', 'shelf24', 2], [$r->kind, $r->status, $r->receive_mode, $r->colis]);
        $this->assertSame('done', Delivery::firstOrFail()->status);
        Notification::assertSentTo($member, Confirmation::class, fn ($n) => str_contains($n->subject, 'Goods arrived'));
    }

    public function test_unknown_goods_wait_for_the_office(): void
    {
        $crew = $this->as('warehouse');
        $this->actingAs($crew)->post('/w/delivery', ['photos' => [UploadedFile::fake()->image('label.jpg')], 'colli' => 1, 'pallets' => 1, 'damaged' => '1', 'match' => 'unknown'])->assertRedirect('/w');
        $d = Delivery::firstOrFail();
        $this->assertSame('open', $d->status);
        $this->actingAs($crew)->get('/warehouse')->assertSee('who is this for?');
        $d->forceFill(['created_at' => now()->subDays(5)])->save();
        $this->assertTrue($d->fresh()->isOverdue());
        $this->actingAs($crew)->post('/warehouse/deliveries/'.$d->id, [])->assertSessionHasErrors('note');
        $this->actingAs($crew)->post('/warehouse/deliveries/'.$d->id, ['note' => 'Refused, back with the carrier'])->assertRedirect();
        $this->assertSame('done', $d->fresh()->status);
    }

    public function test_afrit6_task_on_the_phone_needs_a_photo_and_a_scan_of_an_afrit6_place(): void
    {
        $crew = $this->as('warehouse');
        $member = Member::factory()->active()->create(['locale' => 'en']);
        $task = ServiceRequest::create(['member_id' => $member->id, 'kind' => 'packing', 'status' => 'confirmed', 'date' => today(), 'colis' => 40]);
        Position::where('code', 'GF5B1')->update(['use' => 'afrit6']);
        $this->actingAs($crew)->get('/w/afrit6')->assertOk()->assertSee($member->companyName());
        $this->actingAs($crew)->get('/w/afrit6/'.$task->id)->assertOk();
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/done')->assertSessionHasErrors('photos');
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/photo', ['photos' => [UploadedFile::fake()->image('boxes.jpg')]]);
        $this->assertSame($crew->id, $task->fresh()->assignee_id);
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/done')->assertSessionHasErrors('code');
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/scan', ['code' => 'GA1B1'])->assertSessionHasErrors('code'); // rental place
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/scan', ['code' => 'https://app.cluk.live/l/GF5B1'])->assertSessionHasNoErrors();
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/scan', ['code' => 'DISPATCH'])->assertSessionHasNoErrors();
        $this->actingAs($crew)->post('/w/afrit6/'.$task->id.'/done', ['colis' => 38])->assertRedirect('/w/afrit6');
        $task->refresh();
        $this->assertSame(['done', 38, 2], [$task->status, $task->colis, count($task->scans)]);
        $this->actingAs($crew)->get('/w/media/a6/'.$task->id.'/0')->assertOk();
    }

    public function test_pages_in_every_language(): void
    {
        $admin = $this->as('admin');
        $member = Member::factory()->active()->create();
        $task = ServiceRequest::create(['member_id' => $member->id, 'kind' => 'receive', 'status' => 'pending', 'date' => today(), 'colis' => 3, 'pallets' => 1]);
        Delivery::create(['colli' => 2, 'pallets' => 1, 'match' => 'unknown', 'photos' => []]);
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $admin->forceFill(['locale' => $locale])->save();
            foreach (['/w', '/w/afrit6', '/w/afrit6/'.$task->id, '/w/delivery', '/warehouse'] as $url) {
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $this->assertDoesNotMatchRegularExpression('/\b(warehouse|workspace)\.[a-z_]+(\.[A-Za-z_\-0-9]+)*\b/', strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)), "{$url} ({$locale})");
            }
        }
    }
}
