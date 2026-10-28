<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Paying an invoice by scanning: the European "EPC QR code" (SEPA credit transfer, also called the
 * GiroCode or Betaal-QR) that Belgian and other European bank apps read, with the Belgian structured
 * communication (+++123/4567/89012+++) so the payment is matched to the invoice.
 */
class PaymentQr
{
    /** Belgian structured communication from the digits of the invoice number: 10 digits + 2 check digits (mod 97). */
    public static function ogm(string $invoiceNumber): string
    {
        $digits = preg_replace('/\D/', '', $invoiceNumber);
        $base = str_pad(substr($digits, -10), 10, '0', STR_PAD_LEFT);
        $check = ((int) $base % 97) ?: 97;
        $all = $base.str_pad((string) $check, 2, '0', STR_PAD_LEFT);

        return '+++'.substr($all, 0, 3).'/'.substr($all, 3, 4).'/'.substr($all, 7, 5).'+++';
    }

    /** The text inside the code (EPC069-12, version 002). Null when it cannot be made. */
    public static function payload(string $name, string $iban, ?string $bic, int $cents, string $reference): ?string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if ($cents <= 0 || $cents > 99999999999 || ! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $iban)) {
            return null;
        }

        return implode("\n", [
            'BCD', '002', '1', 'SCT',
            strtoupper(preg_replace('/\s+/', '', (string) $bic)),
            mb_substr(trim($name), 0, 70),
            $iban,
            'EUR'.number_format($cents / 100, 2, '.', ''),
            '',            // purpose
            '',            // structured (ISO 11649 RF) reference
            mb_substr($reference, 0, 140), // the Belgian structured communication, read by Belgian bank apps
        ]);
    }

    /** PNG as a data: URL (for the PDF). */
    public static function png(string $payload): string
    {
        $o = new QROptions([
            'outputInterface' => QRGdImagePNG::class, 'eccLevel' => EccLevel::M, 'scale' => 6, 'quietzoneSize' => 2,
            'outputBase64' => true, 'imageTransparent' => false,
        ]);

        return (new QRCode($o))->render($payload);
    }
}
