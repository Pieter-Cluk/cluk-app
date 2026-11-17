<?php

namespace App\Models\Warehouse;

use App\Models\Equipment\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Check Builder: how a piece is checked after a job, step by step. Edited as a draft, published as a version:
 * a check in progress keeps the version it started with.
 *
 * A step: ['key', 'title' => {nl, en}, 'text' => {nl, en}, 'photos' => [path], 'answer' => confirm|okfail|value|photo|choice,
 *          'choices' => [{nl, en}], 'costs' => [['label' => {nl, en}, 'cents' => ?int]]]
 */
#[Fillable(['names', 'draft_steps', 'current_version_id', 'active', 'updated_by_id'])]
class CheckProcedure extends Model
{
    public const ANSWERS = ['confirm', 'okfail', 'value', 'photo', 'choice'];

    protected function casts(): array
    {
        return ['names' => 'array', 'draft_steps' => 'array', 'active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CheckProcedureVersion::class, 'procedure_id')->orderByDesc('version');
    }

    public function current(): BelongsTo
    {
        return $this->belongsTo(CheckProcedureVersion::class, 'current_version_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'check_procedure_id');
    }

    public function name(?string $locale = null): string
    {
        return self::say($this->names, $locale);
    }

    /** The draft differs from the published version (or nothing is published yet). */
    public function draftChanged(): bool
    {
        return json_encode($this->draft_steps ?? []) !== json_encode($this->current?->steps ?? null);
    }

    /** A text in nl / en: the reader's language, else the other one. */
    public static function say(?array $t, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $first = $locale === 'nl' ? 'nl' : 'en';

        return (string) (($t[$first] ?? '') !== '' ? $t[$first] : ($t[$first === 'nl' ? 'en' : 'nl'] ?? ''));
    }
}
