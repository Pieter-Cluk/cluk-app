<?php

namespace Tests\Feature;

use App\Services\Backup\Offsite;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackupOffsiteTest extends TestCase
{
    public function test_a_backup_is_sent_in_pieces_and_old_copies_are_removed(): void
    {
        config(['cluk.backup_offsite_user' => 'it@cluk.example', 'services.microsoft.client_id' => 'x', 'services.microsoft.client_secret' => 'y', 'services.microsoft.tenant' => 't']);
        $this->assertTrue(Offsite::on());
        $file = tempnam(sys_get_temp_dir(), 'bk');
        file_put_contents($file, str_repeat('a', 25 * 1024 * 1024)); // 25 MB: 3 pieces
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok']),
            '*createUploadSession' => Http::response(['uploadUrl' => 'https://upload.example/session']),
            'upload.example/*' => Http::response([], 202),
            '*children*' => Http::response(['value' => collect(range(1, 16))->map(fn ($n) => ['id' => 'i'.$n, 'name' => sprintf('cluk-db-202610%02d-0600.sql.gz', $n)])->all()]),
            'graph.microsoft.com/v1.0/users/*/drive/items/*' => Http::response([], 204),
        ]);

        $this->assertSame(25 * 1024 * 1024, Offsite::upload($file));
        Http::assertSentCount(1 + 1 + 3);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'it%40cluk.example/drive/root:/CLUK%20app%20backups/') && str_contains($r->url(), 'createUploadSession'));
        Http::assertSent(fn ($r) => $r->hasHeader('Content-Range', 'bytes 20971520-26214399/26214400'));
        $this->assertSame(['cluk-db-20261002-0600.sql.gz', 'cluk-db-20261001-0600.sql.gz'], Offsite::prune());
        @unlink($file);
    }
}
