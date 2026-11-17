<?php

namespace App\Models\Warehouse;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A published, frozen version of a check procedure. */
#[Fillable(['procedure_id', 'version', 'steps', 'note', 'published_by_id'])]
class CheckProcedureVersion extends Model
{
    protected function casts(): array
    {
        return ['steps' => 'array', 'version' => 'integer'];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(CheckProcedure::class, 'procedure_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }
}
