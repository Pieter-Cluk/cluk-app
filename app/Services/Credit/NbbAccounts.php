<?php

namespace App\Services\Credit;

use App\Models\Company;
use App\Models\CompanyAccount;
use App\Models\CompanyRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Annual accounts from the National Bank (NBB Central Balance Sheet Office,
 * "Authentic Data Query", free with a subscription key from developer.cbso.nbb.be).
 * 1. the list of filings of an enterprise number, 2. per filing the figures as JSON.
 * Figures in JSON exist only for XBRL filings since April 2022; older or PDF-only
 * filings are skipped, but each filing also holds last year's figures (NM1).
 */
class NbbAccounts
{
    /** Codes of the Belgian accounts schema. Small companies need not publish turnover (70): then the gross margin (9900) is there. */
    public const CODES = [
        'turnover' => ['70'], 'gross_margin' => ['9900'], 'operating_result' => ['9901'], 'profit' => ['9904'],
        'equity' => ['10/15'], 'total_assets' => ['20/58'], 'employees' => ['9087', '1003'],
    ];

    public static function available(): bool
    {
        return filled(config('services.nbb.key'));
    }

    /** Fetches and stores the last years. Returns the number of years stored, or null when the NBB could not be reached. */
    public static function refresh(Company $c): ?int
    {
        $num = CompanyRegistry::numberFromVat($c->vat_number);
        if (! $num || ! self::available()) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $num);
        $res = self::get('legalEntity/'.$digits.'/references', 'application/json');
        if ($res === null) {
            return null;
        }
        $refs = $res === false ? [] : (array) $res;

        // One filing per financial year: the newest deposit wins (corrections).
        $years = collect($refs)->filter(fn ($r) => is_array($r) && ! empty($r['ReferenceNumber']) && ! empty($r['ExerciseDates']['endDate']))
            ->sortByDesc(fn ($r) => (string) ($r['DepositDate'] ?? ''))
            ->unique(fn ($r) => substr((string) $r['ExerciseDates']['endDate'], 0, 10))
            ->sortByDesc(fn ($r) => (string) $r['ExerciseDates']['endDate'])
            ->take((int) config('services.nbb.years', 6));

        $have = CompanyAccount::where('company_id', $c->id)->get()->keyBy('year');
        $previous = [];
        $stored = 0;
        foreach ($years as $r) {
            $end = Carbon::parse($r['ExerciseDates']['endDate']);
            $year = (int) $end->year;
            if (($have[$year] ?? null)?->reference === $r['ReferenceNumber'] && $have[$year]->period === 'N') {
                $stored++;

                continue; // already in
            }
            $data = self::get('deposit/'.$r['ReferenceNumber'].'/accountingData', 'application/x.jsonxbrl');
            if ($data === null) {
                break; // service trouble: the rest next time
            }
            if ($data === false) {
                continue; // a PDF-only or older filing: no figures as data
            }
            $values = self::parse($data);
            if (! array_filter($values['N'], fn ($v) => $v !== null)) {
                continue;
            }
            $meta = ['reference' => $r['ReferenceNumber'], 'model' => isset($r['ModelType']) ? mb_substr((string) $r['ModelType'], 0, 20) : null,
                'deposited_on' => ! empty($r['DepositDate']) ? Carbon::parse($r['DepositDate'])->toDateString() : null,
                'currency' => mb_substr((string) ($r['Currency'] ?? 'EUR'), 0, 3) ?: 'EUR'];
            CompanyAccount::updateOrCreate(['company_id' => $c->id, 'year' => $year], $meta + $values['N'] + ['period_end' => $end->toDateString(), 'period' => 'N']);
            $stored++;
            if (array_filter($values['NM1'], fn ($v) => $v !== null)) {
                $previous[$year - 1] = $meta + $values['NM1'] + ['period_end' => $end->copy()->subYear()->toDateString(), 'period' => 'NM1'];
            }
        }
        // Years we only know from the comparison column of the next filing.
        foreach ($previous as $year => $v) {
            if (! CompanyAccount::where('company_id', $c->id)->where('year', $year)->where('period', 'N')->exists()) {
                CompanyAccount::updateOrCreate(['company_id' => $c->id, 'year' => $year], $v);
                $stored++;
            }
        }
        $c->forceFill(['accounts_checked_at' => now()])->saveQuietly();

        return $stored;
    }

    /**
     * Reads the figures out of the JSON of one filing: every entry with a code, a period (N or NM1) and a value.
     * Entries split over a dimension (per category, per type of employee…) are left out: we want the totals.
     *
     * @return array{N: array<string, int|float|null>, NM1: array<string, int|float|null>}
     */
    public static function parse(array $json): array
    {
        $found = ['N' => [], 'NM1' => []];
        $walk = function ($node) use (&$walk, &$found) {
            if (! is_array($node)) {
                return;
            }
            $k = array_change_key_case($node);
            if (isset($k['code'], $k['value']) && ! is_array($k['value']) && empty($k['dimensions']) && empty($k['dimension'])) {
                $period = strtoupper((string) ($k['period'] ?? 'N'));
                if (isset($found[$period]) && is_numeric($k['value']) && ! isset($found[$period][(string) $k['code']])) {
                    $found[$period][(string) $k['code']] = (float) $k['value'];
                }

                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($json);

        $out = [];
        foreach ($found as $period => $codes) {
            foreach (self::CODES as $field => $list) {
                $v = null;
                foreach ($list as $code) {
                    if (isset($codes[$code])) {
                        $v = $codes[$code];
                        break;
                    }
                }
                $out[$period][$field] = $v === null ? null : ($field === 'employees' ? round($v, 1) : (int) round($v * 100));
            }
        }

        return $out;
    }

    /** True when a Belgian company was never looked up, or longer ago than the set number of days. */
    public static function stale(Company $c): bool
    {
        return CompanyRegistry::numberFromVat($c->vat_number) !== null
            && (! $c->accounts_checked_at || $c->accounts_checked_at->lt(now()->subDays((int) config('services.nbb.stale_days', 30))));
    }

    /** A few companies at a time in the background: never looked up first, then the oldest. */
    public static function refreshStale(int $max): int
    {
        if (! self::available()) {
            return 0;
        }
        $done = 0;
        $candidates = Company::active()->where('vat_number', 'like', 'BE%')
            ->where(fn ($q) => $q->whereNull('accounts_checked_at')->orWhere('accounts_checked_at', '<', now()->subDays((int) config('services.nbb.stale_days', 30))))
            ->orderByRaw('accounts_checked_at is not null')->orderBy('accounts_checked_at')->limit($max)->get();
        foreach ($candidates as $c) {
            if (! CompanyRegistry::numberFromVat($c->vat_number)) {
                $c->forceFill(['accounts_checked_at' => now()])->saveQuietly();

                continue;
            }
            if (self::refresh($c) === null) {
                break; // the NBB is down or refuses: next time
            }
            $done++;
        }

        return $done;
    }

    /** @return array|false|null decoded JSON; false = nothing there (404, no data in this format); null = could not be reached */
    private static function get(string $path, string $accept): array|false|null
    {
        try {
            $res = Http::timeout(12)->withHeaders([
                'NBB-CBSO-Subscription-Key' => (string) config('services.nbb.key'),
                'X-Request-Id' => (string) Str::uuid(),
                'Accept' => $accept,
            ])->get(rtrim((string) config('services.nbb.url'), '/').'/'.$path);
            if (in_array($res->status(), [404, 406, 415], true) || ($res->successful() && trim($res->body()) === '')) {
                return false;
            }
            if (! $res->successful()) {
                Log::warning('NBB annual accounts: '.$res->status(), ['path' => $path, 'body' => mb_substr($res->body(), 0, 200)]);

                return null;
            }
            $json = json_decode($res->body(), true);

            return is_array($json) ? $json : false;
        } catch (Throwable $e) {
            Log::warning('NBB annual accounts not reached', ['error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }
}
