<?php

namespace App\Services\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Storage\StorageCheck;
use App\Models\Storage\StoredPiece;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\CheckProcedure;
use App\Models\Warehouse\CheckProcedureVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Tech Check: the published procedure of the item, one step at a time. A step must be answered before the next one
 * shows; "not OK" needs a note and a photo and can carry costs for the customer.
 */
class TechCheck
{
    /** The version a check of this piece follows: the one it started with, else the item's published version. */
    public static function version(Asset $asset, Check $check): ?CheckProcedureVersion
    {
        if ($check->procedure_version_id) {
            return $check->procedureVersion;
        }
        $v = $asset->item?->checkProcedure?->current;
        if ($v && $v->procedure->active && $v->steps) {
            $check->update(['procedure_version_id' => $v->id, 'step' => 0, 'answers' => [], 'started_at' => now()]);

            return $v;
        }

        return null;
    }

    /** The same for a storage customer's piece: the procedure of their item. */
    public static function versionForStored(StoredPiece $piece, StorageCheck $check): ?CheckProcedureVersion
    {
        if ($check->procedure_version_id) {
            return $check->procedureVersion;
        }
        $v = $piece->item?->checkProcedure?->current;
        if ($v && $v->procedure->active && $v->steps) {
            $check->update(['procedure_version_id' => $v->id, 'step' => 0, 'answers' => []]);

            return $v;
        }

        return null;
    }

    /**
     * @param  array{answer?: string, value?: string, note?: string, costs?: int[], extra_label?: string, extra_amount?: string}  $d
     * @param  UploadedFile[]  $files
     */
    public static function answer(Check|StorageCheck $check, CheckProcedureVersion $v, array $d, array $files): void
    {
        $steps = $v->steps;
        $step = $steps[$check->step] ?? null;
        if (! $step) {
            return;
        }
        Checks::addPhotos($check, $files, 'step:'.$step['key']);
        $photos = Checks::photoCount($check, 'step:'.$step['key']);
        $answer = $d['answer'] ?? null;
        $ok = match ($step['answer']) {
            'confirm' => $answer === 'done',
            'okfail' => in_array($answer, ['ok', 'fail'], true),
            'value' => trim((string) ($d['value'] ?? '')) !== '',
            'runtime' => is_numeric(str_replace(',', '.', (string) ($d['value'] ?? ''))),
            'photo' => $photos > 0,
            'choice' => isset($step['choices'][(int) ($d['value'] ?? -1)]),
            default => true,
        };
        if (! $ok) {
            throw ValidationException::withMessages(['answer' => __($step['answer'] === 'photo' ? 'checks.run.photo_needed' : 'checks.run.answer_first')]);
        }
        $fail = $step['answer'] === 'okfail' && $answer === 'fail';
        if ($fail && (trim((string) ($d['note'] ?? '')) === '' || $photos === 0)) {
            throw ValidationException::withMessages(['note' => __('checks.run.fail_needs')]);
        }
        $costs = collect($d['costs'] ?? [])->map('intval')->filter(fn ($i) => isset($step['costs'][$i]))->values()->all();
        $extra = trim((string) ($d['extra_label'] ?? ''));
        $answers = $check->answers ?? [];
        $answers[$step['key']] = [
            'by' => auth()->user()?->first_name, 'at' => now()->format('d/m H:i'),
            'answer' => $step['answer'] === 'okfail' ? $answer : ($step['answer'] === 'confirm' ? 'done' : 'ok'),
            'value' => $step['answer'] === 'choice' ? CheckProcedure::say($step['choices'][(int) $d['value']] ?? null) : (trim((string) ($d['value'] ?? '')) ?: null),
            'note' => trim((string) ($d['note'] ?? '')) ?: null,
            'costs' => $costs,
            'extra' => $extra !== '' ? ['label' => mb_substr($extra, 0, 120), 'cents' => ($d['extra_amount'] ?? '') !== '' ? (int) round((float) $d['extra_amount'] * 100) : null] : null,
        ];
        if ($step['answer'] === 'runtime') {
            $answers[$step['key']] += self::runtime($check, (float) str_replace(',', '.', (string) $d['value']));
        }
        $check->update(['answers' => $answers, 'step' => $check->step + 1]);
    }

    /**
     * Runtime (hours meter): the hours since the last check of this piece, against the hours the rental covered
     * (use days × 24). More than that, or a lower meter than last time, gives a warning.
     *
     * @return array{runtime: float, prev: ?float, prev_at: ?string, used: ?float, allowed: ?int, warn: ?string}
     */
    public static function runtime(Check|StorageCheck $check, float $now): array
    {
        $prev = self::lastRuntime($check);
        $allowed = null;
        if ($check instanceof Check && ($o = $check->order) && $o->use_from && $o->use_until) {
            $allowed = ($o->use_from->copy()->startOfDay()->diffInDays($o->use_until->copy()->startOfDay()) + 1) * 24;
        }
        $used = $prev ? round($now - $prev['value'], 1) : null;
        $warn = $used === null ? null : ($used < 0 ? 'lower' : ($allowed !== null && $used > $allowed ? 'over' : null));

        return ['runtime' => $now, 'prev' => $prev['value'] ?? null, 'prev_at' => $prev['at'] ?? null, 'used' => $used, 'allowed' => $allowed, 'warn' => $warn];
    }

    /** The meter reading of the last finished check of this piece. */
    public static function lastRuntime(Check|StorageCheck $check): ?array
    {
        $q = $check instanceof Check ? Check::where('asset_id', $check->asset_id) : StorageCheck::where('stored_piece_id', $check->stored_piece_id);
        foreach ($q->whereNotNull('finished_at')->where('id', '!=', $check->id)->latest('finished_at')->limit(20)->get() as $c) {
            foreach ($c->answers ?? [] as $a) {
                if (is_array($a) && isset($a['runtime'])) {
                    return ['value' => (float) $a['runtime'], 'at' => $c->finished_at->format('d/m/Y')];
                }
            }
        }

        return null;
    }

    /** Runtime warnings of a check (for the summary and the account owner). */
    public static function runtimeWarnings(Check|StorageCheck $check): array
    {
        return collect($check->answers ?? [])->filter(fn ($a) => is_array($a) && ! empty($a['warn']))->values()->all();
    }

    /**
     * Always the first step: the complete view of the fixture. How clean is it (1 clean, 2 not clean, 3 dirty: the
     * cleaning cost for the customer) and is anything broken (a note, a photo and optionally a cost)?
     */
    public static function answerView(Check|StorageCheck $check, array $d, array $files): void
    {
        Checks::addPhotos($check, $files, 'view');
        $dirt = (int) ($d['dirt'] ?? 0);
        if (! in_array($dirt, [1, 2, 3], true) || ! in_array($d['broken'] ?? null, ['no', 'yes'], true)) {
            throw ValidationException::withMessages(['answer' => __('checks.run.answer_first')]);
        }
        $photos = Checks::photoCount($check, 'view');
        if ($dirt >= (int) config('warehouse.photo_stage') && $photos === 0) {
            throw ValidationException::withMessages(['note' => __('checks.run.view_photo')]);
        }
        $broken = $d['broken'] === 'yes';
        if ($broken && (trim((string) ($d['note'] ?? '')) === '' || $photos === 0)) {
            throw ValidationException::withMessages(['note' => __('checks.run.fail_needs')]);
        }
        $extra = trim((string) ($d['extra_label'] ?? ''));
        $answers = $check->answers ?? [];
        $answers['_view'] = ['by' => auth()->user()?->first_name, 'at' => now()->format('d/m H:i'), 'dirt' => $dirt, 'broken' => $broken, 'note' => trim((string) ($d['note'] ?? '')) ?: null,
            'extra' => $broken && $extra !== '' ? ['label' => mb_substr($extra, 0, 120), 'cents' => ($d['extra_amount'] ?? '') !== '' ? (int) round((float) $d['extra_amount'] * 100) : null] : null];
        $check->update(['answers' => $answers] + ($check instanceof Check ? ['dirt' => $dirt, 'cleaned_at' => now()] : []));
    }

    public static function viewDone(Check|StorageCheck $check): bool
    {
        return isset(($check->answers ?? [])['_view']);
    }

    public static function back(Check|StorageCheck $check): void
    {
        $answers = $check->answers ?? [];
        if ($check->step === 0 && isset($answers['_view']) && ! isset($answers['_kit'])) {
            unset($answers['_view']); // back from the first procedure step to the complete view
            $check->update(['answers' => $answers]);

            return;
        }
        if (isset($answers['_kit'])) {
            unset($answers['_kit']); // back from the summary to the accessories
            $check->update(['answers' => $answers]);

            return;
        }
        $check->update(['step' => max(0, $check->step - 1)]);
    }

    /** The accessories that always go with this item (kit in the dry-hire card): checked as the last step. */
    public static function kit(Check|StorageCheck $check): \Illuminate\Support\Collection
    {
        $item = $check instanceof Check ? $check->asset?->item : $check->piece?->item?->sameAs;

        return $item ? $item->kit()->get() : collect();
    }

    /** Steps of the procedure, plus the accessories step when the item has accessories. */
    public static function stepCount(Check|StorageCheck $check): int
    {
        $v = $check->procedureVersion;

        return 1 + count($v?->steps ?? []) + (self::kit($check)->isNotEmpty() ? 1 : 0);
    }

    public static function proceduresDone(Check|StorageCheck $check, CheckProcedureVersion $v): bool
    {
        return self::viewDone($check) && $check->step >= count($v->steps) && collect($v->steps)->every(fn ($s) => isset(($check->answers ?? [])[$s['key']]));
    }

    public static function complete(Check|StorageCheck $check, CheckProcedureVersion $v): bool
    {
        return self::proceduresDone($check, $v) && (self::kit($check)->isEmpty() || isset(($check->answers ?? [])['_kit']));
    }

    /**
     * The accessories step: every accessory is ticked as complete or with the number missing. Missing ones need a photo.
     *
     * @param  array<int, string|int>  $missing  kit item id => missing qty ('' = not answered)
     */
    public static function answerKit(Check|StorageCheck $check, array $missing): void
    {
        $kit = self::kit($check);
        $out = [];
        foreach ($kit as $k) {
            if (! isset($missing[$k->id]) || $missing[$k->id] === '') {
                throw ValidationException::withMessages(['missing' => __('checks.run.kit_each')]);
            }
            $out[$k->id] = max(0, min((int) $missing[$k->id], max(1, (int) $k->pivot->qty)));
        }
        if (array_filter($out) && Checks::photoCount($check, 'kit') === 0) {
            throw ValidationException::withMessages(['missing' => __('checks.run.kit_photo')]);
        }
        $check->update(['answers' => ($check->answers ?? []) + ['_kit' => $out]]);
    }
}
