<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One website setting (texts per language, a list, a link…), by key. */
#[Fillable(['key', 'value', 'updated_by_id'])]
class Setting extends Model
{
    protected $table = 'web_settings';

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
