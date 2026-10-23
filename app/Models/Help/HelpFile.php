<?php

namespace App\Models\Help;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A form, guide or template staff can download from Help. */
#[Fillable(['title', 'note', 'topic', 'path', 'original_name', 'size', 'uploaded_by_id'])]
class HelpFile extends Model
{
    protected $table = 'help_files';

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'FILE');
    }
}
