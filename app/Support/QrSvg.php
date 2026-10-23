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

    /**
     * The CLUK "c" as on our equipment: a thick c, open to the right with
     * straight cut ends, inside a thin outer arc with the same opening.
     * Proportions taken from the mark (outer arc = 1).
     */
    private static function letter(int $size, int $space, string $color): string
    {
        $c = $size / 2;
        $outer = $space / 2 - 0.45;                     // keep a little white around it
        $thin = max(0.35, $outer * 0.035);              // the thin arc must still print on a 25 mm label
        $rThin = $outer - $thin / 2;
        $cOut = $outer * 0.89;                          // the thick c: outer edge
        $cIn = $outer * 0.57;                           //                inner edge
        $rC = ($cOut + $cIn) / 2;
        $arc = function (float $r, float $deg) use ($c) {
            $a = deg2rad($deg);

            return [round($c + $r * cos($a), 3), round($c - $r * sin($a), 3), round($c + $r * sin($a), 3)];
        };
        [$x1, $y1a, $y1b] = $arc($rC, 40);
        [$x2, $y2a, $y2b] = $arc($rThin, 39);
        $col = e($color);

        return '<g fill="none" stroke="'.$col.'" stroke-linecap="butt">'
            .'<path d="M'.$x1.' '.$y1a.' A'.round($rC, 3).' '.round($rC, 3).' 0 1 0 '.$x1.' '.$y1b.'" stroke-width="'.round($cOut - $cIn, 3).'"/>'
            .'<path d="M'.$x2.' '.$y2a.' A'.round($rThin, 3).' '.round($rThin, 3).' 0 1 0 '.$x2.' '.$y2b.'" stroke-width="'.round($thin, 3).'"/>'
            .'</g>';
    }
}
