<?php

namespace App\Services;

use App\Support\VatNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks a VAT number with VIES, the European Commission's service.
 * VIES is sometimes down per country; that is reported as "unavailable"
 * and never blocks saving a company.
 */
class Vies
{
    public const URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /**
     * @return array{status: 'valid'|'invalid'|'unavailable', name: ?string, address: ?string}
     */
    public function check(string $normalized): array
    {
        $prefix = substr($normalized, 0, 2);
        $number = substr($normalized, 2);

        try {
            $response = Http::timeout(10)->acceptJson()->post(self::URL, [
                'countryCode' => $prefix,
                'vatNumber' => $number,
            ]);

            if (! $response->successful() || ! is_bool($response->json('valid'))) {
                Log::info('VIES did not answer', ['country' => $prefix, 'http' => $response->status(), 'error' => $response->json('errorWrappers.0.error') ?? $response->json('userError')]);

                return ['status' => 'unavailable', 'name' => null, 'address' => null];
            }

            $clean = fn ($v) => is_string($v) && trim($v) !== '' && trim($v) !== '---' ? trim(preg_replace('/\s+/', ' ', $v)) : null;

            return [
                'status' => $response->json('valid') ? 'valid' : 'invalid',
                'name' => $clean($response->json('name')),
                'address' => $clean($response->json('address')),
            ];
        } catch (Throwable $e) {
            Log::info('VIES could not be reached', ['error' => $e->getMessage()]);

            return ['status' => 'unavailable', 'name' => null, 'address' => null];
        }
    }

    public static function appliesTo(?string $normalized): bool
    {
        if ($normalized === null) {
            return false;
        }
        $prefix = substr($normalized, 0, 2);

        return VatNumber::isEu($prefix === 'EL' ? 'GR' : $prefix);
    }
}
