<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The day rate: the European Central Bank's euro reference rates (published every working day around 16:00 CET).
 * A rate is "units of the currency for 1 euro", so EUR = foreign / rate. Kept for 6 hours; the last good set is
 * used when the ECB cannot be reached.
 */
class ExchangeRates
{
    public const URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    /** Currencies offered for supplier prices (EUR first). */
    public const CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'JPY', 'CNY', 'AUD', 'CAD'];

    /** @return array{date: string, rates: array<string, float>}|null */
    public static function today(): ?array
    {
        return Cache::remember('fx.ecb.today', now()->addHours(6), function () {
            try {
                $res = Http::timeout(10)->get(self::URL);
                if ($res->successful() && preg_match('/time=[\'"](\d{4}-\d{2}-\d{2})[\'"]/', $res->body(), $d)
                    && preg_match_all('/currency=[\'"]([A-Z]{3})[\'"]\s+rate=[\'"]([0-9.]+)[\'"]/', $res->body(), $m, PREG_SET_ORDER)) {
                    $set = ['date' => $d[1], 'rates' => collect($m)->mapWithKeys(fn ($x) => [$x[1] => (float) $x[2]])->all()];
                    Cache::forever('fx.ecb.last', $set);

                    return $set;
                }
            } catch (Throwable $e) {
                Log::warning('Exchange rates: ECB not reachable', ['error' => mb_substr($e->getMessage(), 0, 200)]);
            }

            return Cache::get('fx.ecb.last'); // null until the first good fetch
        });
    }

    /** @return array{rate: float, date: string}|null  1 EUR = rate × currency */
    public static function rate(string $currency): ?array
    {
        $currency = strtoupper($currency);
        if ($currency === 'EUR') {
            return ['rate' => 1.0, 'date' => now()->toDateString()];
        }
        $set = self::today();
        $rate = $set['rates'][$currency] ?? null;

        return $rate ? ['rate' => $rate, 'date' => $set['date']] : null;
    }

    public static function toEurCents(int $foreignCents, float $rate): int
    {
        return (int) round($foreignCents / $rate);
    }

    /** An amount in its own currency, written the way the reader expects (€ 1.234,50 · $1,234.50). */
    public static function format(int $cents, string $currency, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if (strtoupper($currency) === 'EUR') {
            return \App\Services\Afrit6\Pricing::eur($cents);
        }
        $f = new \NumberFormatter(['nl' => 'nl_BE', 'fr' => 'fr_BE', 'es' => 'es_ES'][$locale] ?? 'en_GB', \NumberFormatter::CURRENCY);

        return (string) $f->formatCurrency($cents / 100, strtoupper($currency));
    }
}
