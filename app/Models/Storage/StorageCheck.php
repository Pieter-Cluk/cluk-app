<?php

namespace App\Models\Storage;

use App\Models\User;
use App\Models\Warehouse\CheckProcedureVersion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Tech Check on a storage customer's piece: by CLUK (billed by the procedure's duration) or by their own staff. */
#[Fillable(['stored_piece_id', 'procedure_version_id', 'step', 'answers', 'photos', 'done_by', 'started_at', 'finished_at', 'minutes', 'result', 'checked_by_id'])]
class StorageCheck extends Model
{
    protected function casts(): array
    {
        return ['answers' => 'array', 'photos' => 'array', 'step' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'minutes' => 'integer'];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(StoredPiece::class, 'stored_piece_id');
    }

    public function procedureVersion(): BelongsTo
    {
        return $this->belongsTo(CheckProcedureVersion::class, 'procedure_version_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_id');
    }

    public static function openFor(StoredPiece $piece): self
    {
        return self::where('stored_piece_id', $piece->id)->whereNull('finished_at')->latest('id')->first()
            ?? self::create(['stored_piece_id' => $piece->id, 'started_at' => now()]);
    }
}
