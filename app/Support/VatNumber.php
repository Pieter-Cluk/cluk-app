<?php

namespace App\Support;

/** Cleaning, checking and printing VAT numbers. */
class VatNumber
{
    /** EU member states (ISO codes). Greece is "EL" in VAT numbers. */
    public const EU = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT',
        'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    public static function isEu(?string $country): bool
    {
        return in_array(strtoupper((string) $country), self::EU, true);
    }

    /** The prefix used in VAT numbers and by VIES for a country. */
    public static function prefix(string $country): string
    {
        $country = strtoupper($country);

        return $country === 'GR' ? 'EL' : $country;
    }

    /**
     * "be 0123.456.789" → "BE0123456789". A number typed without prefix
     * gets the prefix of the company's country.
     */
    public static function normalize(?string $number, string $country = 'BE'): ?string
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $number));
        if ($clean === '') {
            return null;
        }

        $full = preg_match('/^[A-Z]{2}/', $clean) ? $clean : self::prefix($country).$clean;

        // Old Belgian numbers have 9 digits: they get a leading 0 (BE 123.456.749 → BE0123456749).
        return preg_match('/^BE\d{9}$/', $full) ? 'BE0'.substr($full, 2) : $full;
    }

    /** Local check before asking VIES. Belgium: 10 digits with a mod-97 check. */
    public static function looksValid(string $normalized): bool
    {
        if (! preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $normalized)) {
            return false;
        }

        if (str_starts_with($normalized, 'BE')) {
            $digits = substr($normalized, 2);
            if (! preg_match('/^[01]\d{9}$/', $digits)) {
                return false;
            }

            return 97 - ((int) substr($digits, 0, 8) % 97) === (int) substr($digits, 8, 2);
        }

        return true;
    }

    /** "BE0123456789" → "BE 0123.456.789"; others with a space after the prefix. */
    public static function format(?string $normalized): string
    {
        if ($normalized === null || $normalized === '') {
            return '';
        }

        if (preg_match('/^BE(\d{4})(\d{3})(\d{3})$/', $normalized, $m)) {
            return "BE {$m[1]}.{$m[2]}.{$m[3]}";
        }

        return substr($normalized, 0, 2).' '.substr($normalized, 2);
    }
}
