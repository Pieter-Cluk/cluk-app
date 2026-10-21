<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** QR code as inline SVG (black squares, no background) for labels and screens. */
class QrSvg
{
    public static function make(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
        ]);

        return (new QRCode($options))->render($data);
    }
}
