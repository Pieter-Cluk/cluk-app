<?php

namespace App\Services\Social;

use App\Models\Social\Plan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Pictures and videos of a planned post: kept on the server, reachable for the platforms through a signed link for a few days. */
class Media
{
    public const IMAGE_MAX_KB = 8192;

    public const VIDEO_MAX_KB = 512000;

    public static function store(UploadedFile $f): array
    {
        $video = str_starts_with((string) $f->getMimeType(), 'video/');
        $ext = strtolower($f->getClientOriginalExtension() ?: ($video ? 'mp4' : 'jpg'));
        $path = $f->storeAs('social', Str::uuid().'.'.$ext, 'local');

        return ['path' => $path, 'name' => mb_substr($f->getClientOriginalName(), 0, 120), 'type' => $video ? 'video' : 'image', 'size' => $f->getSize()];
    }

    /** A link the platforms can fetch the file from (valid 3 days, no login). */
    public static function url(Plan $plan, array $m): string
    {
        $i = array_search($m['path'], array_column($plan->media ?? [], 'path'), true);

        return URL::temporarySignedRoute('social.media', now()->addDays(3), ['plan' => $plan->id, 'i' => (int) $i, 'f' => basename($m['path'])]);
    }

    public static function mime(array $m): string
    {
        return match (strtolower(pathinfo($m['path'], PATHINFO_EXTENSION))) {
            'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
            default => 'image/jpeg',
        };
    }
}
