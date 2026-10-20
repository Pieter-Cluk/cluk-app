<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use App\Services\Vies;
use App\Support\SheetReader;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create(['first_name' => 'Pieter', 'last_name' => 'Van de Velde', 'role_id' => Role::where('slug', 'admin')->value('id')]);
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    /** A small real .xlsx with one sheet per name (inline strings, like Excel can write). */
    private function xlsx(array $sheets): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $wb = '';
        $rels = '';
        $i = 0;
        foreach ($sheets as $name => $rows) {
            $i++;
            $wb .= '<sheet name="'.htmlspecialchars($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            $data = '';
            foreach ($rows as $r => $row) {
                $data .= '<row r="'.($r + 1).'">';
                foreach (array_values($row) as $c => $value) {
                    if ($value === null) {
                        continue;
                    }
                    $ref = chr(65 + $c).($r + 1);
                    $data .= is_int($value)
                        ? '<c r="'.$ref.'"><v>'.$value.'</v></c>'
                        : '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value).'</t></is></c>';
                }
                $data .= '</row>';
            }
            $zip->addFromString("xl/worksheets/sheet{$i}.xml", '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$data.'</sheetData></worksheet>');
        }
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$wb.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->close();

        return new UploadedFile($path, 'clients.xlsx', null, null, true);
    }

    private function csv(string $content, string $name = 'companies.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_only_settings_editors_can_import(): void
    {
        $manager = User::factory()->create(['role_id' => Role::where('slug', 'manager')->value('id')]);

        $this->actingAs($manager)->get('/settings/import')->assertForbidden();
        $this->actingAs($this->admin)->get('/settings/import')->assertOk();
        $this->actingAs($this->admin)->get('/settings/import/template')->assertOk()->assertDownload('CLUK_Import_Templates.xlsx');
    }

    public function test_the_reader_reads_the_real_template(): void
    {
        $sheets = SheetReader::read(resource_path('templates/CLUK_Import_Templates.xlsx'), 'xlsx');

        $this->assertArrayHasKey('Companies', $sheets);
        $this->assertSame('Company name', $sheets['Companies'][0][0]);
        $this->assertSame('9870', $sheets['Companies'][1][3]);
        $this->assertSame('Sarah', $sheets['Contacts'][1][0]);
    }

    public function test_the_templates_example_rows_are_skipped(): void
    {
        $file = new UploadedFile(resource_path('templates/CLUK_Import_Templates.xlsx'), 'CLUK_Import_Templates.xlsx', null, null, true);

        $response = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $file])->assertOk();

        $reports = $response->viewData('reports');
        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1], array_intersect_key($reports['companies'], array_flip(['created', 'updated', 'skipped'])));
        $this->assertSame(1, $reports['contacts']['skipped']);
        $this->assertSame(0, Company::count());
    }

    public function test_a_dutch_csv_is_checked_first_and_imported_on_confirmation(): void
    {
        $csv = "Bedrijfsnaam;Btw-nummer;Gemeente;Land;Relatie;Taal;Accountbeheerder\n"
            ."Demo Agency;BE 1010.948.054;Zulte;België;Klant;NL;Pieter Van de Velde\n"
            ."Demo Light Hire;DE123456789;Köln;Duitsland;Leverancier;EN;\n"
            ."Demo Typo;BE0123456789;Gent;BE;Beide;FR;Somebody Else\n";

        $preview = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $this->csv($csv)])->assertOk();

        $this->assertSame(3, $preview->viewData('reports')['companies']['created']);
        $this->assertSame(0, Company::count(), 'the check saves nothing');
        $preview->assertSee('Demo Typo')->assertSee('Somebody Else');

        $this->actingAs($this->admin)->post('/settings/import/run', ['token' => $preview->viewData('token')])->assertOk();

        $this->assertSame(3, Company::count());
        $agency = Company::where('name', 'Demo Agency')->firstOrFail();
        $this->assertSame('BE1010948054', $agency->vat_number);
        $this->assertSame('BE', $agency->country);
        $this->assertTrue($agency->is_customer);
        $this->assertFalse($agency->is_supplier);
        $this->assertTrue($agency->accountOwner->is($this->admin));

        $hire = Company::where('name', 'Demo Light Hire')->firstOrFail();
        $this->assertSame(['DE', 'en', false, true], [$hire->country, $hire->language, $hire->is_customer, $hire->is_supplier]);

        $typo = Company::where('name', 'Demo Typo')->firstOrFail();
        $this->assertNull($typo->vat_number, 'a wrong VAT number is left out');
        $this->assertTrue($typo->is_customer && $typo->is_supplier);

        $this->assertSame(1, AuditLog::where('action', 'import.done')->count());

        // The same file again changes nothing.
        $again = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $this->csv($csv)]);
        $this->assertSame(['created' => 0, 'updated' => 0, 'skipped' => 3], array_intersect_key($again->viewData('reports')['companies'], array_flip(['created', 'updated', 'skipped'])));
    }

    public function test_a_token_can_only_be_used_once(): void
    {
        $preview = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $this->csv("Company name\nDemo One\n")]);
        $token = $preview->viewData('token');

        $this->actingAs($this->admin)->post('/settings/import/run', ['token' => $token])->assertOk();
        $this->actingAs($this->admin)->post('/settings/import/run', ['token' => $token])->assertNotFound();
        $this->assertSame(1, Company::count());
    }

    public function test_a_workbook_imports_companies_then_links_contacts(): void
    {
        $file = $this->xlsx([
            'Companies' => [
                ['Company name', 'VAT number', 'City', 'Country', 'Relationship', 'Payment terms (days)'],
                ['Demo Agency', 'BE1010948054', 'Zulte', 'BE', 'Customer', 21],
                ['Demo Production', null, 'Antwerpen', 'BE', 'Customer', null],
            ],
            'Contacts' => [
                ['First name', 'Last name', 'Company VAT number', 'Company name', 'Job title', 'Email', 'Mobile', 'Language', 'Newsletter consent'],
                ['Sarah', 'Peeters', 'BE 1010.948.054', null, 'Event manager', 'Sarah@demo-agency.be', '+32 470 00 00 01', 'NL', 'Yes'],
                ['Sarah', 'Peeters', null, 'demo production', 'Freelance producer', 'sarah@demo-agency.be', null, null, null],
                ['Tom', 'De Smet', null, 'Unknown BV', null, 'tom@unknown.be', null, null, 'No'],
            ],
        ]);

        $preview = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $file])->assertOk();
        $this->actingAs($this->admin)->post('/settings/import/run', ['token' => $preview->viewData('token')])->assertOk();

        $agency = Company::where('name', 'Demo Agency')->firstOrFail();
        $this->assertSame(21, $agency->customer_payment_days);

        $sarah = Contact::where('email', 'sarah@demo-agency.be')->firstOrFail();
        $this->assertSame(1, Contact::where('first_name', 'Sarah')->count(), 'same email = same person');
        $this->assertTrue($sarah->newsletter);
        $this->assertSame(['Demo Agency', 'Demo Production'], $sarah->companies()->orderBy('name')->pluck('name')->all());
        $this->assertSame('Event manager', $agency->contacts()->first()->pivot->job_title);

        $tom = Contact::where('email', 'tom@unknown.be')->firstOrFail();
        $this->assertSame(0, $tom->companies()->count());
        $this->assertFalse($tom->newsletter);
    }

    public function test_updates_never_wipe_data_and_add_roles(): void
    {
        $existing = Company::factory()->create(['name' => 'Demo Rental', 'vat_number' => 'BE1010948054', 'city' => 'Gent', 'phone' => '+32 9 111 11 11', 'is_customer' => true]);

        $preview = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $this->csv("Company name,VAT number,City,Phone,Relationship\nDemo Rental NV,BE1010948054,,,Supplier\n")]);
        $this->assertSame(1, $preview->viewData('reports')['companies']['updated']);
        $this->actingAs($this->admin)->post('/settings/import/run', ['token' => $preview->viewData('token')]);

        $existing->refresh();
        $this->assertSame('Demo Rental NV', $existing->name);
        $this->assertSame('Gent', $existing->city);
        $this->assertSame('+32 9 111 11 11', $existing->phone);
        $this->assertTrue($existing->is_customer && $existing->is_supplier);
        $this->assertSame(1, Company::count());
    }

    public function test_a_missing_name_column_or_unreadable_file_is_explained(): void
    {
        $preview = $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => $this->csv("City,Country\nGent,BE\n"), 'kind' => 'companies']);
        $this->assertSame('error', $preview->viewData('reports')['companies']['messages'][0]['level']);

        $this->actingAs($this->admin)->post('/settings/import/preview', ['file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'not a zip')])
            ->assertSessionHasErrors('file');
    }

    public function test_imported_vat_numbers_are_checked_in_vies_in_batches(): void
    {
        Http::fake([Vies::URL => Http::response(['valid' => true, 'name' => 'DEMO', 'address' => 'Somewhere'])]);
        Company::factory()->create(['vat_number' => 'BE1010948054']);
        Company::factory()->create(['vat_number' => 'NL123456789B01']);
        Company::factory()->create(['vat_number' => 'GB123456789']); // outside the EU: not checked

        $this->actingAs($this->admin)->get('/settings/import')->assertSee('2 imported companies');
        $this->actingAs($this->admin)->post('/settings/import/vies')->assertRedirect('/settings/import');

        $this->assertSame(2, Company::where('vies_status', 'valid')->count());
        Http::assertSentCount(2);
    }

    public function test_the_screen_renders_in_every_language(): void
    {
        Company::factory()->create(['vat_number' => 'BE1010948054']);
        foreach (array_keys(config('cluk.locales')) as $locale) {
            $this->admin->forceFill(['locale' => $locale])->save();
            $html = $this->actingAs($this->admin->fresh())->get('/settings/import')->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\b(import|settings|users|nav)\.[a-z_]+(\.[a-z_]+)?\b/', strip_tags($html), "untranslated text ({$locale})");
        }
    }
}
