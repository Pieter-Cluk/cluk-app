<?php

namespace App\Services\Credit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyRegistry;
use App\Notifications\Confirmation;
use App\Support\InLocale;
use App\Support\StaffMail;
use Illuminate\Support\Carbon;

/**
 * Takes the KBO/BCE extract that the "KBO update" workflow filtered down to
 * our own companies and stores it per company. A change in status or legal
 * situation is written to the audit log; when a customer turns red, the
 * account owner gets an email.
 */
class RegistryImport
{
    /** Enterprise numbers of our Belgian companies, for the workflow to filter the extract on. */
    public static function numbers(): array
    {
        return Company::active()->whereNotNull('vat_number')->pluck('vat_number')
            ->map(fn ($v) => CompanyRegistry::numberFromVat($v))->filter()->unique()->sort()->values()->all();
    }

    /**
     * @param  array{snapshot?: string, numbers?: list<string>, enterprises: array<string, array>, codes?: array<string, array<string, array<string, string>>>}  $data
     * @return array{companies: int, found: int, not_found: int, changed: int, red: int}
     */
    public static function run(array $data): array
    {
        $snap = ! empty($data['snapshot']) ? Carbon::parse($data['snapshot'])->toDateString() : today()->toDateString();
        $asked = collect($data['numbers'] ?? array_keys($data['enterprises']));
        $codes = $data['codes'] ?? [];
        $report = ['companies' => 0, 'found' => 0, 'not_found' => 0, 'changed' => 0, 'red' => 0];

        foreach (Company::active()->whereNotNull('vat_number')->with('registry')->get() as $c) {
            $num = CompanyRegistry::numberFromVat($c->vat_number);
            if (! $num || ! $asked->contains($num)) {
                continue;
            }
            if ($c->registry?->source === 'kbo-ws' && $c->registry->snapshot_date && $c->registry->snapshot_date->gte(Carbon::parse($snap))) {
                continue; // the live lookup is newer than this file
            }
            $e = $data['enterprises'][$num] ?? null;
            $entry = $e ? [
                'status' => $e['status'] ?? null, 'situation' => $e['situation'] ?? null,
                'situation_text' => isset($e['situation']) ? ($codes['JuridicalSituation'][$e['situation']] ?? []) : [],
                'form' => $e['form'] ?? null, 'form_text' => isset($e['form']) ? ($codes['JuridicalForm'][$e['form']] ?? []) : [],
                'start_date' => ! empty($e['start']) ? Carbon::createFromFormat('d-m-Y', $e['start'])->toDateString() : null,
                'name' => $e['name'] ?? null, 'address' => $e['address'] ?? null,
            ] : null;
            [$level, $changed] = self::apply($c, $num, $entry, $snap, 'kbo');
            $report['companies']++;
            $report[$e ? 'found' : 'not_found']++;
            $report['changed'] += (int) $changed;
            $report['red'] += (int) ($level === 'red');
        }

        return $report;
    }

    /**
     * Stores what the register says about one company. A change in status or
     * situation goes to the audit log; turning red emails the account owner.
     * $entry: status, situation, situation_text, form, form_text, start_date, name, address; null = not in the register.
     *
     * @return array{0: string, 1: bool} [credit level after, changed]
     */
    public static function apply(Company $c, string $num, ?array $entry, ?string $snapshot, string $source): array
    {
        $c->loadMissing('registry');
        $old = $c->registry;
        $before = $old ? CreditCheck::for($c)['level'] : null;
        $values = $entry ? [
            'found' => true, 'status' => $entry['status'] ?? null, 'situation_code' => $entry['situation'] ?? null, 'situation' => ($entry['situation_text'] ?? []) ?: null,
            'form_code' => $entry['form'] ?? null, 'form' => ($entry['form_text'] ?? []) ?: null, 'start_date' => $entry['start_date'] ?? null,
            'official_name' => isset($entry['name']) ? mb_substr($entry['name'], 0, 255) : null, 'address' => isset($entry['address']) ? mb_substr($entry['address'], 0, 255) : null,
        ] : ['found' => false, 'status' => null, 'situation_code' => null, 'situation' => null];
        $reg = CompanyRegistry::updateOrCreate(['company_id' => $c->id], $values + [
            'source' => $source, 'enterprise_number' => $num, 'snapshot_date' => $snapshot ?: today()->toDateString(), 'checked_at' => now(),
        ]);
        $changed = $old && ($old->status !== $reg->status || $old->situation_code !== $reg->situation_code || $old->found !== $reg->found);
        if ($changed) {
            AuditLog::record('crm.registry_changed', $c, [
                'status' => ($old->status ?? '—').' → '.($reg->status ?? '—'),
                'situation' => ($old->text('situation', 'en') ?? $old->situation_code ?? '—').' → '.($reg->text('situation', 'en') ?? $reg->situation_code ?? '—'),
                'source' => $source,
            ]);
        }
        $c->setRelation('registry', $reg);
        $after = CreditCheck::for($c);
        if ($old && $after['level'] === 'red' && $before !== 'red') {
            self::alert($c, $after);
        }

        return [$after['level'], $changed];
    }

    /** Live lookup of one company in the KBO web service. False when it is not Belgian or the service did not answer. */
    public static function refresh(Company $c): bool
    {
        $num = CompanyRegistry::numberFromVat($c->vat_number);
        if (! $num || ! KboLookup::available()) {
            return false;
        }
        $r = KboLookup::read($num);
        if ($r === null) {
            return false;
        }
        self::apply($c, $num, $r['entry'], $r['snapshot'], 'kbo-ws');

        return true;
    }

    /** True when the register data of a Belgian company is missing or older than the set number of days. */
    public static function stale(Company $c): bool
    {
        if (! CompanyRegistry::numberFromVat($c->vat_number)) {
            return false;
        }
        $c->loadMissing('registry');

        return ! $c->registry?->checked_at || $c->registry->checked_at->lt(now()->subDays((int) config('services.kbo_ws.stale_days', 7)))
            || $c->registry->enterprise_number !== CompanyRegistry::numberFromVat($c->vat_number);
    }

    /** Background: look up the companies whose register data is oldest (a few per run). */
    public static function refreshStale(int $max): int
    {
        if (! KboLookup::available()) {
            return 0;
        }
        $done = 0;
        $limit = now()->subDays((int) config('services.kbo_ws.stale_days', 7));
        $candidates = Company::active()->whereNotNull('vat_number')->where('vat_number', 'like', 'BE%')
            ->leftJoin('company_registry', 'company_registry.company_id', '=', 'companies.id')
            ->where(fn ($q) => $q->whereNull('company_registry.checked_at')->orWhere('company_registry.checked_at', '<', $limit))
            ->orderByRaw('company_registry.checked_at is not null')->orderBy('company_registry.checked_at')
            ->limit($max)->get(['companies.*']);
        foreach ($candidates as $c) {
            if (! self::refresh($c)) {
                break; // the service is down or refuses: try again next time
            }
            $done++;
        }

        return $done;
    }

    private static function alert(Company $c, array $check): void
    {
        $to = $c->accountOwner;
        if (! $to?->isActive()) {
            return;
        }
        $loc = $to->locale ?: 'en';
        StaffMail::send($to, 'credit_alert', InLocale::run($loc, fn () => new Confirmation(
            subject: __('credit.mail.subject', ['name' => $c->name]),
            greeting: __('mails.hello', ['name' => $to->first_name]),
            intro: [__('credit.mail.intro', ['name' => $c->name])],
            details: collect($check['reasons'])->where('level', 'red')->values()->mapWithKeys(fn ($r, $i) => [__('credit.mail.reason').' '.($i + 1) => $r['text']])->all(),
            next: [__('credit.mail.next')],
            button: [__('credit.mail.button'), route('companies.show', $c)],
        ))->locale($loc));
    }
}
