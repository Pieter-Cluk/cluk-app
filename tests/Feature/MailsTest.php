<?php

namespace Tests\Feature;

use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Space;
use App\Models\Company;
use App\Models\Member;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireRequest;
use App\Models\Role;
use App\Models\Sales\Quote;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Afrit6\MemberMails;
use App\Services\Rental\OrderMails;
use App\Services\Rental\QuoteMails;
use App\Support\InLocale;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The CLUK-style emails: every text translated, details right, terms reachable. */
class MailsTest extends TestCase
{
    use RefreshDatabase;

    private string $pattern = '/\b(mails|quotes|workspace|afrit6)\.[a-z_]+(\.[a-z_]+)*\b/';

    private function render(Confirmation $n, object $to): string
    {
        $this->assertNotNull($n->locale, 'every email has the receiver\'s language');
        $html = (string) InLocale::run($n->locale, fn () => $n->toMail($to)->render());
        $this->assertStringContainsString(['en' => 'Kind regards', 'nl' => 'Met vriendelijke groeten', 'fr' => 'Bien cordialement', 'es' => 'Un saludo'][$n->locale], $html);

        return $html;
    }

    public function test_terms_pdf_is_served(): void
    {
        $this->get('/terms')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        foreach (config('rental.terms_files') as $lang => $file) {
            $this->assertFileExists(public_path($file));
            $this->get('/terms/'.$lang)->assertOk()->assertHeader('Content-Disposition', 'inline; filename="'.basename($file).'"');
        }
        $this->get('/terms/de')->assertOk(); // unknown language: the English version
    }

    public function test_member_emails_in_every_language(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['first_name' => 'Lotte']);
        $desk = Space::where('kind', 'desk')->first();
        $room = Space::where('kind', 'room')->first();
        $day = today()->next('Tuesday');

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $member->forceFill(['locale' => $locale])->save();
            $b = DeskBooking::create(['member_id' => $member->id, 'space_id' => $desk->id, 'date' => $day, 'part' => 'am', 'price_cents' => 3000]);
            $r = RoomBooking::create(['member_id' => $member->id, 'space_id' => $room->id, 'date' => $day, 'start_slot' => 2, 'slots' => 4, 'setup' => 'meeting']);
            $sr = ServiceRequest::create(['member_id' => $member->id, 'kind' => 'packing', 'status' => 'pending', 'date' => $day, 'colis' => 3, 'amount_cents' => 480]);
            MemberMails::deskBooked($member, $b);
            MemberMails::roomBooked($member, $r);
            MemberMails::requestReceived($member, $sr);
            $sr->update(['status' => 'declined', 'decline_reason' => 'Closed that day']);
            MemberMails::declined($sr->fresh());
            MemberMails::question($sr->fresh(), 'How heavy are the boxes?');
        }

        $sent = 0;
        Notification::assertSentTo($member, Confirmation::class, function (Confirmation $n) use ($member, &$sent) {
            $html = $this->render($n, $member);
            $this->assertDoesNotMatchRegularExpression($this->pattern, strip_tags($html), $n->subject);
            $this->assertStringContainsString('cluk-logo.png', $html);
            $this->assertStringContainsString('Lotte', $html);
            $sent++;

            return true;
        });
        $this->assertSame(20, $sent);
    }

    public function test_quote_emails_in_every_language(): void
    {
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $sales = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'first_name' => 'Elke', 'last_name' => 'Martens', 'mobile' => '+32 470 00 00 03']);
        $quote = Quote::create([
            'number' => 'R-QUO-2026-0001', 'version' => 1, 'status' => 'accepted', 'owner_id' => $sales->id, 'title' => 'Company evening',
            'out_from' => '2026-11-13 14:00', 'out_until' => '2026-11-16 10:00', 'use_from' => '2026-11-14', 'use_until' => '2026-11-15',
            'token' => str_repeat('x', 48), 'sent_to' => 'sarah@demo.be', 'reserved_until' => now()->addDays(3), 'answered_name' => 'Sarah Peeters',
            'po_number' => 'PO 77', 'answered_at' => now(), 'answer_note' => 'Four more spots please', 'decline_reason' => 'price', 'net_cents' => 87500, 'vat_rate_bp' => 2100, 'vat_cents' => 18375, 'gross_cents' => 105875,
        ]);
        $quote->lines()->create(['kind' => 'item', 'name' => 'Robe Spiider', 'qty' => 8, 'unit_price_cents' => 6500, 'amount_cents' => 78000]);

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $quote->forceFill(['language' => $locale])->save();
            $q = $quote->fresh(['lines', 'owner', 'contact']);
            QuoteMails::sent($q, null, $sales);
            QuoteMails::accepted($q);
            QuoteMails::change($q);
            QuoteMails::declined($q);
        }

        $sent = 0;
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, function (Confirmation $n, $ch, $to) use (&$sent) {
            $html = $this->render($n, $to);
            $this->assertDoesNotMatchRegularExpression($this->pattern, strip_tags($html), $n->subject);
            $this->assertStringContainsString('Elke Martens', $html);
            $this->assertStringContainsString('/terms/'.$n->locale, $html);
            $sent++;

            return true;
        });
        $this->assertSame(16, $sent);
    }

    public function test_order_and_subhire_emails_in_every_language(): void
    {
        $this->seed(RoleSeeder::class);
        Notification::fake();
        $am = User::factory()->create(['role_id' => Role::where('slug', 'asset-manager')->value('id'), 'first_name' => 'Elke', 'last_name' => 'Martens']);
        $order = Order::create([
            'number' => 'R-OC-2026-0001', 'status' => 'approved', 'title' => 'Company evening', 'out_from' => '2026-11-13 14:00', 'out_until' => '2026-11-16 10:00',
            'use_from' => '2026-11-14', 'use_until' => '2026-11-15', 'customer_email' => 'sarah@demo.be', 'confirmed_name' => 'Sarah Peeters', 'po_number' => 'PO 7',
            'net_cents' => 1000, 'vat_rate_bp' => 2100, 'vat_cents' => 210, 'gross_cents' => 1210,
        ]);
        $line = $order->lines()->create(['kind' => 'item', 'name' => 'Robe Spiider', 'qty' => 2, 'amount_cents' => 1000]);
        $supplier = Company::create(['name' => 'Partner BV', 'is_customer' => false, 'is_supplier' => true, 'vat_regime' => 'auto', 'language' => 'nl']);
        $request = SubhireRequest::create(['number' => 'SHQ-2026-0001', 'confirm_number' => 'SHC-2026-0001', 'order_id' => $order->id, 'supplier_id' => $supplier->id, 'status' => 'accepted', 'sent_to' => 'partner@demo.be', 'delivery_at' => '2026-11-12 16:00', 'return_at' => '2026-11-17 10:00', 'total_cents' => 22000, 'extra_cents' => 0]);
        $request->lines()->create(['order_line_id' => $line->id, 'name' => 'Robe Spiider', 'qty' => 2, 'price_cents' => 5500, 'factor' => 2, 'amount_cents' => 22000]);

        foreach (array_keys(config('cluk.locales')) as $locale) {
            $order->forceFill(['language' => $locale])->save();
            $supplier->forceFill(['language' => $locale])->save();
            OrderMails::orderConfirmation($order->fresh(['lines']), $am);
            OrderMails::priceRequest($request->fresh(['supplier', 'lines']), $am);
            OrderMails::subhireConfirmation($request->fresh(['supplier', 'lines']), $am);
        }
        $sent = 0;
        Notification::assertSentTo(new AnonymousNotifiable, Confirmation::class, function (Confirmation $n, $ch, $to) use (&$sent) {
            $html = $this->render($n, $to);
            $this->assertDoesNotMatchRegularExpression('/\\b(mails|orders|quotes)\\.[a-z_]+(\\.[a-z_]+)*\\b/', strip_tags($html), $n->subject);
            $sent++;

            return true;
        });
        $this->assertSame(12, $sent);
    }
}
