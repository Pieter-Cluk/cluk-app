<?php

namespace App\Services;

use App\Support\VatNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks a VAT number with VIES, the European Commission's service.
 *
 * Three answers: valid, invalid (the number is not registered for intra-EU VAT,
 * e.g. a Belgian small business under the exemption scheme) or unavailable
 * (VIES or the country's service is busy or down; Belgium often answers
 * MS_MAX_CONCURRENT_REQ). Busy answers are retried a few times, and what is still
 * unavailable is checked again automatically later. It never blocks saving.
 */
class Vies
{
    public const URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /** VIES errors that mean "ask again later", not "wrong number". */
    public const BUSY = ['MS_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ', 'SERVICE_UNAVAILABLE', 'TIMEOUT', 'SERVER_BUSY', 'IP_BLOCKED', 'VAT_BLOCKED'];

    /** @var int milliseconds between tries (0 in tests) */
    public static int $pause = 1200;

    /**
     * @return array{status: 'valid'|'invalid'|'unavailable', name: ?string, address: ?string, reason: ?string}
     */
    public function check(string $normalized, int $tries = 3): array
    {
        $prefix = substr($normalized, 0, 2);
        $number = substr($normalized, 2);
        $result = ['status' => 'unavailable', 'name' => null, 'address' => null, 'reason' => 'busy'];

        for ($i = 1; $i <= $tries; $i++) {
            try {
                $response = Http::timeout(10)->acceptJson()->post(self::URL, ['countryCode' => $prefix === 'GR' ? 'EL' : $prefix, 'vatNumber' => $number]);
                $error = (string) ($response->json('errorWrappers.0.error') ?? $response->json('userError') ?? '');
                if ($response->successful() && is_bool($response->json('valid')) && ! in_array($error, self::BUSY, true)) {
                    $clean = fn ($v) => is_string($v) && trim($v) !== '' && trim($v) !== '---' ? trim(preg_replace('/\s+/', ' ', $v)) : null;

                    return [
                        'status' => $response->json('valid') ? 'valid' : 'invalid',
                        'name' => $clean($response->json('name')),
                        'address' => $clean($response->json('address')),
                        'reason' => $response->json('valid') ? null : 'not_registered',
                    ];
                }
                if ($error === 'INVALID_INPUT') {
                    return ['status' => 'invalid', 'name' => null, 'address' => null, 'reason' => 'bad_format'];
                }
                Log::warning('VIES did not answer', ['country' => $prefix, 'http' => $response->status(), 'error' => $error, 'try' => $i]);
            } catch (Throwable $e) {
                Log::warning('VIES could not be reached', ['error' => $e->getMessage(), 'try' => $i]);
                $result['reason'] = 'unreachable';
            }
            if ($i < $tries && self::$pause > 0) {
                usleep(self::$pause * 1000 * $i);
            }
        }

        return $result;
    }

    public static function appliesTo(?string $normalized): bool
    {
        if ($normalized === null) {
            return false;
        }
        $prefix = substr($normalized, 0, 2);

        return VatNumber::isEu($prefix === 'EL' ? 'GR' : $prefix);
    }

    /** VIES knows the company under another name than the one given (often the cause of doubt). */
    public static function nameDiffers(?string $given, ?string $vies): bool
    {
        if (! $given || ! $vies) {
            return false;
        }
        $norm = fn (string $s) => trim(preg_replace('/\b(bv|bvba|nv|vzw|commv|cv|vof|srl|sa|sprl|asbl|gmbh|ltd|sl|sas|sarl)\b/', '', preg_replace('/[^a-z0-9 ]/', ' ', mb_strtolower(Str::ascii($s)))));
        $a = $norm($given);
        $b = $norm($vies);
        if ($a === '' || $b === '' || str_contains($b, $a) || str_contains($a, $b)) {
            return false;
        }
        similar_text($a, $b, $pct);

        return $pct < 70;
    }
}
