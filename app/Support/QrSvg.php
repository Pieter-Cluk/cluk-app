<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR code as inline SVG (black squares, no background) for labels and screens.
 * With the CLUK "c" in the middle: the code then uses the highest error
 * correction (30 %) and keeps a clear square in the centre for the letter, so
 * phones still read it.
 */
class QrSvg
{
    public static function make(string $data, bool $logo = false, string $color = '#000'): string
    {
        if (! $logo) {
            return (new QRCode(self::options(false)))->render($data);
        }
        $v = self::version($data);
        if ($v !== null) {
            $options = self::options(true, $v);
            $svg = (new QRCode($options))->render($data);
            preg_match('/viewBox="0 0 (\d+) /', $svg, $m);

            return str_replace('</svg>', self::letter((int) $m[1], $options->logoSpaceWidth, $color).'</svg>', $svg);
        }

        return (new QRCode(self::options(false)))->render($data);
    }

    /**
     * One size up from the smallest that holds the data, so the clear square
     * never eats into a full code: then it still reads reliably.
     */
    public static function version(string $data): ?int
    {
        foreach (range(4, 14) as $v) {
            try {
                (new QRCode(self::options(true, $v)))->render($data);

                return $v + 1;
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** Version $v has 17 + 4v modules; the clear square takes about a quarter of the width (odd, so it sits in the middle). */
    public static function options(bool $logo, int $v = -1, array $extra = []): QROptions
    {
        $space = $logo ? (int) round((17 + 4 * $v) * 0.25) | 1 : null;

        return new QROptions($extra + [
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => $logo ? EccLevel::H : EccLevel::M,
            'version' => $v,
            'addLogoSpace' => $logo,
            'logoSpaceWidth' => $space,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
        ]);
    }

    /** The lower-case "c" of the CLUK logo: a thick ring, open to the right. */
    private static function letter(int $size, int $space, string $color): string
    {
        $c = $size / 2;
        $stroke = $space * 0.22;
        $r = $space / 2 - $stroke / 2 - 0.55;
        $a = deg2rad(42);
        $x = round($c + $r * cos($a), 3);
        $y1 = round($c - $r * sin($a), 3);
        $y2 = round($c + $r * sin($a), 3);
        $r = round($r, 3);

        return '<path d="M'.$x.' '.$y1.' A'.$r.' '.$r.' 0 1 0 '.$x.' '.$y2.'" fill="none" stroke="'.e($color).'" stroke-width="'.round($stroke, 3).'"/>';
    }
}
