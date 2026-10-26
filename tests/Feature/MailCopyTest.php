<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Support\MailCopy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** A blind copy of mails to outside people goes to info@cluk.live. */
class MailCopyTest extends TestCase
{
    use RefreshDatabase;

    private function mail(string $to, array $headers = []): Email
    {
        $m = (new Email)->from('pieter@cluk.live')->to($to)->subject('x')->text('x');
        foreach ($headers as $k => $v) {
            $m->getHeaders()->addTextHeader($k, $v);
        }
        MailCopy::apply($m);

        return $m;
    }

    private function bcc(Email $m): array
    {
        return array_map(fn ($a) => $a->getAddress(), $m->getBcc());
    }

    public function test_who_gets_a_copy(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'email' => 'elke@gmail.example']);
        $this->assertSame(['info@cluk.live'], $this->bcc($this->mail('jan@customer.example')));
        $this->assertSame([], $this->bcc($this->mail('ruben@cluk.live')));            // a colleague
        $this->assertSame([], $this->bcc($this->mail('elke@gmail.example')));         // a colleague's own address
        $this->assertSame([], $this->bcc($this->mail('info@cluk.live')));             // already to info
        $this->assertSame([], $this->bcc($this->mail('jan@customer.example', ['List-Unsubscribe' => '<https://x>']))); // newsletter
        $private = $this->mail('jan@customer.example', [MailCopy::NO_COPY => '1']);
        $this->assertSame([], $this->bcc($private));
        $this->assertFalse($private->getHeaders()->has(MailCopy::NO_COPY));
        config(['cluk.mail_copy' => '']);
        $this->assertSame([], $this->bcc($this->mail('jan@customer.example')));
    }

    public function test_private_mails_are_marked_and_the_listener_adds_the_copy(): void
    {
        $c = (new Confirmation(subject: 'Code', greeting: 'Hi', intro: ['123456']))->private();
        $sym = new Email;
        foreach ($c->toMail(Notification::route('mail', 'jan@customer.example'))->callbacks as $cb) {
            $cb($sym);
        }
        $this->assertTrue($sym->getHeaders()->has(MailCopy::NO_COPY));
        $m = (new Email)->from('pieter@cluk.live')->to('jan@customer.example')->subject('x')->text('x');
        event(new MessageSending($m, []));
        $this->assertSame(['info@cluk.live'], $this->bcc($m));
    }
}
