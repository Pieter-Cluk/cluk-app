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
            $e = $data['enterprises'][$num] ?? null;
            $before = CreditCheck::for($c)['level'];
            $old = $c->registry;
            $values = $e ? [
                'found' => true, 'status' => $e['status'] ?? null, 'situation_code' => $e['situation'] ?? null,
                'situation' => isset($e['situation']) ? ($codes['JuridicalSituation'][$e['situation']] ?? null) : null,
                'form_code' => $e['form'] ?? null, 'form' => isset($e['form']) ? ($codes['JuridicalForm'][$e['form']] ?? null) : null,
                'start_date' => ! empty($e['start']) ? Carbon::createFromFormat('d-m-Y', $e['start'])->toDateString() : null,
                'official_name' => isset($e['name']) ? mb_substr($e['name'], 0, 255) : null, 'address' => isset($e['address']) ? mb_substr($e['address'], 0, 255) : null,
            ] : ['found' => false, 'status' => null, 'situation_code' => null, 'situation' => null];
            $reg = CompanyRegistry::updateOrCreate(['company_id' => $c->id], $values + ['source' => 'kbo', 'enterprise_number' => $num, 'snapshot_date' => $snap, 'checked_at' => now()]);
            $report['companies']++;
            $report[$e ? 'found' : 'not_found']++;

            $changed = $old && ($old->status !== $reg->status || $old->situation_code !== $reg->situation_code || $old->found !== $reg->found);
            if ($changed) {
                $report['changed']++;
                AuditLog::record('crm.registry_changed', $c, [
                    'status' => ($old->status ?? '—').' → '.($reg->status ?? '—'),
                    'situation' => ($old->text('situation', 'en') ?? $old->situation_code ?? '—').' → '.($reg->text('situation', 'en') ?? $reg->situation_code ?? '—'),
                ]);
            }
            $c->setRelation('registry', $reg);
            $after = CreditCheck::for($c);
            if ($after['level'] === 'red') {
                $report['red']++;
            }
            if ($old && $after['level'] === 'red' && $before !== 'red') {
                self::alert($c, $after);
            }
        }

        return $report;
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
