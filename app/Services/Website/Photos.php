<?php

namespace App\Services\Website;

use Illuminate\Support\Facades\Storage;

/** Web-sized JPEG copies of uploaded photos for the public website, made once and kept. */
class Photos
{
    /** Path of a copy at most $width wide; the original when the server cannot resize; null when missing. */
    public static function web(?string $path, string $key, int $width): ?string
    {
        $disk = Storage::disk('local');
        if (! $path || ! $disk->exists($path)) {
            return null;
        }
        $src = $disk->path($path);
        if (! function_exists('imagecreatefromstring')) {
            return $src;
        }
        $rel = 'site-photos/'.$key.'-'.$width.'-'.substr(md5($path.'|'.filemtime($src)), 0, 12).'.jpg';
        if ($disk->exists($rel)) {
            return $disk->path($rel);
        }
        $img = @imagecreatefromstring((string) file_get_contents($src));
        if (! $img) {
            return $src;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $nw = min($width, $w);
        $nh = max(1, (int) round($h * $nw / max(1, $w)));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $disk->makeDirectory('site-photos');
        imagejpeg($out, $disk->path($rel), 82);
        imagedestroy($img);
        imagedestroy($out);

        return $disk->path($rel);
    }
}
