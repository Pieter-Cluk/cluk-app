<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Media\Message;
use App\Models\Media\MessageSend;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MessageMail;
use App\Services\Crm\Birthdays;
use App\Services\Media\Messages;
use App\Services\Media\Translator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Notification::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([Translator::URL => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'nl' => ['subject' => 'Onze nieuwsbrief', 'body' => 'Hallo {name}, lees het nieuws.'],
            'fr' => ['subject' => 'Notre newsletter', 'body' => 'Bonjour {name}, lisez les nouvelles.'],
            'es' => ['subject' => 'Nuestro boletín', 'body' => 'Hola {name}, lee las noticias.'],
        ])]]])]);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en', 'email' => 'pieter@cluk.live']);
    }

    private function people(): void
    {
        $nl = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $fr = Company::create(['name' => 'Demo Liège SRL', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'fr']);
        $nl->contacts()->attach(Contact::create(['first_name' => 'Jo', 'email' => 'jo@demo.example', 'newsletter' => true])->id);
        $fr->contacts()->attach(Contact::create(['first_name' => 'Luc', 'email' => 'luc@demo.example', 'newsletter' => false])->id);
        Contact::create(['first_name' => 'Ana', 'email' => 'ana@demo.example', 'language' => 'es', 'newsletter' => false, 'promo_opt_out' => true]);
        Member::factory()->active()->create(['email' => 'lien@studio.example', 'locale' => 'nl', 'newsletter' => true]);
    }

    public function test_a_newsletter_is_translated_sent_once_and_can_be_unsubscribed(): void
    {
        $this->people();
        $this->actingAs($this->admin)->get('/media/messages?kind=newsletter')->assertOk()->assertSee('2 people on the list now');
        $this->actingAs($this->admin)->post('/media/messages', ['kind' => 'newsletter', 'title' => 'Newsletter November', 'subject' => ['en' => 'Our newsletter'], 'body' => ['en' => 'Hi {name}, read the news.'],
            'image' => UploadedFile::fake()->image('cover.jpg', 1200, 600), 'pdf' => UploadedFile::fake()->create('nov.pdf', 300, 'application/pdf')])->assertRedirect();
        $m = Message::firstOrFail();
        $this->assertSame('Onze nieuwsbrief', $m->subject['nl']);
        $this->assertFalse($m->translationsOutdated());
        $this->actingAs($this->admin)->get('/media/messages/'.$m->id)->assertOk()->assertSee('Notre newsletter')->assertSee('Goes to 2 people');

        $this->actingAs($this->admin)->post('/media/messages/'.$m->id.'/test', ['locale' => 'fr'])->assertSessionHasNoErrors();
        Notification::assertSentTo(new AnonymousNotifiable, MessageMail::class, fn ($n, $c, $to) => $to->routes['mail'] === 'pieter@cluk.live' && $n->subject === 'Notre newsletter' && count($n->files) === 1);

        $this->actingAs($this->admin)->post('/media/messages/'.$m->id.'/send')->assertSessionHasNoErrors();
        $m->refresh();
        $this->assertSame('sent', $m->status);
        $this->assertSame(2, $m->sent_count);
        Notification::assertSentTo(new AnonymousNotifiable, MessageMail::class, fn ($n, $c, $to) => $to->routes['mail'] === 'jo@demo.example' && $n->subject === 'Onze nieuwsbrief' && $n->unsubscribeUrl);
        // Never again by itself.
        $this->assertSame(0, Messages::work());
        $this->actingAs($this->admin)->post('/media/messages/'.$m->id.'/send')->assertStatus(409);

        // Unsubscribe from the link in the mail.
        $send = MessageSend::where('email', 'jo@demo.example')->firstOrFail();
        $url = URL::signedRoute('messages.unsubscribe', ['list' => 'newsletter', 'send' => $send->id]);
        auth('web')->logout();
        $this->get($url)->assertOk()->assertSee('Deze mails stoppen');
        $this->post($url)->assertOk();
        $this->assertFalse(Contact::where('email', 'jo@demo.example')->value('newsletter'));
        $this->get(URL::signedRoute('messages.public.image', $m))->assertOk();
        $this->get(route('messages.public.image', $m))->assertForbidden();
    }

    public function test_a_scheduled_promotion_goes_to_everyone_once_at_its_time(): void
    {
        $this->people();
        $this->actingAs($this->admin)->post('/media/messages', ['kind' => 'promotion', 'title' => 'Black Friday', 'subject' => ['en' => 'Our newsletter'], 'body' => ['en' => 'Hi {name}, read the news.']]);
        $m = Message::firstOrFail();
        $this->actingAs($this->admin)->post('/media/messages/'.$m->id.'/schedule', ['send_at' => now()->addHour()->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        $this->assertSame(0, Messages::work());
        $this->travel(2)->hours();
        $this->assertSame(3, Messages::work(), 'Jo, Luc and the member; Ana unsubscribed from promotions');
        $this->assertSame('sent', $m->fresh()->status);
        $this->assertSame(0, Messages::work());
        Notification::assertSentTo(new AnonymousNotifiable, MessageMail::class, fn ($n, $c, $to) => $to->routes['mail'] === 'luc@demo.example' && $n->subject === 'Notre newsletter' && $n->body === 'Bonjour Luc, lisez les nouvelles.');
        $this->assertSame('Happy birthday!', Messages::fill('Happy birthday, {NAME}!', []));
        $this->assertSame('Jo, our deals for you', Messages::fill('{Name}, our deals for you', ['name' => 'Jo']));
        $this->assertSame('Our deals', Messages::fill('{name}, our deals', []));
    }

    public function test_birthday_card_with_picture_and_the_banner_on_quotes(): void
    {
        $this->actingAs($this->admin)->post('/media/messages', ['kind' => 'birthday', 'title' => 'Card 2027', 'subject' => ['en' => 'Our newsletter'], 'body' => ['en' => 'Hi {name}, read the news.'], 'image' => UploadedFile::fake()->image('cake.jpg')]);
        $card = Message::firstOrFail();
        $this->actingAs($this->admin)->post('/media/messages/'.$card->id.'/toggle');
        $this->assertTrue($card->fresh()->active);
        $c = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $c->contacts()->attach(Contact::create(['first_name' => 'Jo', 'email' => 'jo@demo.example', 'birth_date' => '1985-03-14', 'birthday_card' => true])->id);
        $this->assertSame(1, Birthdays::sendToday(Carbon::parse('2027-03-14')));
        Notification::assertSentTo(new AnonymousNotifiable, MessageMail::class, fn ($n) => $n->body === 'Hallo Jo, lees het nieuws.' && $n->imageUrl && ! $n->unsubscribeUrl);

        $this->actingAs($this->admin)->post('/media/messages', ['kind' => 'banner', 'title' => 'Christmas', 'subject' => ['en' => 'Our newsletter'], 'body' => ['en' => 'Closed 24/12 – 2/1'],
            'active_from' => today()->subDay()->toDateString(), 'active_until' => today()->addMonth()->toDateString()]);
        $banner = Message::kind('banner')->firstOrFail();
        $this->assertNull(Message::inUse('banner'));
        $this->actingAs($this->admin)->post('/media/messages/'.$banner->id.'/toggle');
        $this->assertTrue(Message::inUse('banner')->is($banner));
        $this->actingAs($this->admin)->get('/media/messages?kind=banner')->assertSee('In use now: Christmas');
    }

    public function test_without_a_translation_key_english_is_used_and_staff_are_told(): void
    {
        config(['services.anthropic.key' => null]);
        $this->actingAs($this->admin)->post('/media/messages', ['kind' => 'promotion', 'title' => 'Deal', 'subject' => ['en' => 'Deal'], 'body' => ['en' => 'Only today']])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'not switched on yet'));
        $m = Message::firstOrFail();
        $this->assertSame('Deal', $m->text('subject', 'fr'));
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'warehouse')->value('id')]))->get('/media/messages')->assertForbidden();
    }
}
