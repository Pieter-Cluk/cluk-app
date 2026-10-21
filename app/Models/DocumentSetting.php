<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Texts on customer documents: a commercial note per document type (in the
 * four languages, can be switched off) and a seasonal message between two dates.
 */
#[Fillable(['key', 'value', 'updated_by_id'])]
class DocumentSetting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    private static array $cache = [];

    public static function get(string $key): array
    {
        return self::$cache[$key] ??= (static::where('key', $key)->value('value') ?? []);
    }

    public static function put(string $key, array $value, ?User $by): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by_id' => $by?->id]);
        unset(self::$cache[$key]);
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** The commercial note of a document type in a language, or null when off / empty. */
    public static function note(string $type, string $locale): ?string
    {
        $v = static::get('note.'.$type);
        if (! ($v['on'] ?? false)) {
            return null;
        }

        return trim((string) ($v['text'][$locale] ?? $v['text']['en'] ?? '')) ?: null;
    }

    /** The seasonal message, only between its dates. */
    public static function season(string $locale, ?Carbon $on = null): ?string
    {
        $v = static::get('season');
        $on ??= today();
        if (! ($v['on'] ?? false) || empty($v['from']) || empty($v['to']) || $on->lt(Carbon::parse($v['from'])) || $on->gt(Carbon::parse($v['to']))) {
            return null;
        }

        return trim((string) ($v['text'][$locale] ?? $v['text']['en'] ?? '')) ?: null;
    }
}
