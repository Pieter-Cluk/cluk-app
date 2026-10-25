<?php

namespace App\Http\Controllers\Site;

use App\Services\Website\SiteSettings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Pictures and videos uploaded in Media › Website (videos can be streamed: range requests). */
class WebMediaController extends SiteController
{
    public function show(string $f): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{32}\.(jpg|jpeg|png|webp|gif|mp4|webm)$/', $f), 404);
        $file = SiteSettings::publicMedia($f);
        abort_unless($file, 404);
        $type = match (pathinfo($f, PATHINFO_EXTENSION)) {
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg'
        };

        return response()->file($file, ['Content-Type' => $type, 'Cache-Control' => 'public, max-age=2592000, immutable']);
    }
}
