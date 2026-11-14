<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The dB meter is a static tool in public/tools/dbmeter, copied to cluk.live by the deploy. */
class DbMeterFilesTest extends TestCase
{
    public function test_the_meter_files_are_in_place(): void
    {
        $dir = public_path('tools/dbmeter');
        foreach (['index.html', 'relay.php', 'manifest.webmanifest', 'icon.svg', 'lib/html2canvas.min.js', 'lib/jspdf.umd.min.js', 'lib/qrcode.js', 'data/.htaccess'] as $f) {
            $this->assertFileExists($dir.'/'.$f);
        }
        $this->assertStringContainsString('DirectoryIndex index.html', file_get_contents($dir.'/.htaccess'));
        $this->assertStringContainsString('Require all denied', file_get_contents($dir.'/data/.htaccess'));
        // cluk.live keeps /tools/ for itself and forwards the rest; the app web root leaves /tools/ alone too.
        $this->assertStringContainsString('RewriteRule ^tools/ - [L]', file_get_contents(base_path('deploy/cluk-live.htaccess')));
        $this->assertStringContainsString('!^/tools/', file_get_contents(base_path('deploy/htaccess-root')));
        $this->assertStringContainsString('public/tools/dbmeter/data/*.json', file_get_contents(base_path('.gitignore')));
    }
}
