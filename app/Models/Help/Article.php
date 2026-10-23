<?php

namespace App\Models\Help;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A help article: title, short intro and the full explanation, in each app language (English is the fallback). */
#[Fillable(['slug', 'topic', 'title', 'intro', 'body', 'keywords', 'link_path', 'sort', 'active', 'updated_by_id'])]
class Article extends Model
{
    protected $table = 'help_articles';

    public const TOPICS = ['start', 'sales', 'rental', 'warehouse', 'dryhire', 'factory', 'afrit6', 'media', 'finance', 'settings'];

    protected function casts(): array
    {
        return ['title' => 'array', 'intro' => 'array', 'body' => 'array', 'active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /** The text of a field in the reader's language, else English. */
    public function t(string $field, ?string $locale = null): string
    {
        $v = $this->{$field} ?? [];
        $locale ??= app()->getLocale();

        return (string) (($v[$locale] ?? '') !== '' ? $v[$locale] : ($v['en'] ?? ''));
    }

    /** The explanation as blocks: paragraphs and numbered steps (lines starting with "1." or "-"). */
    public function blocks(?string $locale = null): array
    {
        $blocks = [];
        foreach (preg_split('/\R/', $this->t('body', $locale)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(\d+[.)]|[-•*])\s+(.*)$/u', $line, $m)) {
                $kind = ctype_digit($m[1][0]) ? 'ol' : 'ul';
                if (! $blocks || end($blocks)['kind'] !== $kind) {
                    $blocks[] = ['kind' => $kind, 'items' => []];
                }
                $blocks[array_key_last($blocks)]['items'][] = $m[2];
            } else {
                $blocks[] = ['kind' => 'p', 'text' => $line];
            }
        }

        return $blocks;
    }

    /** Everything the search looks at, all languages together, lower case and without accents. */
    public function haystack(): string
    {
        return self::fold(implode(' ', array_merge(array_values($this->title ?? []), array_values($this->intro ?? []), array_values($this->body ?? []), [$this->keywords])));
    }

    public static function fold(string $s): string
    {
        $s = mb_strtolower($s);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return $t !== false ? $t : $s;
    }
}
