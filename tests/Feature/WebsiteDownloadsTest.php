<?php

namespace Tests\Feature;

use App\Models\Website\Download;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Website › footer › Downloads. */
class WebsiteDownloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_by_group_in_the_visitors_language(): void
    {
        Storage::disk('local')->put('web-downloads/prices-2027.pdf', '%PDF-1.4 test');
        $d = Download::create(['category' => 'pricelists', 'title' => ['en' => 'Dry-hire price list 2027', 'nl' => 'Prijslijst dry-hire 2027'], 'path' => 'web-downloads/prices-2027.pdf',
            'original_name' => 'CLUK prices 2027.pdf', 'mime' => 'application/pdf', 'size' => 1200000, 'locale' => 'nl', 'active' => true]);
        Download::create(['category' => 'forms', 'title' => ['en' => 'Old form'], 'path' => 'web-downloads/old.pdf', 'original_name' => 'old.pdf', 'active' => false]);

        $this->get('/site')->assertOk()->assertSee('/site/downloads', false);
        $this->get('/site/downloads')->assertOk()->assertSee('Price lists')->assertSee('Dry-hire price list 2027')->assertSee('1,1 MB')->assertSee('General terms and conditions')
            ->assertDontSee('Old form')->assertDontSee('Forms</h2>', false);
        $this->get('/site/downloads?lang=nl')->assertOk()->assertSee('Prijslijsten')->assertSee('Prijslijst dry-hire 2027');

        $this->get('/site/downloads/'.$d->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $d->fresh()->downloads);
        $this->get('/site/downloads/'.Download::where('active', false)->value('id'))->assertNotFound();
    }
}
