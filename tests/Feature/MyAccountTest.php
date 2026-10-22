<?php

namespace Tests\Feature;

use App\Models\Finance\PreInvoice;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Me\DailySummary;
use App\Support\StaffMail;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MyAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
    }

    private function as(string $role, array $extra = []): User
    {
        return User::factory()->create($extra + ['role_id' => Role::where('slug', $role)->value('id'), 'locale' => 'en']);
    }

    private function prefs(array $over = []): array
    {
        return $over + ['theme' => 'auto', 'density' => 'comfortable', 'start_page' => 'dashboard', 'locale' => 'en', 'mail' => ['quote_answered' => 1, 'order_handed' => 1, 'pf_back' => 1]];
    }

    public function test_preferences_change_the_look_start_page_and_language(): void
    {
        $u = $this->as('sales');
        $this->actingAs($u)->get('/me/preferences')->assertOk()->assertSee('My preferences')->assertSee('Night')->assertDontSee('>Management<', false);

        $this->actingAs($u)->put('/me/preferences', $this->prefs(['theme' => 'dark', 'density' => 'compact', 'start_page' => 'companies', 'locale' => 'nl']))->assertRedirect('/me/preferences');
        $u->refresh();
        $this->assertSame(['dark', 'compact', 'companies', 'nl'], [$u->theme, $u->density, $u->start_page, $u->locale]);
        $this->actingAs($u)->get('/me/preferences')->assertOk()->assertSee('data-theme="dark"', false)->assertSee('density-compact')->assertSee('Mijn voorkeuren');
        $this->assertSame(route('companies.index'), $u->homeUrl());
        $this->actingAs($u)->get('/')->assertRedirect(route('companies.index'));

        // The day/night button in the top bar remembers the choice.
        $this->actingAs($u)->postJson('/me/theme', ['theme' => 'light'])->assertNoContent();
        $this->assertSame('light', $u->fresh()->theme);

        // A start page the person cannot open falls back to the dashboard.
        $u->forceFill(['start_page' => 'management'])->save();
        $this->assertSame(route('dashboard'), $u->fresh()->homeUrl());

        $this->actingAs($u)->get('/me/notifications')->assertRedirect('/me/preferences#notifications');
    }

    public function test_emails_can_be_switched_off_and_go_to_the_stand_in_while_away(): void
    {
        $elke = $this->as('sales', ['first_name' => 'Elke']);
        $ruben = $this->as('sales', ['first_name' => 'Ruben']);
        $mail = fn () => new Confirmation(subject: 'x', greeting: 'Hi', intro: ['y']);

        StaffMail::send($elke, 'pf_back', $mail());
        Notification::assertSentToTimes($elke, Confirmation::class, 1);

        $this->actingAs($elke)->put('/me/preferences', $this->prefs(['mail' => ['quote_answered' => 1], 'away_delegate_id' => $ruben->id, 'away_from' => today()->toDateString(), 'away_until' => today()->addDays(3)->toDateString()]))->assertSessionHasNoErrors();
        $elke->refresh();
        $this->assertFalse($elke->wantsMail('pf_back'));
        $this->assertTrue($elke->awayNow());

        StaffMail::send($elke, 'pf_back', $mail());
        Notification::assertSentToTimes($elke, Confirmation::class, 1); // switched off
        Notification::assertSentToTimes($ruben, Confirmation::class, 1); // the stand-in still hears about it
        $this->actingAs($elke)->get('/dashboard')->assertSee('Away until');

        // Not yourself, and dates are needed.
        $this->actingAs($elke)->put('/me/preferences', $this->prefs(['away_delegate_id' => $elke->id, 'away_from' => today()->toDateString(), 'away_until' => today()->toDateString()]))->assertSessionHasErrors('away_delegate_id');
        $this->actingAs($elke)->put('/me/preferences', $this->prefs(['away_delegate_id' => $ruben->id]))->assertSessionHasErrors(['away_from', 'away_until']);
    }

    public function test_daily_summary_once_per_working_day_only_with_tasks(): void
    {
        $u = $this->as('sales', ['daily_at' => '08:00']);
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(7, 0)); // Thursday
        PreInvoice::create(['number' => 'PF-2026-0001', 'source_type' => 'rental', 'source_id' => 1, 'source_code' => 'X1', 'description' => 'Job', 'module' => 'dryhire',
            'made_by_id' => $u->id, 'status' => 'back', 'lines' => [], 'net_cents' => 100, 'vat_cents' => 0, 'gross_cents' => 100, 'payment_days' => 30]);

        $this->assertSame(0, DailySummary::run(now()->timezone('Europe/Brussels')->setTime(7, 45)));
        $this->assertSame(1, DailySummary::run(now()->timezone('Europe/Brussels')->setTime(8, 5)));
        $this->assertSame(0, DailySummary::run(now()->timezone('Europe/Brussels')->setTime(9, 0)));
        Notification::assertSentToTimes($u, Confirmation::class, 1);
        $this->assertSame(0, DailySummary::run(now()->setDate(2026, 10, 24)->setTime(9, 0))); // Saturday
    }

    public function test_profile_shows_access_and_saves_phone_numbers_in_four_languages(): void
    {
        $u = $this->as('warehouse', ['title' => 'Warehouse lead']);
        $this->actingAs($u)->get('/me/profile')->assertOk()->assertSee('Warehouse lead')->assertSee('Warehouse: sign delivery notes and hand over');
        $this->actingAs($u)->put('/me/profile', ['phone' => '+32 9 123 45 67', 'mobile' => '+32 470 12 34 56', 'role_id' => 1])->assertRedirect();
        $this->assertSame(['+32 9 123 45 67', $u->role_id], [$u->fresh()->phone, $u->fresh()->role_id]);
        foreach (['nl' => 'Mijn profiel', 'fr' => 'Mon profil', 'es' => 'Mi perfil'] as $loc => $t) {
            $u->update(['locale' => $loc]);
            $this->actingAs($u)->get('/me/profile')->assertOk()->assertSee($t);
            $this->actingAs($u)->get('/me/preferences')->assertOk();
        }
    }
}
