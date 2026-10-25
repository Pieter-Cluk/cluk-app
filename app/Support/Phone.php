<?php

namespace App\Support;

/**
 * Phone numbers in one international format (+32 9 123 45 67), so a caller is
 * found however the number was typed: "09/123.45.67", "0032 9 123 45 67" and
 * "+32 (0)9 123 45 67" all become the same. A number without country code
 * gets the country of its company (Belgium when unknown).
 */
class Phone
{
    /** Country calling codes for the countries we work with most. */
    public const CODES = [
        'BE' => '32', 'NL' => '31', 'LU' => '352', 'FR' => '33', 'DE' => '49', 'GB' => '44', 'IE' => '353', 'ES' => '34', 'PT' => '351',
        'IT' => '39', 'AT' => '43', 'CH' => '41', 'CZ' => '420', 'SK' => '421', 'PL' => '48', 'HU' => '36', 'DK' => '45', 'SE' => '46',
        'NO' => '47', 'FI' => '358', 'GR' => '30', 'RO' => '40', 'BG' => '359', 'HR' => '385', 'SI' => '386', 'EE' => '372', 'LV' => '371',
        'LT' => '370', 'US' => '1', 'CA' => '1', 'AE' => '971', 'TR' => '90', 'MA' => '212', 'ZA' => '27', 'AU' => '61', 'BR' => '55',
        'MX' => '52', 'AR' => '54', 'CL' => '56', 'CO' => '57',
    ];

    /** The number written internationally ("+32 9 123 45 67"); unclear input is kept as typed. */
    public static function international(?string $raw, ?string $country = 'BE'): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/\(\s*0\s*\)/', ' ', $s);              // +32 (0)9 → +32 9
        $s = preg_replace('/[\s.\/\-()]+/', ' ', $s);              // separators → one space
        $s = trim($s);
        if (str_starts_with($s, '00')) {
            $s = '+'.ltrim(substr($s, 2));
        } elseif (str_starts_with($s, '0') && ($code = self::CODES[strtoupper((string) $country)] ?? self::CODES['BE'])) {
            $s = '+'.$code.' '.ltrim(substr($s, 1));
        }
        if (str_starts_with($s, '+')) {
            $s = '+'.ltrim(substr($s, 1));
            // "+32 9..." stays; "+3291..." gets a space after the country code.
            if (! str_contains(substr($s, 0, 6), ' ') && ($cc = self::codeOf(substr($s, 1)))) {
                $s = '+'.$cc.' '.substr($s, 1 + strlen($cc));
            }
            // Digits typed in one block: grouped the usual way, so they read easily.
            if (preg_match('/^\+(\d{1,3}) (\d{7,12})$/', $s, $m)) {
                $s = '+'.$m[1].' '.self::group($m[1], $m[2]);
            }
        }

        return mb_substr($s, 0, 40);
    }

    /** Digits only, with country code (e.g. 3291234567): what numbers are matched on. Null when too short. */
    public static function key(?string $raw, ?string $country = 'BE'): ?string
    {
        $int = self::international($raw, $country);
        if ($int === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $int);
        if (! str_starts_with($int, '+')) {
            // Typed without 0 or + (e.g. "9 123 45 67", or from a phone system): assume the country.
            $code = self::CODES[strtoupper((string) $country)] ?? '32';
            if (strlen($digits) >= 8 && strlen($digits) <= 10 && ! str_starts_with($digits, $code)) {
                $digits = $code.$digits;
            }
        }

        return strlen($digits) >= 8 ? substr($digits, 0, 20) : null;
    }

    /** A WhatsApp link for the number, or null. */
    public static function whatsapp(?string $raw, ?string $country = 'BE'): ?string
    {
        $k = self::key($raw, $country);

        return $k ? 'https://wa.me/'.$k : null;
    }

    /** For tel: links. */
    public static function dial(?string $raw, ?string $country = 'BE'): ?string
    {
        $k = self::key($raw, $country);

        return $k ? '+'.$k : (preg_replace('/[^0-9+]/', '', (string) $raw) ?: null);
    }

    /** Is this probably a mobile number (worth a WhatsApp button)? Belgian/Dutch/French mobiles are recognised; others count as maybe. */
    public static function maybeMobile(?string $raw, ?string $country = 'BE'): bool
    {
        $k = self::key($raw, $country);
        if (! $k) {
            return false;
        }

        return match (true) {
            str_starts_with($k, '32') => (bool) preg_match('/^324[5-9]\d{7}$/', $k),
            str_starts_with($k, '31') => str_starts_with($k, '316'),
            str_starts_with($k, '33') => (bool) preg_match('/^33[67]/', $k),
            default => true,
        };
    }

    private static function group(string $cc, string $n): string
    {
        $parts = match (true) {
            $cc === '32' && strlen($n) === 9 => [3, 2, 2, 2],                                // mobile 471 12 34 56
            $cc === '32' && strlen($n) === 8 && in_array($n[0], ['2', '3', '4', '9'], true) => [1, 3, 2, 2], // 9 123 45 67
            $cc === '32' && strlen($n) === 8 => [2, 2, 2, 2],                                // 56 12 34 56
            $cc === '31' && strlen($n) === 9 && $n[0] === '6' => [1, 8],                     // 6 12345678
            $cc === '33' && strlen($n) === 9 => [1, 2, 2, 2, 2],                             // 6 12 34 56 78
            $cc === '1' && strlen($n) === 10 => [3, 3, 4],                                  // 212 555 0100
            default => array_fill(0, (int) ceil(strlen($n) / 3), 3),
        };
        $out = [];
        $at = 0;
        foreach ($parts as $len) {
            if ($at >= strlen($n)) {
                break;
            }
            $out[] = substr($n, $at, $len);
            $at += $len;
        }
        if ($at < strlen($n)) {
            $out[] = substr($n, $at);
        }

        return implode(' ', $out);
    }

    private static function codeOf(string $digits): ?string
    {
        foreach ([3, 2, 1] as $len) {
            $c = substr($digits, 0, $len);
            if (in_array($c, self::CODES, true)) {
                return $c;
            }
        }

        return null;
    }
}
