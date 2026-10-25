<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Sales\SalesTask;
use App\Models\User;
use App\Models\Web\CustomerAccount;
use App\Models\Website\Download;
use App\Models\Website\DownloadLog;
use App\Services\Sales\CommercialTasks;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Website downloads: documents managed in Media › Website, and who downloaded them. */
class WebDownloadsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Company $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->editor = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en', 'is_commercial' => true]);
        $this->editor->extraRights()->create(['right' => 'website.edit']);
        $this->customer = Company::create(['name' => 'Demo Agency BV', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en', 'country' => 'BE', 'account_owner_id' => $this->editor->id]);
        $jan = Contact::create(['first_name' => 'Jan', 'last_name' => 'Peeters', 'email' => 'jan@demo.example']);
        $this->customer->contacts()->attach($jan->id, ['is_main' => true]);
    }

    private function upload(string $access = 'email', string $title = 'Truss brochure'): Download
    {
        $this->actingAs($this->editor, 'web')->post(route('website.downloads.store'), [
            'file' => UploadedFile::fake()->create('TAF Truss Brochure 2026.pdf', 800, 'application/pdf'),
            'title' => ['en' => $title, 'nl' => 'Trussbrochure'], 'description' => ['en' => 'All truss series with load tables.'], 'access' => $access, 'category' => 'brochures', 'active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Download::where('title->en', $title)->firstOrFail();
    }

    public function test_documents_are_managed_and_shown_with_a_footer_link(): void
    {
        $d = $this->upload();
        Storage::disk('local')->assertExists($d->path);
        $this->assertSame(['TAF Truss Brochure 2026.pdf', 'PDF'], [$d->original_name, $d->type()]);
        $this->get('/site')->assertSee(route('site.downloads'), false);
        $this->get('/site/downloads')->assertOk()->assertSee('Brochures')->assertSee('Truss brochure')->assertSee('All truss series')->assertSee(route('site.downloads.file', $d->id), false);
        $this->get('/site/downloads?lang=nl')->assertSee('Trussbrochure');
        $this->actingAs($this->editor, 'web')->get(route('website.index', ['tab' => 'downloads']))->assertOk()->assertSee('Truss brochure')->assertSee('Not downloaded yet')->assertDontSee('Seconds per slide');
        // Switched off: gone from the website.
        $this->actingAs($this->editor, 'web')->post(route('website.downloads.update', $d), ['title' => ['en' => 'Truss brochure'], 'access' => 'email', 'category' => 'brochures', 'active' => 0])->assertSessionHasNoErrors();
        $this->get('/site/downloads')->assertDontSee('Truss brochure');
        $this->get(route('site.downloads.file', $d->id))->assertNotFound();
        $this->actingAs($this->editor, 'web')->delete(route('website.downloads.destroy', $d))->assertRedirect();
        Storage::disk('local')->assertMissing($d->path);
        $this->assertSame(3, AuditLog::where('action', 'like', 'website.download_%')->count());
        // Only with the right.
        $other = User::factory()->create(['role_id' => Role::where('slug', 'sales')->value('id'), 'locale' => 'en']);
        $this->actingAs($other, 'web')->post(route('website.downloads.store'), ['title' => ['en' => 'x'], 'access' => 'open'])->assertForbidden();
    }

    public function test_name_and_email_first_then_we_know_who_downloaded(): void
    {
        $d = $this->upload('email');
        $this->get(route('site.downloads.file', $d->id))->assertOk()->assertSee('Tell us who you are')->assertSee('name="email"', false);
        $this->post(route('site.downloads.ask', $d->id), ['name' => 'Jan Peeters', 'email' => 'JAN@demo.example'])->assertRedirect(route('site.downloads.file', $d->id));
        $this->get(route('site.downloads.file', $d->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $log = DownloadLog::firstOrFail();
        $this->assertSame([$this->customer->id, 'jan@demo.example', 'Jan Peeters'], [$log->company_id, $log->email, $log->name]);
        $this->assertSame(1, $d->fresh()->downloads);
        // A second document: no questions this visit.
        $d2 = $this->upload('email', 'Price list');
        $this->get(route('site.downloads.file', $d2->id))->assertOk();

        // Staff see who, linked to the company; the company card and the owner's commercial tasks show it too.
        $this->actingAs($this->editor, 'web')->get(route('website.downloads.log'))->assertOk()->assertSee('Jan Peeters')->assertSee('Demo Agency BV')->assertSee('Price list');
        $this->actingAs($this->editor, 'web')->get(route('website.downloads.log', ['doc' => $d->id]))->assertOk()->assertDontSee('<td>Price list</td>', false);
        $this->actingAs($this->editor, 'web')->get(route('companies.show', $this->customer))->assertSee('Truss brochure');
        CommercialTasks::refresh();
        $t = SalesTask::where('kind', 'web')->where('company_id', $this->customer->id)->firstOrFail();
        $this->assertSame($this->editor->id, $t->owner_id);
        $this->assertEqualsCanonicalizing(['Truss brochure', 'Price list'], $t->data['downloads']);
        $this->actingAs($this->editor, 'web')->get('/commercial-tasks')->assertSee('downloaded');

        // Someone unknown: kept with what they filled in, with a quick "make company".
        $this->flushSession();
        $this->post(route('site.downloads.ask', $d->id), ['name' => 'Eva', 'email' => 'eva@new.example', 'company' => 'New Events BV']);
        $this->get(route('site.downloads.file', $d->id))->assertOk();
        $this->actingAs($this->editor, 'web')->get(route('website.downloads.log'))->assertSee('New Events BV')->assertSee('make company');
        // Bots filling the hidden field get nothing.
        $this->flushSession();
        $this->post(route('site.downloads.ask', $d->id), ['name' => 'x', 'email' => 'x@y.zz', 'website_url' => 'spam'])->assertRedirect(route('site.downloads'));
        $this->assertSame(3, DownloadLog::count());
    }

    public function test_open_and_login_only_documents(): void
    {
        $open = $this->upload('open', 'Rider template');
        $this->get(route('site.downloads.file', $open->id))->assertOk();
        $this->assertNull(DownloadLog::firstOrFail()->email); // anonymous, still counted

        $only = $this->upload('login', 'Customer price list');
        $this->get(route('site.downloads.file', $only->id))->assertRedirect(route('site.account.login'));
        $a = CustomerAccount::create(['email' => 'jan@demo.example', 'password' => Hash::make('secret-pass-1'), 'first_name' => 'Jan', 'last_name' => 'Peeters', 'company_id' => $this->customer->id, 'status' => 'active', 'locale' => 'en']);
        $this->actingAs($a, 'customer')->get(route('site.downloads.file', $only->id))->assertOk();
        $log = DownloadLog::where('download_id', $only->id)->firstOrFail();
        $this->assertSame([$a->id, $this->customer->id], [$log->customer_account_id, $log->company_id]);
    }

    public function test_order_by_number_and_replace_keeps_the_link(): void
    {
        $a = $this->upload('open', 'A');
        $b = $this->upload('open', 'B');
        $c = $this->upload('open', 'C');
        $this->actingAs($this->editor, 'web')->post(route('website.downloads.update', $c), ['title' => ['en' => 'C'], 'access' => 'open', 'category' => 'forms', 'locale' => 'nl', 'active' => 1, 'place' => 1])->assertSessionHasNoErrors();
        $this->assertSame([$c->id, $a->id, $b->id], Download::orderBy('sort')->pluck('id')->all());
        $this->assertSame(['forms', 'nl'], [$c->fresh()->category, $c->fresh()->locale]);
        $old = $a->path;
        $this->actingAs($this->editor, 'web')->post(route('website.downloads.update', $a), ['title' => ['en' => 'A'], 'access' => 'open', 'category' => 'brochures', 'active' => 1,
            'file' => UploadedFile::fake()->create('A v2.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->assertSame('A v2.xlsx', $a->fresh()->original_name);
        $this->get(route('site.downloads.file', $a->id))->assertOk(); // same link, new file
        $this->actingAs($this->editor, 'web')->post(route('website.downloads.store'), ['file' => UploadedFile::fake()->create('x.exe', 10), 'title' => ['en' => 'x'], 'access' => 'open', 'category' => 'other'])->assertSessionHasErrors('file');
        $this->actingAs($this->editor, 'web')->post(route('website.downloads.store'), ['file' => UploadedFile::fake()->create('x.pdf', 10), 'title' => ['nl' => 'alleen NL'], 'access' => 'open', 'category' => 'other'])->assertSessionHasErrors('title.en');
    }
}
