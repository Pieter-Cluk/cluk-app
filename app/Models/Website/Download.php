<?php

namespace App\Models\Website;

use App\Models\Product\Translated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A document on the website's Downloads page. Title and description per language (English fallback).
 * Access: open to all, name and email first, or only customers with a login; every download is logged.
 */
#[Fillable(['category', 'title', 'description', 'path', 'original_name', 'mime', 'size', 'locale', 'access', 'sort', 'active', 'created_by_id'])]
class Download extends Model
{
    use Translated;

    protected $table = 'web_downloads';

    public const ACCESS = ['open', 'email', 'login'];

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'active' => 'boolean', 'size' => 'integer', 'sort' => 'integer', 'downloads' => 'integer'];
    }

    /** "PDF", "XLSX"… from the file name. */
    public function type(): string
    {
        return strtoupper(pathinfo($this->original_name ?: $this->path, PATHINFO_EXTENSION) ?: 'FILE');
    }

    public function sizeLabel(): string
    {
        $b = (int) $this->size;

        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.').' MB' : max(1, (int) round($b / 1024)).' KB';
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DownloadLog::class)->latest('id');
    }
}
