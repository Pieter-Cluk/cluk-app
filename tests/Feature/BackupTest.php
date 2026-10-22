<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Backup\Backups;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->dir = sys_get_temp_dir().'/cluk-backup-test-'.uniqid();
        config(['cluk.backup_path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Storage::disk('local')->delete('logos/test.png');
        parent::tearDown();
    }

    public function test_backup_and_restore_the_database_and_files(): void
    {
        Company::create(['name' => "Demo Agency BV; with a\nnew line and 'quotes'", 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'nl']);
        Storage::disk('local')->put('logos/test.png', 'PNGDATA');

        $this->artisan('cluk:backup')->assertSuccessful();
        $db = Backups::list('db');
        $files = Backups::list('files');
        $this->assertCount(1, $db);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sql.gz', $db[0]);
        $this->assertGreaterThan(0, filesize($db[0]));
        [$tables, $inserts, $complete] = Backups::check($db[0]);
        $this->assertTrue($complete);
        $this->assertGreaterThan(30, $tables);

        // Things change …
        Company::query()->delete();
        Company::create(['name' => 'Something new', 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => 'en']);
        Storage::disk('local')->delete('logos/test.png');

        // … and come back.
        $this->artisan('cluk:restore', ['backup' => basename($db[0])])->assertFailed(); // needs --force
        Backups::restoreDatabase($db[0]);
        $this->assertSame(["Demo Agency BV; with a\nnew line and 'quotes'"], Company::pluck('name')->all());
        Backups::restoreFiles($files[0]);
        $this->assertSame('PNGDATA', Storage::disk('local')->get('logos/test.png'));
    }

    public function test_keeps_two_recent_plus_seven_days_and_two_file_archives(): void
    {
        // Twice a day for 10 days.
        foreach (range(10, 1) as $daysAgo) {
            foreach ([6, 18] as $hour) {
                Backups::run(true, Carbon::now()->subDays($daysAgo)->setTime($hour, 0));
            }
        }
        $db = array_map('basename', Backups::list('db'));
        $this->assertCount(8, $db, 'the last 2 + one per day for 7 days (the newest day counted once)');
        $this->assertSame(now()->subDay()->format('Ymd').'-1800', substr($db[0], 8, 13));
        $this->assertSame(now()->subDay()->format('Ymd').'-0600', substr($db[1], 8, 13));
        $this->assertCount(2, Backups::list('files'));
    }
}
