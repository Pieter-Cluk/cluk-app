<?php

namespace App\Models\Website;

use App\Models\Product\Translated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A document on the website's Downloads page. Title and description per language (English fallback). */
#[Fillable(['category', 'title', 'description', 'path', 'original_name', 'mime', 'size', 'locale', 'sort', 'active', 'created_by_id'])]
class Download extends Model
{
    use Translated;

    protected $table = 'web_downloads';

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
}
