<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Media\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Media\MailBanners;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Email banners: under the app's mails to outside people, in turn per person, between their dates. */
class MailBannersTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.anthropic.key' => null]);
        $this->sales = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en', 'email' => 'elke@cluk.live']);
    }

    private function banner(string $title, array $extra = []): Message
    {
        $this->actingAs($this->sales, 'web')->post(route('messages.store'), array_merge([
            'kind' => 'mail_banner', 'title' => $title, 'subject' => ['en' => $title.' heading'], 'body' => ['en' => 'Text of '.$title],
            'image' => UploadedFile::fake()->image('b.jpg', 1200, 400), 'link_url' => 'https://cluk.live/sales?b='.urlencode($title),
        ], $extra))->assertSessionHasNoErrors();
        $m = Message::where('title', $title)->firstOrFail();
        $this->actingAs($this->sales, 'web')->post(route('messages.toggle', $m))->assertRedirect();

        return $m->fresh();
    }

    private function mailTo(string $email, ?Confirmation $c = null): string
    {
        $c ??= new Confirmation(subject: 'Your quote', greeting: 'Hello Jan', intro: ['Here is your quote.']);

        return (string) $c->toMail(Notification::route('mail', $email))->render();
    }

    public function test_banners_take_turns_per_person_with_their_link(): void
    {
        $this->assertStringNotContainsString('mail-banner', $this->mailTo('jan@demo.example')); // none yet
        $a = $this->banner('Autumn LED');
        $b = $this->banner('Demo day');
        $this->assertSame([$a->id, $b->id], MailBanners::live()->pluck('id')->all());

        $first = $this->mailTo('jan@demo.example');
        $this->assertStringContainsString('Autumn LED heading', $first);
        $this->assertStringContainsString('https://cluk.live/sales?b=Autumn+LED', $first);
        $this->assertStringContainsString('/m/'.$a->id.'/image', $first);
        $this->assertStringContainsString('Demo day heading', $this->mailTo('JAN@demo.example')); // the reply: banner 2
        $this->assertStringContainsString('Autumn LED heading', $this->mailTo('jan@demo.example')); // and round again
        // Someone else starts at banner 1.
        $this->assertStringContainsString('Autumn LED heading', $this->mailTo('piet@other.example'));
        $this->assertSame([3, 1], [$a->fresh()->sent_count, $b->fresh()->sent_count]);

        // Order changed: Demo day goes first.
        $this->actingAs($this->sales, 'web')->post(route('messages.move', [$b, 'up']))->assertRedirect();
        $this->assertSame([$b->id, $a->id], MailBanners::live()->pluck('id')->all());
        $this->assertSame(1, AuditLog::where('action', 'messages.reordered')->count());
        $this->actingAs($this->sales, 'web')->get(route('messages.index', ['kind' => 'mail_banner']))->assertOk()
            ->assertSee('2 email banners running today')->assertSee('Mail 1')->assertSee('Mail 3 starts again', false);
    }

    public function test_only_between_the_dates_and_not_for_staff_codes_or_the_accountant(): void
    {
        $this->banner('Christmas', ['active_from' => today()->addDays(10)->toDateString(), 'active_until' => today()->addDays(20)->toDateString()]);
        $this->assertStringNotContainsString('mail-banner', $this->mailTo('jan@demo.example'));
        $this->travel(12)->days();
        $this->assertStringContainsString('Christmas heading', $this->mailTo('jan@demo.example'));
        // Colleagues (by address or as user) and security mails: no banner.
        $this->assertStringNotContainsString('mail-banner', $this->mailTo('elke@cluk.live'));
        $this->assertStringNotContainsString('mail-banner', $this->mailTo('someone@cluk.live'));
        $this->assertStringNotContainsString('mail-banner', (string) (new Confirmation(subject: 'x', greeting: 'x', intro: []))->toMail($this->sales)->render());
        $this->assertStringNotContainsString('mail-banner', $this->mailTo('jan@demo.example', (new Confirmation(subject: 'Code', greeting: 'Hi', intro: ['123456']))->withoutBanner()));
        $this->travel(10)->days();
        $this->assertStringNotContainsString('mail-banner', $this->mailTo('jan@demo.example'));
    }

    public function test_test_mail_shows_the_banner_and_links_must_be_web_addresses(): void
    {
        Notification::fake();
        $m = $this->banner('Spring');
        $this->actingAs($this->sales, 'web')->post(route('messages.test', $m), ['locale' => 'en'])->assertRedirect();
        Notification::assertSentOnDemand(Confirmation::class, function (Confirmation $c, $ch, $n) use ($m) {
            return $c->forceBanner?->id === $m->id && str_contains((string) $c->toMail($n)->render(), 'Spring heading');
        });
        $this->actingAs($this->sales, 'web')->put(route('messages.update', $m), ['title' => 'Spring', 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');
        $this->actingAs($this->sales, 'web')->get(route('messages.edit', $m))->assertOk()->assertSee('Link when clicked');
        foreach (['nl' => 'E-mailbanners', 'fr' => 'Bannières e-mail', 'es' => 'Banners de email'] as $loc => $t) {
            $this->sales->update(['locale' => $loc]);
            $this->actingAs($this->sales->fresh(), 'web')->get(route('messages.index', ['kind' => 'mail_banner']))->assertOk()->assertSee($t);
        }
    }
}
