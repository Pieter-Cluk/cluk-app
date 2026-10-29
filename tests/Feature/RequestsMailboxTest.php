<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use App\Services\Mail\RequestsMailbox;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mails in requests@cluk.live become Incoming items: forwarded ones under the original sender, with attachments. */
class RequestsMailboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Europe/Brussels']);
        date_default_timezone_set('Europe/Brussels');
        config(['mail.default' => 'microsoft-graph', 'services.microsoft.tenant' => 't', 'services.microsoft.client_id' => 'c', 'services.microsoft.client_secret' => 's']);
        Storage::fake('local');
        Cache::flush();
    }

    private function fake(array $messages, array $bodies, array $files = []): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            'graph.microsoft.com/v1.0/users/requests%40cluk.live/mailFolders/inbox/messages*' => Http::response(['value' => $messages]),
            'graph.microsoft.com/v1.0/users/requests%40cluk.live/messages/*/attachments' => Http::response(['value' => $files]),
            'graph.microsoft.com/v1.0/users/requests%40cluk.live/messages/*' => function ($req) use ($bodies) {
                $id = urldecode(explode('?', last(explode('/messages/', $req->url())))[0]);

                return Http::response($bodies[$id] ?? ['body' => ['content' => '']]);
            },
        ]);
    }

    private function msg(string $id, string $subject, string $from, string $name = '', bool $files = false): array
    {
        return ['id' => $id, 'internetMessageId' => '<'.$id.'@x>', 'subject' => $subject, 'from' => ['emailAddress' => ['address' => $from, 'name' => $name]],
            'receivedDateTime' => now()->subMinutes(5)->toIso8601String(), 'hasAttachments' => $files];
    }

    public function test_mails_become_incoming_items(): void
    {
        $co = Company::create(['name' => 'Demo Events BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        $p = Contact::create(['first_name' => 'Lies', 'last_name' => 'Peeters', 'email' => 'lies@demo-events.be', 'language' => 'nl']);
        $co->contacts()->attach($p->id);
        $this->fake(
            [
                $this->msg('m1', 'Offerte 20 blinders', 'jan@other.be', 'Jan Other', true),
                $this->msg('m2', 'FW: Aanvraag truss', 'pieter@cluk.live', 'Pieter'),
                $this->msg('m3', 'Automatisch antwoord: afwezig', 'x@y.be'),
                $this->msg('m4', 'Our October news', 'news@brand.com'),
            ],
            [
                'm1' => ['body' => ['content' => "Hallo,\nGraag een prijs voor 20 blinders.\nJan"]],
                'm2' => ['body' => ['content' => "Zie hieronder.\n\nVan: Lies Peeters <lies@demo-events.be>\nVerzonden: dinsdag\nOnderwerp: Aanvraag truss\n\n12 m truss graag."]],
                'm4' => ['body' => ['content' => 'news'], 'internetMessageHeaders' => [['name' => 'List-Unsubscribe', 'value' => '<mailto:u@brand.com>']]],
            ],
            [['@odata.type' => '#microsoft.graph.fileAttachment', 'name' => 'plan.pdf', 'size' => 9, 'isInline' => false, 'contentBytes' => base64_encode('%PDF-1.4 ')],
             ['@odata.type' => '#microsoft.graph.fileAttachment', 'name' => 'logo.png', 'size' => 5, 'isInline' => true, 'contentBytes' => base64_encode('x')]],
        );

        $this->assertSame(2, RequestsMailbox::scan());
        $this->assertSame('ok', RequestsMailbox::status());
        $a = IncomingMessage::where('subject', 'Offerte 20 blinders')->firstOrFail();
        $this->assertSame(['email', 'jan@other.be', 'Jan Other'], [$a->channel, $a->from_email, $a->from_name]);
        $this->assertSame(['plan.pdf'], array_column($a->payload['attachments'], 'name'));
        $this->assertSame(now()->subMinutes(5)->format('Y-m-d H:i'), $a->received_at->format('Y-m-d H:i')); // local time, not UTC
        $b = IncomingMessage::where('subject', 'Aanvraag truss')->firstOrFail();
        $this->assertSame(['lies@demo-events.be', 'Lies Peeters', $co->id, $p->id, 'pieter@cluk.live'], [$b->from_email, $b->from_name, $b->company_id, $b->contact_id, $b->payload['forwarded_by']]);

        // Read again: nothing twice.
        Cache::forget('requests_mailbox.since');
        $this->assertSame(0, RequestsMailbox::scan());
        $this->assertSame(2, IncomingMessage::count());

        $this->seed(RoleSeeder::class);
        $u = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id'), 'locale' => 'en']);
        $this->actingAs($u)->get(route('incoming.index', ['open' => $a->id]))->assertOk()->assertSee('plan.pdf')->assertSee('requests@cluk.live arrive here');
        $this->actingAs($u)->get(route('incoming.file', [$a, 0]))->assertOk()->assertDownload('plan.pdf');
        $this->actingAs($u)->get(route('incoming.index', ['open' => $b->id]))->assertSee('Forwarded by pieter@cluk.live');
    }

    public function test_a_missing_mailbox_is_shown_and_retried_later(): void
    {
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorInvalidUser']], 404)]);
        $this->assertSame(0, RequestsMailbox::scan());
        $this->assertSame('missing', RequestsMailbox::status());
        Http::assertSentCount(2);
        $this->assertSame(0, RequestsMailbox::scan()); // paused for an hour
        Http::assertSentCount(2);
    }
}
