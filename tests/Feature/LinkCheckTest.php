<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Item;
use App\Models\Role;
use App\Models\User;
use App\Support\LinkCheck;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every page reachable from the dashboard opens, and no page shows a raw translation key. */
class LinkCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_link_from_the_dashboard_works(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id'), 'locale' => 'en']);
        $co = Company::create(['name' => 'Demo Events BV', 'is_customer' => true, 'is_supplier' => true, 'vat_regime' => 'auto', 'country' => 'BE', 'vat_number' => 'BE0123456789', 'email' => 'info@demo.example', 'account_owner_id' => $admin->id]);
        $co->contacts()->attach(Contact::create(['first_name' => 'Jo', 'last_name' => 'Peeters', 'email' => 'jo@demo.example'])->id);
        $item = Item::create(['number' => 'LT-SPIIDER', 'kind' => 'item', 'name' => 'Robe Spiider', 'serial' => true, 'day_price_cents' => 6500]);
        $item->assets()->create(['code' => 'LT-0001', 'status' => 'available', 'location' => 'GA1B1']);
        Item::create(['number' => 'RIG-CLAMP', 'kind' => 'item', 'name' => 'Clamp', 'serial' => false, 'stock_qty' => 20]);

        $check = (new LinkCheck(3000, 2))->run($admin, [url('/dashboard'), url('/warehouse/labels'), url('/w/scan'), url('/dry-hire/labels?item='.$item->id), url('/settings/warehouse-locations/labels?rack=GA1'), url('/call?number=%2B3292000000&name=Jo')]);
        if ($out = getenv('LINKCHECK_OUT')) {
            file_put_contents($out, implode(PHP_EOL, $check->visited));
        }
        $this->assertGreaterThan(50, $check->opened);
        $this->assertSame([], $check->lines(), implode("\n", $check->lines()));
    }

    public function test_every_link_in_the_member_portal_and_on_the_website_works(): void
    {
        $member = \App\Models\Member::factory()->create(['status' => \App\Models\Member::ACTIVE, 'email_verified_at' => now()]);
        $member->acceptCurrentTerms();
        $portal = (new LinkCheck(1500, 2))->run($member, [url('/afrit6/portal')], 'member');
        $this->assertSame([], $portal->lines(), implode("\n", $portal->lines()));
        $this->assertGreaterThan(3, $portal->opened);

        $site = (new LinkCheck(1500, 2))->run(null, [route('site.home'), route('site.account.login')]);
        $this->assertSame([], $site->lines(), implode("\n", $site->lines()));
        $co = \App\Models\Company::create(['name' => 'Demo BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'country' => 'BE']);
        $customer = \App\Models\Web\CustomerAccount::create(['email' => 'jo@demo.example', 'password' => 'secret-secret', 'first_name' => 'Jo', 'status' => 'active', 'email_verified_at' => now(), 'company_id' => $co->id, 'role' => 'main']);
        $mine = (new LinkCheck(1500, 2))->run($customer, [route('site.account')], 'customer');
        $this->assertSame([], $mine->lines(), implode("\n", $mine->lines()));
    }

    /** The server has PHP short tags on: "<?xml" in a view would break the page there (not in tests). */
    public function test_no_view_starts_php_by_accident(): void
    {
        $bad = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if (str_ends_with($f, '.blade.php') && preg_match('/<\?(?!php|=)/', file_get_contents($f))) {
                $bad[] = (string) $f;
            }
        }
        $this->assertSame([], $bad);
    }
}
