<?php

namespace App\Services\Warehouse;

use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\User;
use App\Models\Warehouse\CheckProcedure;
use App\Models\Warehouse\CheckProcedureVersion;
use App\Support\Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Check Builder: edit the draft steps of a procedure, publish versions, choose the items it is for. */
class Procedures
{
    public static function create(array $names, User $by, array $steps = [], ?Item $item = null): CheckProcedure
    {
        $p = CheckProcedure::create(['item_id' => $item?->id, 'names' => $names, 'draft_steps' => $steps, 'updated_by_id' => $by->id]);
        $item?->update(['check_procedure_id' => $p->id]);
        AuditLog::record('checks.procedure_created', $p, ['name' => $p->name('en')], $by);

        return $p;
    }

    /**
     * Add or change one step of the draft.
     *
     * @param  array{title: array, text: array, answer: string, choices?: array, costs?: array}  $d
     * @param  UploadedFile[]  $files  reference photos to add
     * @param  int[]  $removePhotos  indexes of reference photos to take away
     */
    public static function saveStep(CheckProcedure $p, ?string $key, array $d, array $files, array $removePhotos, User $by): string
    {
        $steps = $p->draft_steps ?? [];
        $i = $key ? collect($steps)->search(fn ($s) => $s['key'] === $key) : false;
        $step = $i === false ? ['key' => Str::lower(Str::random(8)), 'photos' => []] : $steps[$i];
        $photos = array_values(array_diff_key($step['photos'] ?? [], array_flip($removePhotos)));
        foreach ($files as $f) {
            $photos[] = Images::store($f, 'warehouse/procedures/'.$p->id, 1600);
        }
        $step = array_merge($step, [
            'title' => $d['title'], 'text' => $d['text'], 'answer' => $d['answer'], 'photos' => $photos,
            'choices' => $d['answer'] === 'choice' ? array_values($d['choices'] ?? []) : [],
            'costs' => array_values($d['costs'] ?? []),
        ]);
        if ($i === false) {
            $steps[] = $step;
        } else {
            $steps[$i] = $step;
        }
        $p->update(['draft_steps' => array_values($steps), 'updated_by_id' => $by->id]);

        return $step['key'];
    }

    public static function moveStep(CheckProcedure $p, string $key, string $dir): void
    {
        $steps = $p->draft_steps ?? [];
        $i = collect($steps)->search(fn ($s) => $s['key'] === $key);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i === false || ! isset($steps[$j])) {
            return;
        }
        [$steps[$i], $steps[$j]] = [$steps[$j], $steps[$i]];
        $p->update(['draft_steps' => array_values($steps)]);
    }

    public static function deleteStep(CheckProcedure $p, string $key): void
    {
        $p->update(['draft_steps' => array_values(array_filter($p->draft_steps ?? [], fn ($s) => $s['key'] !== $key))]);
    }

    /** Publish the draft as the next version: checks started from now on use it. */
    public static function publish(CheckProcedure $p, ?string $note, User $by): CheckProcedureVersion
    {
        return DB::transaction(function () use ($p, $note, $by) {
            $v = CheckProcedureVersion::create(['procedure_id' => $p->id, 'version' => (int) $p->versions()->max('version') + 1,
                'steps' => $p->draft_steps ?? [], 'note' => $note, 'published_by_id' => $by->id]);
            $p->update(['current_version_id' => $v->id]);
            AuditLog::record('checks.published', $p, ['name' => $p->name('en'), 'version' => $v->version, 'steps' => count($v->steps), 'note' => $note], $by);

            return $v;
        });
    }

    /** A copy to start a similar procedure from (same steps and photos, nothing published, no items). */
    public static function copy(CheckProcedure $p, User $by): CheckProcedure
    {
        $names = collect($p->names)->map(fn ($n) => $n.' (2)')->all();
        $steps = collect($p->draft_steps ?? [])->map(fn ($s) => ['key' => Str::lower(Str::random(8))] + $s)->all();

        return self::create($names, $by, $steps, null);
    }

    /**
     * Delete a procedure (e.g. a completely new one is written, or the item is no longer used). The items are unlinked,
     * finished checks keep their answers, checks still in progress start again. Returns the number restarted.
     */
    public static function delete(CheckProcedure $p, User $by): int
    {
        return DB::transaction(function () use ($p, $by) {
            $versions = $p->versions()->pluck('id');
            $restart = 0;
            foreach ([\App\Models\Warehouse\Check::class, \App\Models\Storage\StorageCheck::class] as $model) {
                $restart += $model::whereIn('procedure_version_id', $versions)->whereNull('finished_at')->update(['procedure_version_id' => null, 'step' => 0, 'answers' => null]);
            }
            $items = Item::where('check_procedure_id', $p->id)->count();
            Item::where('check_procedure_id', $p->id)->update(['check_procedure_id' => null]);
            \App\Models\Storage\StoredItem::where('check_procedure_id', $p->id)->update(['check_procedure_id' => null]);
            AuditLog::record('checks.procedure_deleted', $p, ['name' => $p->name('en'), 'versions' => $versions->count(), 'items' => $items, 'restarted' => $restart], $by);
            $p->delete();

            return $restart;
        });
    }

    /** @param  int[]  $itemIds */
    public static function assign(CheckProcedure $p, array $itemIds, User $by): int
    {
        $n = Item::whereIn('id', $itemIds)->update(['check_procedure_id' => $p->id]);
        AuditLog::record('checks.items_assigned', $p, ['items' => $n], $by);

        return $n;
    }
}
