<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailBlock;
use App\Models\Role;
use App\Models\Sales\SalesTask;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Notifications\MessageMail;
use App\Services\Mail\Bounces;
use App\Services\Sales\CommercialTasks;
use App\Services\Web\ApprovalCheck;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Bounced mails block the address and give a red task; website registrations are checked in advance. */
class BounceAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
    }

    private function company(array $a = []): Company
    {
        return Company::create($a + ['name' => 'Demo Events BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'country' => 'BE', 'vat_number' => 'BE0123456789']);
    }

    public function test_a_microsoft_bounce_blocks_the_address_and_makes_a_red_task(): void
    {
        $owner = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'nl']);
        $co = $this->company(['account_owner_id' => $owner->id]);
        $jo = Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'Jo@Demo-Events.example']);
        $co->contacts()->attach($jo->id);

        $body = "Your message to jo@demo-events.example couldn't be delivered.\njo wasn't found at demo-events.example.\n\nDiagnostic information for administrators:\nRemote server returned '550 5.1.1 RESOLVER.ADR.RecipNotFound; not found'\nnoreply@cluk.live\npostmaster@demo-events.example";
        $this->assertSame(0, Bounces::handle('Automatic reply: out of office', 'jo@demo-events.example', $body));
        $this->assertSame(0, Bounces::handle('Delivery delayed: Offer Q-2026-0042', 'postmaster@outlook.com', $body));
        $this->assertSame(1, Bounces::handle('Undeliverable: Offer Q-2026-0042', 'postmaster@outlook.com', $body));

        $b = EmailBlock::active()->firstOrFail();
        $this->assertSame('jo@demo-events.example', $b->email);
        $this->assertSame('Offer Q-2026-0042', $b->subject);
        $this->assertStringContainsString('5.1.1', $b->detail);
        $this->assertTrue(Bounces::isBlocked('JO@demo-events.example'));

        CommercialTasks::refresh();
        $task = SalesTask::where('kind', 'mail')->firstOrFail();
        $this->assertSame(['red', $owner->id, $co->id, $jo->id], [$task->level, $task->owner_id, $task->company_id, $task->contact_id]);
        $this->actingAs($owner, 'web')->get(route('salestasks.index', ['who' => 'me']))->assertOk()->assertSee('jo@demo-events.example');
        $this->actingAs($this->admin, 'web')->get(route('companies.show', $co))->assertOk()->assertSee('Mail could not be delivered')->assertSee('mail bounced');

        // No more mails to it: an app mail to only that address does not leave; a mail with a colleague keeps the colleague.
        Mail::fake();
        Notification::route('mail', 'jo@demo-events.example')->notify(new MessageMail(subject: 'Hi', body: 'Test'));
        $this->assertSame(false, Bounces::filter((new \Symfony\Component\Mime\Email)->to('jo@demo-events.example')));
        $m = (new \Symfony\Component\Mime\Email)->to('jo@demo-events.example', 'an@demo-events.example');
        $this->assertNull(Bounces::filter($m));
        $this->assertSame(['an@demo-events.example'], array_map(fn ($a) => $a->getAddress(), $m->getTo()));

        // The address is changed on the card: the task closes by itself.
        $jo->update(['email' => 'jo.peeters@demo-events.example']);
        CommercialTasks::refresh();
        $this->assertSame('resolved', $task->fresh()->status);
    }

    public function test_unblocking_lets_mails_go_again(): void
    {
        $co = $this->company();
        $co->contacts()->attach(Contact::create(['first_name' => 'An', 'email' => 'an@demo.example'])->id);
        $b = Bounces::block('an@demo.example', '550 5.2.2 mailbox full', 'Newsletter');
        $this->assertNull(Bounces::block('info@cluk.live')); // never our own

        $this->actingAs($this->admin, 'web')->post(route('mail-blocks.clear', $b))->assertRedirect()->assertSessionHas('status');
        $this->assertFalse(Bounces::isBlocked('an@demo.example'));
        $this->assertNotNull($b->fresh()->cleared_at);
    }

    public function test_a_known_contact_with_the_same_vat_number_is_ready_in_one_click(): void
    {
        $co = $this->company();
        $co->contacts()->attach(Contact::create(['first_name' => 'Jo', 'email' => 'jo@demo-events.example'])->id);
        $other = $this->company(['name' => 'Other BV', 'vat_number' => 'BE0999999999']);
        $ready = CustomerAccount::create(['email' => 'jo@demo-events.example', 'password' => 'x', 'first_name' => 'Jo', 'company_name' => 'Demo Events', 'vat_number' => 'BE 0123.456.789', 'status' => 'pending', 'email_verified_at' => now()]);
        $notVerified = CustomerAccount::create(['email' => 'zoe@demo-events.example', 'password' => 'x', 'first_name' => 'Zoe', 'vat_number' => '0123456789', 'status' => 'pending']);
        $conflict = CustomerAccount::create(['email' => 'mia@gmail.com', 'password' => 'x', 'first_name' => 'Mia', 'vat_number' => 'BE0999999999', 'status' => 'pending', 'email_verified_at' => now()]);
        $co->contacts()->attach(Contact::create(['first_name' => 'Mia', 'email' => 'mia@gmail.com'])->id);

        $c = ApprovalCheck::for($ready);
        $this->assertTrue($c['ready']);
        $this->assertSame($co->id, $c['company']->id);
        $this->assertFalse(ApprovalCheck::for($notVerified)['ready']);
        $this->assertSame((string) $co->id, ApprovalCheck::for($notVerified)['choice']); // BE added to a bare number
        $m = ApprovalCheck::for($conflict);
        $this->assertFalse($m['ready']);
        $this->assertContains('conflict', array_column($m['checks'], 1));
        $this->assertContains('free_mail', array_column($m['checks'], 1));

        $this->actingAs($this->admin, 'web')->get(route('web-accounts.index'))->assertOk()->assertSee('1 registration is ready to confirm')->assertSee('Same VAT number as Demo Events BV');
        $this->actingAs($this->admin, 'web')->post(route('web-accounts.approve-ready'), ['ids' => [$ready->id, $notVerified->id, $conflict->id]])->assertSessionHas('status', '1 login confirmed.');
        $this->assertSame(['active', $co->id], [$ready->fresh()->status, $ready->fresh()->company_id]);
        $this->assertSame('pending', $notVerified->fresh()->status);
        $this->assertSame('pending', $conflict->fresh()->status);
    }
}
