<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Notifications\MemberVerifyEmail;
use App\Support\SafeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GraphMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.default' => 'microsoft-graph',
            'mail.from.address' => 'noreply@cluk.live',
            'mail.from.name' => 'CLUK',
            'services.microsoft.tenant' => 'tenant-id',
            'services.microsoft.client_id' => 'client-id',
            'services.microsoft.client_secret' => 'secret',
        ]);
        Cache::flush();
    }

    public function test_mail_is_sent_through_microsoft_graph_as_the_cluk_mailbox(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        Mail::raw('Hello', fn ($m) => $m->to('lien@demo.example', 'Lien')->subject('Test'));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/tenant-id/oauth2/v2.0/token') && $r['grant_type'] === 'client_credentials');
        Http::assertSent(function ($r) {
            return $r->url() === 'https://graph.microsoft.com/v1.0/users/noreply%40cluk.live/sendMail'
                && $r->hasHeader('Authorization', 'Bearer tok')
                && $r['message']['subject'] === 'Test'
                && $r['message']['toRecipients'][0]['emailAddress']['address'] === 'lien@demo.example'
                && $r['message']['from']['emailAddress']['address'] === 'noreply@cluk.live';
        });
    }

    public function test_a_failing_mail_service_does_not_break_the_page(): void
    {
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => 'bad secret'], 401)]);
        $member = Member::factory()->create();

        $this->assertFalse(SafeMail::notify($member, new MemberVerifyEmail));
    }
}
