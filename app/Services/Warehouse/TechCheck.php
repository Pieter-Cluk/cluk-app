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
            'answer' => $step['answer'] === 'okfail' ? $answer : ($step['answer'] === 'confirm' ? 'done' : 'ok'),
            'value' => $step['answer'] === 'choice' ? CheckProcedure::say($step['choices'][(int) $d['value']] ?? null) : (trim((string) ($d['value'] ?? '')) ?: null),
            'note' => trim((string) ($d['note'] ?? '')) ?: null,
            'costs' => $costs,
            'extra' => $extra !== '' ? ['label' => mb_substr($extra, 0, 120), 'cents' => ($d['extra_amount'] ?? '') !== '' ? (int) round((float) $d['extra_amount'] * 100) : null] : null,
        ];
        $check->update(['answers' => $answers, 'step' => $check->step + 1]);
    }

    public static function back(Check|StorageCheck $check): void
    {
        $answers = $check->answers ?? [];
        if ($check->step >= self::stepCount($check) - 1 && isset($answers['_kit'])) {
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

        return count($v?->steps ?? []) + (self::kit($check)->isNotEmpty() ? 1 : 0);
    }

    public static function proceduresDone(Check|StorageCheck $check, CheckProcedureVersion $v): bool
    {
        return $check->step >= count($v->steps) && collect($v->steps)->every(fn ($s) => isset(($check->answers ?? [])[$s['key']]));
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
