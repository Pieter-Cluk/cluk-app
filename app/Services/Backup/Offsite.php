<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A copy of each backup outside one.com: in a folder of CLUK's own Microsoft 365 (OneDrive of the person set in
 * BACKUP_OFFSITE_USER), through Microsoft Graph with the app registration "CLUK app" (application permission
 * Files.ReadWrite.All). Kept there: the last 14 database backups and the last 4 file archives.
 */
class Offsite
{
    public const KEEP = ['db' => 14, 'files' => 4];

    private const CHUNK = 10 * 1024 * 1024; // a multiple of 320 KiB, as Graph wants

    public static function on(): bool
    {
        return filled(config('cluk.backup_offsite_user')) && filled(config('services.microsoft.client_id'));
    }

    private static function base(): string
    {
        return 'https://graph.microsoft.com/v1.0/users/'.rawurlencode((string) config('cluk.backup_offsite_user')).'/drive/root:/'.rawurlencode((string) config('cluk.backup_offsite_folder', 'CLUK app backups'));
    }

    /** Upload one backup file (resumable, in pieces). Returns the size sent. */
    public static function upload(string $path): int
    {
        $token = self::token();
        $name = basename($path);
        $session = Http::withToken($token)->timeout(30)->post(self::base().'/'.rawurlencode($name).':/createUploadSession', [
            'item' => ['@microsoft.graph.conflictBehavior' => 'replace'],
        ]);
        if (! $session->successful() || ! ($url = $session->json('uploadUrl'))) {
            throw new RuntimeException('OneDrive upload could not start: HTTP '.$session->status().' '.$session->json('error.code'));
        }
        $size = filesize($path);
        $fh = fopen($path, 'rb');
        try {
            for ($from = 0; $from < $size; $from += self::CHUNK) {
                $bytes = fread($fh, self::CHUNK);
                $to = $from + strlen($bytes) - 1;
                $r = Http::withHeaders(['Content-Range' => "bytes $from-$to/$size"])->timeout(120)->withBody($bytes, 'application/octet-stream')->put($url);
                if (! $r->successful()) {
                    throw new RuntimeException('OneDrive upload failed at byte '.$from.': HTTP '.$r->status());
                }
            }
        } finally {
            fclose($fh);
        }

        return $size;
    }

    /** Remove older copies in the OneDrive folder. Returns the names removed. */
    public static function prune(): array
    {
        $token = self::token();
        $r = Http::withToken($token)->timeout(30)->get(self::base().':/children', ['$select' => 'id,name', '$top' => 200]);
        if (! $r->successful()) {
            return [];
        }
        $gone = [];
        foreach (self::KEEP as $kind => $keep) {
            $items = collect($r->json('value', []))->filter(fn ($i) => str_starts_with($i['name'], 'cluk-'.$kind.'-'))->sortByDesc('name')->values();
            foreach ($items->slice($keep) as $i) {
                if (Http::withToken($token)->timeout(30)->delete('https://graph.microsoft.com/v1.0/users/'.rawurlencode((string) config('cluk.backup_offsite_user')).'/drive/items/'.$i['id'])->successful()) {
                    $gone[] = $i['name'];
                }
            }
        }

        return $gone;
    }

    private static function token(): string
    {
        return Cache::remember('graph-backup-token', 3000, function () {
            $r = Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/'.config('services.microsoft.tenant').'/oauth2/v2.0/token', [
                'client_id' => config('services.microsoft.client_id'), 'client_secret' => config('services.microsoft.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials',
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                throw new RuntimeException('Microsoft Graph token failed: '.$r->json('error'));
            }

            return $r->json('access_token');
        });
    }
}
