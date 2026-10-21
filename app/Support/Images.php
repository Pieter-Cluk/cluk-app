<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores pictures on the private disk, scaled down so pages stay fast:
 * photos as JPEG, logos keep transparency as PNG. Phone photos of 10 MB
 * become a few hundred kB.
 */
class Images
{
    /** @return string the stored path on the 'local' disk */
    public static function store(UploadedFile|string $source, string $dir, int $maxPx = 1600, bool $keepAlpha = false): string
    {
        $data = $source instanceof UploadedFile ? (string) file_get_contents($source->getRealPath()) : $source;
        if (! function_exists('imagecreatefromstring')) {
            // No GD: keep the original as it is.
            $ext = $source instanceof UploadedFile ? ($source->guessExtension() ?: 'jpg') : 'jpg';
            $path = $dir.'/'.Str::random(32).'.'.$ext;
            Storage::disk('local')->put($path, $data);

            return $path;
        }
        $img = @imagecreatefromstring($data);
        if ($img === false) {
            throw new RuntimeException('Not an image');
        }
        $img = self::orient($img, $data);
        [$w, $h] = [imagesx($img), imagesy($img)];
        $scale = min(1, $maxPx / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
        }
        ob_start();
        if ($keepAlpha) {
            imagesavealpha($img, true);
            imagepng($img, null, 6);
            $ext = 'png';
        } else {
            // Flatten transparency on white for JPEG.
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imagejpeg($flat, null, 85);
            imagedestroy($flat);
            $ext = 'jpg';
        }
        $out = (string) ob_get_clean();
        imagedestroy($img);
        $path = $dir.'/'.Str::random(32).'.'.$ext;
        Storage::disk('local')->put($path, $out);

        return $path;
    }

    /** Phone photos carry their rotation in EXIF: apply it. */
    private static function orient(\GdImage $img, string $data): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($data, "\xFF\xD8")) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($data));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0
        };

        return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /** Upload rule for every picture field. */
    public static function rule(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'];
    }
}
