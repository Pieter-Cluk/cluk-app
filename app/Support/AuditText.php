<?php

namespace App\Support;

use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;

/** Turns an audit log line into a short sentence in the reader's language. */
class AuditText
{
    public static function action(AuditLog $log): string
    {
        $key = 'users.audit.actions.'.str_replace('.', '_', $log->action);
        $text = __($key);

        return $text === $key ? $log->action : $text;
    }

    public static function subject(AuditLog $log): string
    {
        $subject = $log->subject;

        return match (true) {
            $subject instanceof User => $subject->name,
            $subject instanceof Role => $subject->name,
            $subject instanceof VatRate => $subject->name,
            $subject instanceof Company => $subject->name,
            $subject instanceof Member => $subject->name,
            $subject instanceof Item => $subject->number.' · '.$subject->name,
            $subject instanceof Asset => $subject->code,
            $subject instanceof ServiceRequest => __('workspace.kinds.'.$subject->kind).' · '.$subject->member?->name,
            $subject instanceof CompanyDetail => trim($subject->legal_name.' '.$subject->legal_form),
            $subject !== null => class_basename($subject).' #'.$subject->getKey(),
            default => '',
        };
    }

    public static function details(AuditLog $log): string
    {
        $d = $log->data ?? [];
        $level = fn ($l) => $l === null ? __('users.access.same_as_role') : __('cluk.levels.'.config('cluk.levels')[(int) $l]);

        return match ($log->action) {
            'user.module_override' => __('cluk.modules.'.($d['module'] ?? '')).': '.$level($d['from'] ?? null).' → '.$level($d['to'] ?? null),
            'user.right_granted', 'user.right_revoked' => __('cluk.rights.'.($d['right'] ?? '')),
            'user.role_changed' => ($d['from'] ?? '—').' → '.($d['to'] ?? '—'),
            'user.created' => trim(($d['email'] ?? '').' · '.($d['role'] ?? ''), ' ·'),
            'user.updated' => implode(', ', array_map(fn ($f) => __('users.fields.'.$f), array_keys($d['changes'] ?? []))),
            'role.updated' => self::roleChanges($d),
            'role.created' => isset($d['copied_from']) ? __('users.audit.copied_from', ['role' => $d['copied_from']]) : '',
            'role.deleted' => (string) ($d['name'] ?? ''),
            'company.updated', 'vat.updated', 'vat.defaults_changed' => implode(', ', array_map(fn ($f) => __('settings.fields.'.$f), array_keys($d['changes'] ?? []))),
            'company.bank_added', 'company.bank_changed', 'company.bank_removed' => (string) ($d['iban'] ?? ''),
            'vat.created' => trim(($d['name'] ?? '').' · '.($d['code'] ?? ''), ' ·'),
            'vat.scheduled' => ($d['from'] ?? '').' → '.($d['to'] ?? '').' · '.($d['date'] ?? ''),
            'vat.ended', 'vat.deleted' => (string) ($d['name'] ?? ''),
            'crm.company_updated' => implode(', ', array_map(fn ($f) => __('companies.fields.'.$f), $d['fields'] ?? [])),
            'afrit6.desk_swapped' => ($d['from'] ?? '').' → '.($d['to'] ?? '').' · '.($d['date'] ?? ''),
            'afrit6.desk_added' => trim(($d['desk'] ?? '').' · '.($d['from'] ?? '').' → '.($d['to'] ?? '…').' · '.($d['price'] ?? ''), ' ·'),
            'afrit6.desk_ended' => ($d['desk'] ?? '').' · '.($d['from'] ?? '…').' → '.($d['to'] ?? ''),
            'afrit6.statement_created' => ($d['period'] ?? '').' · '.($d['total'] ?? ''),
            'afrit6.task_assigned' => trim(($d['from'] ?? '—').' → '.($d['to'] ?? '').(isset($d['note']) ? ' · '.$d['note'] : '')),
            'afrit6.task_declined' => (string) ($d['reason'] ?? ''),
            'afrit6.task_amount' => ($d['from'] ?? '').' → '.($d['to'] ?? ''),
            'equipment.item_created' => (string) ($d['number'] ?? ''),
            'equipment.item_updated', 'equipment.asset_updated' => implode(', ', array_map(fn ($f) => __('equipment.fields.'.preg_replace('/_cents$/', '', $f)), array_keys($d['changes'] ?? []))),
            'equipment.assets_added' => ($d['count'] ?? '').' · '.($d['codes'] ?? ''),
            'equipment.asset_retired' => __('equipment.reasons.'.($d['reason'] ?? '')),
            'crm.vat_regime_changed' => ($d['from'] ?? '').' → '.($d['to'] ?? '').(isset($d['code']) ? ' ('.$d['code'].')' : ''),
            'import.done' => trim(($d['file'] ?? '').' · '.collect($d)->except('file')->map(fn ($r, $k) => __('import.kinds.'.$k).' +'.($r['created'] ?? 0).' ~'.($r['updated'] ?? 0))->implode(' · '), ' ·'),
            'afrit6.member_approved' => (string) ($d['company'] ?? ''),
            'afrit6.member_declined' => (string) ($d['reason'] ?? ''),
            'auth.refused_unknown' => (string) ($d['email'] ?? ''),
            default => '',
        };
    }

    /** @param array<string, mixed> $d */
    private static function roleChanges(array $d): string
    {
        $parts = [];
        foreach ($d['levels'] ?? [] as $module => [$from, $to]) {
            $parts[] = __('cluk.modules.'.$module).': '.__('cluk.levels.'.config('cluk.levels')[$from]).' → '.__('cluk.levels.'.config('cluk.levels')[$to]);
        }
        foreach ($d['rights_added'] ?? [] as $right) {
            $parts[] = '+ '.__('cluk.rights.'.$right);
        }
        foreach ($d['rights_removed'] ?? [] as $right) {
            $parts[] = '− '.__('cluk.rights.'.$right);
        }

        return implode(' · ', $parts);
    }
}
