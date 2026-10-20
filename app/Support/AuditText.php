<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

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
